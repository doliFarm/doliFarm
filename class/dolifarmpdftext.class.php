<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dolifarm/class/dolifarmpdftext.class.php
 * \ingroup dolifarm
 * \brief   Minimal text extraction with positions from a PDF, enough for the printouts of the SIAN
 *          (standard Type1 fonts with WinAnsiEncoding, FlateDecode streams, cross-reference tables or streams,
 *          object streams). No dependency: Dolibarr has no PDF text parser.
 */

/**
 * Text of a PDF as pages of rows of cells (x position and text)
 */
class DoliFarmPdfText
{
	/** @var string Error message */
	public $error = '';

	/** @var string Raw content of the file */
	private $raw = '';

	/** @var array<int,string> Body of the objects by number (without "obj"/"endobj") */
	private $objects = array();

	/** @var float Vertical tolerance to put two texts on the same row */
	const ROW_TOLERANCE = 2.0;

	/**
	 * Read a PDF file
	 *
	 * @param	string	$filepath	Path of the file
	 * @return	array<int,array<int,array{y:float,cells:array<int,array{x:float,text:string,bold:bool}>}>>|null	Pages of rows (top to bottom), null if KO
	 */
	public function read($filepath)
	{
		$this->raw = (string) @file_get_contents($filepath);
		if (strncmp($this->raw, '%PDF-', 5) !== 0) {
			$this->error = 'NotPdf';
			return null;
		}
		if (preg_match('/\/Encrypt\s/', $this->raw)) {
			$this->error = 'Encrypted';
			return null;
		}
		$this->loadObjects();

		$pages = array();
		foreach ($this->pageObjects() as $pagenum) {
			$page = $this->dict($this->objects[$pagenum]);
			$fonts = $this->fonts($page);
			$content = '';
			foreach ($this->refs($this->value($page, 'Contents')) as $ref) {
				$content .= $this->streamData($ref)."\n";
			}
			$pages[] = $this->rows($this->texts($content, $fonts));
		}
		if (empty($pages)) {
			$this->error = 'NoPages';
			return null;
		}
		return $pages;
	}

	/**
	 * Index all the objects, also those inside object streams
	 *
	 * @return void
	 */
	private function loadObjects()
	{
		if (preg_match_all('/(?<![0-9])(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $this->raw, $m, PREG_SET_ORDER)) {
			foreach ($m as $o) {
				$this->objects[(int) $o[1]] = $o[2];	// The last definition wins (incremental updates)
			}
		}
		foreach ($this->objects as $num => $body) {
			if (!preg_match('/\/Type\s*\/ObjStm\b/', $body)) {
				continue;
			}
			$dict = $this->dict($body);
			$data = $this->streamData($num);
			$n = (int) $this->value($dict, 'N');
			$first = (int) $this->value($dict, 'First');
			$header = preg_split('/\s+/', trim(substr($data, 0, $first)));
			for ($i = 0; $i < $n && isset($header[2 * $i + 1]); $i++) {
				$objnum = (int) $header[2 * $i];
				$start = $first + (int) $header[2 * $i + 1];
				$end = isset($header[2 * $i + 3]) ? $first + (int) $header[2 * $i + 3] : strlen($data);
				if (!isset($this->objects[$objnum])) {
					$this->objects[$objnum] = substr($data, $start, $end - $start);
				}
			}
		}
	}

	/**
	 * Page objects in reading order, from the page tree of the catalog
	 *
	 * @return	array<int,int>	Object numbers
	 */
	private function pageObjects()
	{
		$root = 0;
		if (preg_match_all('/\/Root\s+(\d+)\s+\d+\s+R/', $this->raw, $m)) {
			$root = (int) end($m[1]);
		}
		$pages = array();
		if ($root && isset($this->objects[$root])) {
			$tree = $this->refs($this->value($this->dict($this->objects[$root]), 'Pages'));
			$this->walkPages($tree ? $tree[0] : 0, $pages, 0);
		}
		if (empty($pages)) {
			// No usable tree: pages in the order of the file
			foreach ($this->objects as $num => $body) {
				if (preg_match('/\/Type\s*\/Page\b(?!s)/', $body)) {
					$pages[] = $num;
				}
			}
		}
		return $pages;
	}

	/**
	 * @param	int				$num	Node of the page tree
	 * @param	array<int,int>	$pages	Pages found
	 * @param	int				$depth	Depth (guard against loops)
	 * @return	void
	 */
	private function walkPages($num, &$pages, $depth)
	{
		if ($depth > 32 || !isset($this->objects[$num])) {
			return;
		}
		$dict = $this->dict($this->objects[$num]);
		if (preg_match('/\/Type\s*\/Page\b(?!s)/', $dict)) {
			$pages[] = $num;
			return;
		}
		foreach ($this->refs($this->value($dict, 'Kids')) as $kid) {
			$this->walkPages($kid, $pages, $depth + 1);
		}
	}

	/**
	 * Fonts of a page: resource name => bold
	 *
	 * @param	string	$page	Dictionary of the page
	 * @return	array<string,bool>
	 */
	private function fonts($page)
	{
		$res = $this->resolve($this->value($page, 'Resources'));
		$fontdict = $this->resolve($this->value($res, 'Font'));
		$fonts = array();
		if (preg_match_all('/\/([^\s\/<>\[\]()]+)\s+(\d+)\s+\d+\s+R/', $fontdict, $m, PREG_SET_ORDER)) {
			foreach ($m as $f) {
				$body = isset($this->objects[(int) $f[2]]) ? $this->objects[(int) $f[2]] : '';
				$fonts[$f[1]] = (bool) preg_match('/\/BaseFont\s*\/[^\s\/]*Bold/i', $body);
			}
		}
		return $fonts;
	}

	/**
	 * Dictionary part of an object body
	 *
	 * @param	string	$body	Object body
	 * @return	string
	 */
	private function dict($body)
	{
		$pos = strpos($body, 'stream');
		return $pos === false ? $body : substr($body, 0, $pos);
	}

	/**
	 * Raw value of a key in a dictionary (a reference, a name, a number, an array or a dictionary)
	 *
	 * @param	string	$dict	Dictionary
	 * @param	string	$key	Key without the slash
	 * @return	string			'' if missing
	 */
	private function value($dict, $key)
	{
		if (!preg_match('/\/'.preg_quote($key, '/').'(?=[\s\/\[<(])\s*/', $dict, $m, PREG_OFFSET_CAPTURE)) {
			return '';
		}
		$pos = $m[0][1] + strlen($m[0][0]);
		$rest = substr($dict, $pos);
		if (preg_match('/^\d+\s+\d+\s+R/', $rest, $r)) {
			return $r[0];
		}
		$open = substr($rest, 0, 2) == '<<' ? '<<' : (substr($rest, 0, 1) == '[' ? '[' : '');
		if ($open === '') {
			preg_match('/^[^\s\/\[\]<>]*(\/[^\s\/\[\]<>]+)?/', $rest, $r);
			return trim($r[0]);
		}
		// Balanced << >> or [ ]
		$close = ($open == '<<') ? '>>' : ']';
		$depth = 0;
		$len = strlen($rest);
		for ($i = 0; $i < $len; $i++) {
			if (substr($rest, $i, strlen($open)) == $open) {
				$depth++;
				$i += strlen($open) - 1;
			} elseif (substr($rest, $i, strlen($close)) == $close) {
				$depth--;
				$i += strlen($close) - 1;
				if ($depth == 0) {
					return substr($rest, 0, $i + 1);
				}
			}
		}
		return $rest;
	}

	/**
	 * Follow a reference to the dictionary of its object
	 *
	 * @param	string	$value	Value (reference or direct)
	 * @return	string
	 */
	private function resolve($value)
	{
		if (preg_match('/^(\d+)\s+\d+\s+R$/', trim($value), $m)) {
			return isset($this->objects[(int) $m[1]]) ? $this->dict($this->objects[(int) $m[1]]) : '';
		}
		return $value;
	}

	/**
	 * Object numbers referenced in a value
	 *
	 * @param	string	$value	Value
	 * @return	array<int,int>
	 */
	private function refs($value)
	{
		$value = trim($value);
		if (preg_match('/^(\d+)\s+\d+\s+R$/', $value, $m)) {
			// A reference to an array of references
			$target = isset($this->objects[(int) $m[1]]) ? trim($this->objects[(int) $m[1]]) : '';
			if ($target !== '' && $target[0] == '[') {
				$value = $target;
			} else {
				return array((int) $m[1]);
			}
		}
		preg_match_all('/(\d+)\s+\d+\s+R/', $value, $m);
		return array_map('intval', $m[1]);
	}

	/**
	 * Decoded data of a stream object
	 *
	 * @param	int		$num	Object number
	 * @return	string
	 */
	private function streamData($num)
	{
		if (!isset($this->objects[$num]) || !preg_match('/stream\r?\n/', $this->objects[$num], $m, PREG_OFFSET_CAPTURE)) {
			return '';
		}
		$body = $this->objects[$num];
		$dict = substr($body, 0, $m[0][1]);
		$start = $m[0][1] + strlen($m[0][0]);
		$length = $this->value($dict, 'Length');
		if (preg_match('/^(\d+)\s+\d+\s+R$/', $length, $r)) {
			$length = isset($this->objects[(int) $r[1]]) ? trim($this->objects[(int) $r[1]]) : '';
		}
		$data = is_numeric($length) ? substr($body, $start, (int) $length) : preg_replace('/\r?\n?endstream\s*$/', '', substr($body, $start));
		if (strpos($this->value($dict, 'Filter'), 'FlateDecode') !== false) {
			$decoded = @gzuncompress($data);
			if ($decoded === false) {
				$decoded = @zlib_decode($data);
			}
			$data = ($decoded === false) ? '' : $decoded;
		}
		return $data;
	}

	/**
	 * Texts of a content stream with their position
	 *
	 * @param	string				$content	Content stream
	 * @param	array<string,bool>	$fonts		Fonts of the page
	 * @return	array<int,array{x:float,y:float,text:string,bold:bool}>
	 */
	private function texts($content, $fonts)
	{
		$texts = array();
		$ctm = array(1, 0, 0, 1, 0, 0);
		$stack = array();
		$tm = $tlm = array(1, 0, 0, 1, 0, 0);
		$leading = 0.0;
		$bold = false;
		$operands = array();

		$len = strlen($content);
		$i = 0;
		while ($i < $len) {
			$ch = $content[$i];
			if (ctype_space($ch)) {
				$i++;
				continue;
			}
			if ($ch == '%') {
				$eol = strcspn($content, "\r\n", $i);
				$i += $eol;
				continue;
			}
			if ($ch == '(') {
				$operands[] = array('s', $this->literalString($content, $i));
				continue;
			}
			if ($ch == '<' && ($content[$i + 1] ?? '') != '<') {
				$end = strpos($content, '>', $i);
				$hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($content, $i + 1, $end - $i - 1));
				if (strlen($hex) % 2) {
					$hex .= '0';
				}
				$operands[] = array('s', (string) hex2bin($hex));
				$i = $end + 1;
				continue;
			}
			if ($ch == '[') {
				$operands[] = array('[', null);
				$i++;
				continue;
			}
			if ($ch == ']') {
				// Collapse the array: only its strings matter (TJ)
				$arr = array();
				while ($operands && end($operands)[0] != '[') {
					array_unshift($arr, array_pop($operands));
				}
				array_pop($operands);
				$str = '';
				foreach ($arr as $el) {
					if ($el[0] == 's') {
						$str .= $el[1];
					} elseif ($el[0] == 'n' && $el[1] < -200) {
						$str .= ' ';	// Large negative kerning is a space
					}
				}
				$operands[] = array('s', $str);
				$i++;
				continue;
			}
			if ($ch == '/') {
				preg_match('/\/[^\s\/\[\]<>()%{}]*/', $content, $m, 0, $i);
				$operands[] = array('/', substr($m[0], 1));
				$i += strlen($m[0]);
				continue;
			}
			if ($ch == '<' || $ch == '>') {
				// Inline dictionary (BDC/BI): skip the delimiters
				$i += 2;
				continue;
			}
			preg_match('/[^\s\/\[\]<>()%{}]+/', $content, $m, 0, $i);
			$tok = isset($m[0]) ? $m[0] : $ch;
			$i += max(1, strlen($tok));
			if (is_numeric($tok)) {
				$operands[] = array('n', (float) $tok);
				continue;
			}
			$n = array();
			foreach ($operands as $o) {
				if ($o[0] == 'n') {
					$n[] = $o[1];
				}
			}
			switch ($tok) {
				case 'q':
					$stack[] = $ctm;
					break;
				case 'Q':
					if ($stack) {
						$ctm = array_pop($stack);
					}
					break;
				case 'cm':
					if (count($n) >= 6) {
						$ctm = self::multiply(array_slice($n, -6), $ctm);
					}
					break;
				case 'BT':
					$tm = $tlm = array(1, 0, 0, 1, 0, 0);
					break;
				case 'Tf':
					foreach ($operands as $o) {
						if ($o[0] == '/') {
							$bold = !empty($fonts[$o[1]]);
						}
					}
					break;
				case 'TL':
					$leading = (float) end($n);
					break;
				case 'Tm':
					if (count($n) >= 6) {
						$tm = $tlm = array_slice($n, -6);
					}
					break;
				case 'Td':
				case 'TD':
					if (count($n) >= 2) {
						list($tx, $ty) = array_slice($n, -2);
						if ($tok == 'TD') {
							$leading = -$ty;
						}
						$tlm = self::multiply(array(1, 0, 0, 1, $tx, $ty), $tlm);
						$tm = $tlm;
					}
					break;
				case 'T*':
					$tlm = self::multiply(array(1, 0, 0, 1, 0, -$leading), $tlm);
					$tm = $tlm;
					break;
				case "'":
				case '"':
					$tlm = self::multiply(array(1, 0, 0, 1, 0, -$leading), $tlm);
					$tm = $tlm;
					// no break: show the string
				case 'Tj':
				case 'TJ':
					$str = '';
					foreach ($operands as $o) {
						if ($o[0] == 's') {
							$str = $o[1];
						}
					}
					$text = trim(self::winAnsi($str));
					if ($text !== '') {
						$pos = self::multiply($tm, $ctm);
						$texts[] = array('x' => round($pos[4], 2), 'y' => round($pos[5], 2), 'text' => $text, 'bold' => $bold);
					}
					break;
			}
			$operands = array();
		}
		return $texts;
	}

	/**
	 * Literal string starting at $i (on the opening parenthesis); $i moves after the closing one
	 *
	 * @param	string	$s	Content
	 * @param	int		$i	Position
	 * @return	string
	 */
	private function literalString($s, &$i)
	{
		$out = '';
		$depth = 0;
		$len = strlen($s);
		for ($i++; $i < $len; $i++) {
			$c = $s[$i];
			if ($c == '\\') {
				$i++;
				$e = $s[$i] ?? '';
				$map = array('n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f");
				if (isset($map[$e])) {
					$out .= $map[$e];
				} elseif (ctype_digit($e) && $e < '8') {
					$oct = $e;
					while (strlen($oct) < 3 && isset($s[$i + 1]) && ctype_digit($s[$i + 1]) && $s[$i + 1] < '8') {
						$oct .= $s[++$i];
					}
					$out .= chr(octdec($oct) & 0xFF);
				} elseif ($e == "\r" || $e == "\n") {
					if ($e == "\r" && ($s[$i + 1] ?? '') == "\n") {
						$i++;
					}
				} else {
					$out .= $e;
				}
				continue;
			}
			if ($c == '(') {
				$depth++;
			} elseif ($c == ')') {
				if ($depth == 0) {
					$i++;
					return $out;
				}
				$depth--;
			}
			$out .= $c;
		}
		return $out;
	}

	/**
	 * Product of two matrices [a b c d e f]
	 *
	 * @param	array<int,float>	$a	First
	 * @param	array<int,float>	$b	Second
	 * @return	array<int,float>
	 */
	private static function multiply($a, $b)
	{
		return array(
			$a[0] * $b[0] + $a[1] * $b[2], $a[0] * $b[1] + $a[1] * $b[3],
			$a[2] * $b[0] + $a[3] * $b[2], $a[2] * $b[1] + $a[3] * $b[3],
			$a[4] * $b[0] + $a[5] * $b[2] + $b[4], $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
		);
	}

	/**
	 * WinAnsi (cp1252) bytes to UTF-8
	 *
	 * @param	string	$s	Bytes
	 * @return	string
	 */
	private static function winAnsi($s)
	{
		$s = str_replace("\0", '', $s);
		$utf = @iconv('CP1252', 'UTF-8//IGNORE', $s);
		return $utf === false ? mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1') : $utf;
	}

	/**
	 * Group the texts of a page into rows, top to bottom and left to right
	 *
	 * @param	array<int,array{x:float,y:float,text:string,bold:bool}>	$texts	Texts
	 * @return	array<int,array{y:float,cells:array<int,array{x:float,text:string,bold:bool}>}>
	 */
	private function rows($texts)
	{
		usort($texts, function ($a, $b) {
			return ($b['y'] <=> $a['y']) ?: ($a['x'] <=> $b['x']);
		});
		$rows = array();
		foreach ($texts as $t) {
			$last = count($rows) - 1;
			if ($last >= 0 && abs($rows[$last]['y'] - $t['y']) <= self::ROW_TOLERANCE) {
				$rows[$last]['cells'][] = array('x' => $t['x'], 'text' => $t['text'], 'bold' => $t['bold']);
			} else {
				$rows[] = array('y' => $t['y'], 'cells' => array(array('x' => $t['x'], 'text' => $t['text'], 'bold' => $t['bold'])));
			}
		}
		foreach ($rows as $k => $row) {
			usort($rows[$k]['cells'], function ($a, $b) {
				return $a['x'] <=> $b['x'];
			});
		}
		return $rows;
	}
}
