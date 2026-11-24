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
 * \file    dolifarm/class/dolifarmfascicolopdfreader.class.php
 * \ingroup dolifarm
 * \brief   Reader of the PDF of the validation sheet of the farm dossier printed by the SIAN ("AGEA - FASCICOLO
 *          AZIENDALE - VALIDAZIONE DATI", DM 12 gennaio 2015 n. 162). Returns the same structure as
 *          DoliFarmFascicoloXmlReader, so the same importer writes it.
 *
 *          The PDF has no data structure: the reader finds the sections by their titles and rebuilds the rows of
 *          the tables from the position of the texts (columns of the layout of the SIAN, as printed in 2025).
 *          A change of the layout by AGEA can make it miss data: the preview of the import shows what was read.
 */

dol_include_once('/dolifarm/class/dolifarmpdftext.class.php');

/**
 * Reader of the PDF of the dossier
 */
class DoliFarmFascicoloPdfReader
{
	/** @var string Error message */
	public $error = '';

	/** @var array<int,string> Detailed errors */
	public $errors = array();

	/** @var array<int,string> Warnings on data of the file not read */
	public $warnings = array();

	/** @var float Max vertical distance of a text from the number of its row of table */
	const RECORD_DISTANCE = 19.0;

	/** @var array<string,string> Titles of the sections (start of the text, upper case) => key */
	const SECTIONS = array(
		'DATI ANAGRAFICI E AZIENDALI' => 'anagrafica',
		'RECAPITO' => 'recapito',
		'ORIENTAMENTO TECNICO' => 'ote',
		'RIEPILOGO DELLA COMPOSIZIONE' => 'riepilogo',
		'COMPOSIZIONE TERRITORIALE' => 'terreni',
		'SEGNALAZIONI SUI TERRENI' => 'segnalazioni',
		'VINCOLI AMMINISTRATIVI' => 'vincoli',
		'COMPOSIZIONE ZOOTECNICA' => 'zootecnia',
		'FABBRICATI' => 'fabbricati',
		'MEZZI DI PRODUZIONE' => 'macchine',
		'MANODOPERA' => 'manodopera',
		'VINCOLI AZIENDALI' => 'vincoliaziendali',
		'TITOLI ALL' => 'titoli',
		'LEGAMI ASSOCIATIVI' => 'legami',
		'ISCRIZIONE AD ALBI' => 'albi',
		'DOCUMENTI PRESENTI' => 'documenti',
		'COORDINATE BANCARIE' => 'conti',
		'CONSISTENZA TERRITORIALE AZIENDALE' => 'consistenza',
		'PIANO DI COLTIVAZIONE - APPEZZAMENTI' => 'appezzamenti',
		'PIANO DI COLTIVAZIONE - PARTICELLE' => 'pcgparticelle',
		'DICHIARAZIONI DEL CAA' => 'dichiarazionicaa',
		'DICHIARAZIONI DEL PRODUTTORE' => 'fine',
	);

	/** @var array<string,array<int,array{page:int,y:float,cells:array<int,array{x:float,text:string,bold:bool}>}>> Rows by section */
	private $sections = array();

	/** @var array<string,string> Footer: number of the sheet, CUAA, date of print */
	private $footer = array('number' => '', 'cuaa' => '', 'date_print' => '');

	/**
	 * Read the PDF
	 *
	 * @param	string	$filepath	Path of the file
	 * @return	array<string,mixed>|null	Neutral structure (see DoliFarmFascicoloXmlReader::read()), null if KO
	 */
	public function read($filepath)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		$text = new DoliFarmPdfText();
		$pages = $text->read($filepath);
		if ($pages === null) {
			$this->error = $langs->transnoentitiesnoconv($text->error == 'Encrypted' ? 'DossierImportPdfEncrypted' : 'DossierImportPdfUnreadable');
			dol_syslog(__METHOD__.' '.$text->error, LOG_DEBUG);
			return null;
		}
		$this->split($pages);
		if (empty($this->sections['anagrafica']) || empty($this->sections['head'])
			|| strpos(implode(' ', $this->texts($this->sections['head'])), 'FASCICOLO AZIENDALE') === false) {
			$this->error = $langs->transnoentitiesnoconv('DossierImportPdfNotDossier');
			return null;
		}

		$particelle = $this->readParticelle();
		$isole = $this->readAppezzamenti();
		$this->readPcgParticelle($particelle);
		$scheda = $this->readScheda();

		// Campaign: year of the end of the cultivation plan, else of the print
		$campagna = 0;
		if ($isole['date_end'] !== '') {
			$campagna = (int) substr($isole['date_end'], 0, 4);
		} elseif ($scheda['date_print'] !== '') {
			$campagna = (int) substr($scheda['date_print'], 0, 4);
		}

		$skipped = array();
		foreach (array('macchine' => 'DossierImportPdfMachines', 'manodopera' => 'DossierImportPdfLabour', 'vincoliaziendali' => 'DossierImportPdfFarmConstraints', 'titoli' => 'DossierImportPdfEntitlements', 'albi' => 'DossierImportPdfRegisters') as $key => $label) {
			$n = count($this->records($key, array($this, 'isNumberedRow')));
			if ($n > 0) {
				$skipped[] = $langs->transnoentitiesnoconv($label).' ('.$n.')';
			}
		}

		return array(
			'format' => 'pdf',
			'campagna' => $campagna,
			'scheda' => $scheda,
			'anagrafica' => $this->readAnagrafica(),
			'ote' => $this->readOte(),
			'particelle' => $particelle,
			'isole' => $isole,
			'allevamenti' => $this->readAllevamenti(),
			'fabbricati' => $this->readFabbricati(),
			'macchine' => array(),
			'organismi' => $this->readOrganismi(),
			'documenti' => $this->readDocumenti(),
			'conti' => $this->readConti(),
			'skipped' => $skipped,
		);
	}

	// ------------------------------------------------------------------------------------------------
	// Layout
	// ------------------------------------------------------------------------------------------------

	/**
	 * Split the rows of all the pages into sections; read the footer
	 *
	 * @param	array<int,array<int,array{y:float,cells:array<int,array{x:float,text:string,bold:bool}>}>>	$pages	Pages
	 * @return	void
	 */
	private function split($pages)
	{
		$current = 'head';
		$this->sections = array('head' => array());
		foreach ($pages as $p => $rows) {
			foreach ($rows as $row) {
				if ($row['y'] < 20) {
					// Footer: "<sheet> - CUAA: <cuaa>  Data Stampa: dd/mm/yyyy  pag. N"
					$line = $this->rowText($row);
					if ($this->footer['number'] === '' && preg_match('/^(\d+)\s*-\s*CUAA:\s*([A-Z0-9]+)/', $line, $m)) {
						$this->footer['number'] = $m[1];
						$this->footer['cuaa'] = $m[2];
					}
					if ($this->footer['date_print'] === '' && preg_match('/Data Stampa:\s*(\d{2}\/\d{2}\/\d{4})/', $line, $m)) {
						$this->footer['date_print'] = self::isoDate($m[1]);
					}
					continue;
				}
				$first = $row['cells'][0];
				if ($first['bold'] && $first['x'] < 12) {
					$title = strtoupper($first['text']);
					foreach (self::SECTIONS as $start => $key) {
						if (strpos($title, $start) === 0) {
							$current = $key;
							if (!isset($this->sections[$current])) {
								$this->sections[$current] = array();
							}
							$this->sections[$current][] = array('page' => $p, 'y' => $row['y'], 'cells' => $row['cells'], 'title' => true);
							continue 2;
						}
					}
				}
				$this->sections[$current][] = array('page' => $p, 'y' => $row['y'], 'cells' => $row['cells']);
			}
		}
	}

	/**
	 * Text of a row
	 *
	 * @param	array{cells:array<int,array{x:float,text:string,bold:bool}>}	$row	Row
	 * @return	string
	 */
	private function rowText($row)
	{
		return implode(' ', array_map(function ($c) {
			return $c['text'];
		}, $row['cells']));
	}

	/**
	 * Texts of rows
	 *
	 * @param	array<int,array{cells:array<int,array{x:float,text:string,bold:bool}>}>	$rows	Rows
	 * @return	array<int,string>
	 */
	private function texts($rows)
	{
		return array_map(array($this, 'rowText'), $rows);
	}

	/**
	 * Row with the number of a row of table: "N" and ")" at the left margin
	 *
	 * @param	array{cells:array<int,array{x:float,text:string,bold:bool}>}	$row	Row
	 * @return	bool
	 */
	public function isNumberedRow($row)
	{
		$c = $row['cells'];
		return count($c) >= 2 && $c[0]['x'] < 25 && preg_match('/^\d+$/', $c[0]['text']) && $c[1]['text'] === ')';
	}

	/**
	 * Rows of table of a section: each anchor row (found by $isanchor) with the texts around it
	 *
	 * @param	string		$section	Section
	 * @param	callable	$isanchor	Function(row) => bool
	 * @return	array<int,array<int,array{x:float,dy:float,text:string}>>	Records: texts with their distance from the anchor (positive = above)
	 */
	private function records($section, $isanchor)
	{
		if (empty($this->sections[$section])) {
			return array();
		}
		$rows = $this->sections[$section];
		$records = array();
		$anchors = array();
		foreach ($rows as $i => $row) {
			if (empty($row['title']) && call_user_func($isanchor, $row)) {
				$anchors[$i] = count($records);
				$records[] = array();
			}
		}
		foreach ($rows as $i => $row) {
			if (!empty($row['title'])) {
				continue;
			}
			$best = null;
			if (isset($anchors[$i])) {
				$best = $i;
			} else {
				$allbold = true;
				foreach ($row['cells'] as $c) {
					$allbold = $allbold && $c['bold'];
				}
				if ($allbold) {
					continue;	// Header of the table
				}
				$dist = self::RECORD_DISTANCE;
				foreach ($anchors as $a => $n) {
					if ($rows[$a]['page'] == $row['page'] && abs($rows[$a]['y'] - $row['y']) <= $dist) {
						$dist = abs($rows[$a]['y'] - $row['y']);
						$best = $a;
					}
				}
			}
			if ($best === null) {
				continue;
			}
			foreach ($row['cells'] as $c) {
				$records[$anchors[$best]][] = array('x' => $c['x'], 'dy' => round($row['y'] - $rows[$best]['y'], 1), 'text' => $c['text']);
			}
		}
		// Top to bottom, left to right
		foreach ($records as $k => $cells) {
			usort($cells, function ($a, $b) {
				return ($b['dy'] <=> $a['dy']) ?: ($a['x'] <=> $b['x']);
			});
			$records[$k] = $cells;
		}
		return $records;
	}

	/**
	 * Texts of a record in a column, top to bottom
	 *
	 * @param	array<int,array{x:float,dy:float,text:string}>	$record	Record
	 * @param	float		$from	Left limit (included)
	 * @param	float		$to		Right limit (excluded)
	 * @param	int|null	$side	1 = only above the anchor row, -1 = only below, 0 = only on it, null = all
	 * @return	array<int,string>
	 */
	private static function col($record, $from, $to, $side = null)
	{
		$out = array();
		foreach ($record as $c) {
			if ($c['x'] < $from || $c['x'] >= $to || $c['text'] === ')') {
				continue;
			}
			if ($side !== null && (($side > 0 && $c['dy'] <= 0.5) || ($side < 0 && $c['dy'] >= -0.5) || ($side === 0 && abs($c['dy']) > 0.5))) {
				continue;
			}
			$out[] = $c['text'];
		}
		return $out;
	}

	/**
	 * Joined text of a column
	 *
	 * @param	array<int,array{x:float,dy:float,text:string}>	$record	Record
	 * @param	float		$from	Left limit
	 * @param	float		$to		Right limit
	 * @param	int|null	$side	See col()
	 * @return	string
	 */
	private static function cell($record, $from, $to, $side = null)
	{
		return trim(preg_replace('/\s+/', ' ', implode(' ', self::col($record, $from, $to, $side))));
	}

	/**
	 * Value after a label in the rows of some sections ("CUAA :" => "01906450893")
	 *
	 * @param	array<int,string>	$sections	Sections
	 * @param	string				$label		Label without the colon
	 * @return	string
	 */
	private function labelValue($sections, $label)
	{
		$wanted = self::labelKey($label);
		foreach ($sections as $section) {
			foreach ($this->sections[$section] ?? array() as $row) {
				$cells = $row['cells'];
				foreach ($cells as $i => $c) {
					if (self::labelKey($c['text']) !== $wanted) {
						continue;
					}
					// The value: the next cell, unless it is another label (empty value)
					if (isset($cells[$i + 1]) && !preg_match('/:\s*$/', $cells[$i + 1]['text'])) {
						return trim($cells[$i + 1]['text']);
					}
					return '';
				}
			}
		}
		return '';
	}

	/**
	 * @param	string	$s	Label
	 * @return	string		Upper case without spaces and colons
	 */
	private static function labelKey($s)
	{
		return preg_replace('/[\s:]+/', '', strtoupper($s));
	}

	/**
	 * dd/mm/yyyy => yyyy-mm-dd
	 *
	 * @param	string	$s	Date
	 * @return	string		'' if not a date
	 */
	private static function isoDate($s)
	{
		return preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $s, $m) ? $m[3].'-'.$m[2].'-'.$m[1] : '';
	}

	/**
	 * Surface "Ha,Aa,Ca" (04,85,95) => hectares
	 *
	 * @param	string	$s	Surface
	 * @return	float|null
	 */
	private static function hectares($s)
	{
		if (!preg_match('/^(\d+),(\d{2}),(\d{2})$/', trim($s), $m)) {
			return null;
		}
		return round((int) $m[1] + (int) $m[2] / 100 + (int) $m[3] / 10000, 4);
	}

	/**
	 * Coded value "420 = OLIVO" or "000"
	 *
	 * @param	string	$s	Text
	 * @return	array{code:string,label:string}
	 */
	private static function coded($s)
	{
		if (preg_match('/^(\d{3})\s*(?:=\s*(.*))?$/', trim($s), $m)) {
			return array('code' => $m[1], 'label' => isset($m[2]) ? trim(preg_replace('/\s+/', ' ', $m[2])) : '');
		}
		return array('code' => '', 'label' => trim($s));
	}

	/**
	 * Empty comune
	 *
	 * @param	string	$name		Name
	 * @param	string	$province	Province
	 * @return	array<string,string>
	 */
	private static function comune($name, $province = '')
	{
		return array('name' => $name, 'province' => $province, 'belfiore' => '', 'istat' => '');
	}

	/**
	 * Key of a parcel to match the sections between them
	 *
	 * @param	string	$comune		Comune
	 * @param	string	$section	Section
	 * @param	string	$sheet		Sheet
	 * @param	string	$parcel		Parcel
	 * @param	string	$sub		Sub
	 * @return	string
	 */
	private static function parcelKey($comune, $section, $sheet, $parcel, $sub)
	{
		$n = function ($s) {
			$s = strtoupper(trim($s));
			return preg_match('/^\d+$/', $s) ? ltrim($s, '0') : $s;
		};
		return implode('|', array(strtoupper(trim($comune)), $n($section), $n($sheet), $n($parcel), $n($sub)));
	}

	// ------------------------------------------------------------------------------------------------
	// Sections
	// ------------------------------------------------------------------------------------------------

	/**
	 * @return	array<string,mixed>
	 */
	private function readScheda()
	{
		$number = $this->footer['number'];
		$agency = '';
		foreach ($this->sections['head'] as $row) {
			foreach ($row['cells'] as $c) {
				if ($number === '' && $c['x'] > 600 && preg_match('/^\d{8,}$/', $c['text'])) {
					$number = $c['text'];
				}
				if ($agency === '' && preg_match('/^([A-Z]+)\s*-\s*FASCICOLO AZIENDALE/', $c['text'], $m)) {
					$agency = $m[1];
				}
			}
		}
		$reference = '';
		foreach ($this->sections['consistenza'] ?? array() as $row) {
			if (!empty($row['title']) && preg_match('/\bAL (\d{2}\/\d{2}\/\d{4})/', $this->rowText($row), $m)) {
				$reference = self::isoDate($m[1]);
			}
		}
		$operator = $office = '';
		foreach ($this->texts($this->sections['dichiarazionicaa'] ?? array()) as $line) {
			if (preg_match("/sottoscritto\s+(.+?),\s*operatore dell'Ufficio\s+(\d+)/i", $line, $m)) {
				$operator = trim($m[1]);
				$office = $m[2];
			}
		}
		return array(
			'number' => $number,
			'protocol' => $this->labelValue(array('head'), 'Protocollo'),
			'act_type' => '',
			'paying_agency' => $agency,
			'date_signature' => '',
			'date_reference' => $reference,
			'date_print' => $this->footer['date_print'],
			'caa' => $this->labelValue(array('head'), 'Mandato'),
			'caa_office' => $office,
			'caa_operator' => $operator,
			'date_mandate' => self::isoDate($this->labelValue(array('head'), 'Data sottoscrizione del mandato')),
		);
	}

	/**
	 * @return	array<string,mixed>
	 */
	private function readAnagrafica()
	{
		$s = array('anagrafica', 'recapito');
		$cuaa = strtoupper($this->labelValue($s, 'CUAA'));
		$vat = $this->labelValue($s, 'Partita IVA');

		$representatives = array();
		$rep = $this->labelValue($s, 'Titolare o Rappresentante legale');
		if (preg_match('/^([A-Z0-9]{16})\s*-\s*(.+)$/', $rep, $m)) {
			list($lastname, $firstname) = self::splitName($m[2], $m[1]);
			$representatives[] = array(
				'cf' => $m[1],
				'lastname' => $lastname,
				'firstname' => $firstname,
				'birthday' => self::isoDate($this->labelValue($s, 'Data di nascita')),
				'birthplace' => array('name' => $this->labelValue($s, 'Comune di nascita'), 'province' => $this->labelValue($s, 'Prov'), 'belfiore' => substr($m[1], 11, 4), 'istat' => ''),
				'role' => '',
			);
		}

		// "CONTRADA STALLAINI S P 73 SN  96017 NOTO (SR)"
		$address = $this->labelValue($s, 'Indirizzo');
		$zip = '';
		$town = self::comune('');
		if (preg_match('/^(.*?)\s+(\d{5})\s+(.+?)\s*\(([A-Z]{2})\)\s*$/', $address, $m)) {
			$address = trim($m[1]);
			$zip = $m[2];
			$town = self::comune(trim($m[3]), $m[4]);
		}
		$ateco = '';
		if (preg_match('/^(\d{2})[-.](\d{2})[-.](\d{2})/', $this->labelValue($s, 'Attività (codici ATECO)'), $m)) {
			$ateco = $m[1].'.'.$m[2].'.'.$m[3];
		}
		return array(
			'cuaa' => $cuaa,
			'vat' => $vat !== '' ? array($vat) : array(),
			'name' => $this->labelValue($s, 'Denominazione'),
			'legal_form' => $this->labelValue($s, 'Forma giuridica'),
			'ateco' => $ateco,
			'representatives' => $representatives,
			'address' => $address,
			'zip' => $zip,
			'town' => $town,
			'pec' => $this->labelValue($s, 'PEC'),
			'email' => $this->labelValue($s, 'Mail'),
			'phone' => $this->labelValue($s, 'Telefono'),
			'rea' => $this->labelValue($s, 'Numero REA'),
			'date_registry' => self::isoDate($this->labelValue($s, 'Data iscrizione registro')),
			'date_activity' => self::isoDate($this->labelValue($s, 'Data inizio')),
		);
	}

	/**
	 * Split "GRILLO LUIGI" into last name and first name using the tax code (letters 1-3 are of the last name)
	 *
	 * @param	string	$name	Last name and first name
	 * @param	string	$cf		Tax code
	 * @return	array{0:string,1:string}
	 */
	private static function splitName($name, $cf)
	{
		$words = preg_split('/\s+/', trim($name));
		for ($i = 1; $i < count($words); $i++) {
			$last = implode(' ', array_slice($words, 0, $i));
			if (self::cfSurname($last) === substr($cf, 0, 3)) {
				return array($last, implode(' ', array_slice($words, $i)));
			}
		}
		// Not found: first word as last name
		return array($words[0], implode(' ', array_slice($words, 1)));
	}

	/**
	 * Three letters of the last name in the tax code
	 *
	 * @param	string	$s	Last name
	 * @return	string
	 */
	private static function cfSurname($s)
	{
		$s = preg_replace('/[^A-Z]/', '', strtoupper(dol_string_unaccent($s)));
		$cons = preg_replace('/[AEIOU]/', '', $s);
		$vow = preg_replace('/[^AEIOU]/', '', $s);
		return substr($cons.$vow.'XXX', 0, 3);
	}

	/**
	 * @return	array<string,mixed>
	 */
	private function readOte()
	{
		$res = array('code' => '', 'label' => '', 'economic_size' => null);
		foreach ($this->sections['ote'] ?? array() as $row) {
			$cells = $row['cells'];
			if (self::labelKey($cells[0]['text']) === 'OTE' && isset($cells[1])) {
				$res['code'] = $cells[1]['text'];
				$label = array();
				foreach (array_slice($cells, 2) as $c) {
					if ($c['text'] !== '-') {
						$label[] = $c['text'];
					}
				}
				$res['label'] = implode(' ', $label);
			}
			if (self::labelKey($cells[0]['text']) === 'DIMENSIONE' && isset($cells[1]) && is_numeric($cells[1]['text'])) {
				$res['economic_size'] = (float) $cells[1]['text'];
			}
		}
		return $res;
	}

	/**
	 * Consistenza terreni and their constraints
	 *
	 * @return	array<string,array<string,mixed>>	Parcels by id
	 */
	private function readParticelle()
	{
		$parcels = array();
		foreach ($this->records('terreni', array($this, 'isNumberedRow')) as $r) {
			$comune = self::cell($r, 24, 150, 0);
			$section = self::cell($r, 150, 175, 0);
			$sheet = self::cell($r, 175, 198, 0);
			$parcel = self::cell($r, 198, 224, 0);
			$sub = self::cell($r, 224, 245, 0);
			$label = array();
			$protocol = '';
			foreach (self::col($r, 245, 410) as $t) {
				if (preg_match('/^[A-Z]+\.[A-Z0-9]+\.\d{4}\.\d+$/', $t)) {
					$protocol = $t;
				} else {
					$label[] = $t;
				}
			}
			$owner = self::cell($r, 410, 575);
			$id = self::parcelKey($comune, $section, $sheet, $parcel, $sub);
			$parcels[$id] = array(
				'id' => $id,
				'comune' => self::comune($comune),
				'section' => $section,
				'sheet' => $sheet,
				'parcel' => $parcel,
				'sub' => $sub,
				'conduction' => array(
					'code' => 0,
					'subcode' => 0,
					'label' => implode(' ', $label),
					'protocol' => $protocol,
					'date_start' => self::isoDate(self::cell($r, 600, 650)),
					'date_end' => self::isoDate(self::cell($r, 650, 700)),
				),
				'owners' => $owner !== '' ? array(array('cf' => '', 'name' => $owner)) : array(),
				'partial' => self::cell($r, 575, 600) === 'SI',
				'size_cadastral' => (float) self::hectares(self::cell($r, 700, 748)),
				'size_graphic' => self::hectares(self::cell($r, 748, 795)),
				'size_conducted' => (float) self::hectares(self::cell($r, 795, 900)),
				'constraints' => array(),
				'cultivations' => array(),
			);
		}

		// Constraints: the row of the parcel (comune, sheet, parcel) with the SIC/ZPS/ZVN above and below it
		$isparcelrow = function ($row) {
			$hascomune = $hasparcel = false;
			foreach ($row['cells'] as $c) {
				$hascomune = $hascomune || ($c['x'] < 60 && !$c['bold']);
				$hasparcel = $hasparcel || ($c['x'] >= 90 && $c['x'] < 118 && preg_match('/^\d+$/', $c['text']));
			}
			return $hascomune && $hasparcel;
		};
		foreach ($this->records('vincoli', $isparcelrow) as $r) {
			$id = self::parcelKey(self::cell($r, 0, 58, 0), self::cell($r, 58, 78, 0), self::cell($r, 78, 98, 0), self::cell($r, 98, 118, 0), self::cell($r, 118, 140, 0));
			if (!isset($parcels[$id])) {
				continue;
			}
			$v = array('sic' => '', 'zps' => '', 'zvn' => false, 'zone_psr' => self::cell($r, 390, 445), 'disadvantaged' => self::cell($r, 445, 495));
			foreach (self::col($r, 330, 390) as $t) {
				if (preg_match('/^(SIC|ZPS|ZVN):\s*(.*)$/', $t, $m)) {
					if ($m[1] == 'ZVN') {
						$v['zvn'] = (trim($m[2]) === 'SI');
					} else {
						$v[strtolower($m[1])] = trim($m[2]);
					}
				}
			}
			$parcels[$id]['constraints'][] = $v;
		}
		return $parcels;
	}

	/**
	 * Values of the stacked column of a cultivation: land use, destination, use, quality, variety
	 *
	 * @param	array<int,array{x:float,dy:float,text:string}>	$r	Record
	 * @return	array<string,array{code:string,label:string}>
	 */
	private static function stackedCodes($r)
	{
		$values = array();
		foreach (self::col($r, 230, 440) as $t) {
			if (preg_match('/^\d{3}\b/', $t) || empty($values)) {
				$values[] = $t;
			} else {
				$values[count($values) - 1] .= ' '.$t;	// Wrapped label
			}
		}
		$keys = array('land_use', 'destination', 'use', 'quality', 'variety');
		$res = array();
		foreach ($keys as $i => $k) {
			$res[$k] = self::coded($values[$i] ?? '');
		}
		return $res;
	}

	/**
	 * Cultivation of a record of the cultivation plan
	 *
	 * @param	array<int,array{x:float,dy:float,text:string}>	$r	Record
	 * @return	array<string,mixed>
	 */
	private static function cultivation($r)
	{
		$c = self::stackedCodes($r);
		$dates = self::col($r, 580, 620);
		$c['size'] = (float) self::hectares(self::cell($r, 540, 580));
		$c['date_start'] = self::isoDate($dates[0] ?? '');
		$c['date_end'] = self::isoDate($dates[1] ?? '');
		$c['organic'] = '';
		$c['maintenance'] = self::cell($r, 750, 800);
		return $c;
	}

	/**
	 * Graphic plan: islands and fields ("appezzamenti")
	 *
	 * @return	array{date_start:string,date_end:string,islands:array<int,array<string,mixed>>}
	 */
	private function readAppezzamenti()
	{
		$res = array('date_start' => '', 'date_end' => '', 'islands' => array());
		$islands = array();
		foreach ($this->records('appezzamenti', array($this, 'isNumberedRow')) as $r) {
			$island = self::cell($r, 40, 150, 0);
			$c = self::cultivation($r);
			if (!isset($islands[$island])) {
				$islands[$island] = array('id' => $island, 'fields' => array());
			}
			$islands[$island]['fields'][] = array('id' => self::cell($r, 150, 230, 0), 'cultivations' => array($c));
			if ($c['date_start'] !== '' && ($res['date_start'] === '' || $c['date_start'] < $res['date_start'])) {
				$res['date_start'] = $c['date_start'];
			}
			if ($c['date_end'] !== '' && $c['date_end'] > $res['date_end']) {
				$res['date_end'] = $c['date_end'];
			}
		}
		$res['islands'] = array_values($islands);
		return $res;
	}

	/**
	 * Cultivation plan by cadastral parcel: cultivations added to the parcels
	 *
	 * @param	array<string,array<string,mixed>>	$parcels	Parcels by id
	 * @return	void
	 */
	private function readPcgParticelle(&$parcels)
	{
		global $langs;

		$missing = array();
		foreach ($this->records('pcgparticelle', array($this, 'isNumberedRow')) as $r) {
			// Sheet above section and sub below parcel, in two columns
			$id = self::parcelKey(self::cell($r, 140, 190, 0), self::cell($r, 190, 215, 1), self::cell($r, 215, 232, 1), self::cell($r, 190, 215, -1), self::cell($r, 215, 232, -1));
			if (!isset($parcels[$id])) {
				$missing[$id] = str_replace('|', ' ', $id);
				continue;
			}
			$parcels[$id]['cultivations'][] = self::cultivation($r);
		}
		if ($missing) {
			$this->warnings[] = $langs->transnoentitiesnoconv('DossierImportPdfParcelUnknown', implode(', ', $missing));
		}
	}

	/**
	 * @return	array<int,array<string,mixed>>
	 */
	private function readAllevamenti()
	{
		$res = array();
		foreach ($this->records('zootecnia', array($this, 'isNumberedRow')) as $r) {
			$heads = null;
			foreach (self::col($r, 690, 800) as $t) {
				if (preg_match_all('/\d+/', $t, $m)) {
					$heads = (int) $heads + (int) array_sum($m[0]);
				}
			}
			$res[] = array(
				'bdn_code' => self::cell($r, 30, 78, 0),
				'address' => self::cell($r, 78, 200),
				'town' => self::comune(''),
				'species' => self::cell($r, 200, 255),
				'breeding_type' => self::cell($r, 420, 485),
				'breeding_mode' => self::cell($r, 255, 310),
				'production_type' => self::cell($r, 310, 375),
				'milk' => self::cell($r, 375, 420),
				'date_start' => self::isoDate(self::cell($r, 485, 523)),
				'date_end' => self::isoDate(self::cell($r, 523, 555)),
				'holder' => self::cell($r, 555, 605),
				'owner' => '',
				'date_bdn' => self::isoDate(self::cell($r, 605, 690)),
				'organic' => self::cell($r, 800, 900) === 'SI',
				'heads' => $heads,
			);
		}
		return $res;
	}

	/**
	 * @return	array<int,array<string,mixed>>
	 */
	private function readFabbricati()
	{
		$res = array();
		$num = function ($s) {
			$s = str_replace(',', '.', trim($s));
			return is_numeric($s) ? (float) $s : null;
		};
		foreach ($this->records('fabbricati', array($this, 'isNumberedRow')) as $r) {
			$seats = self::cell($r, 615, 700);
			$res[] = array(
				'comune' => self::comune(self::cell($r, 24, 150, 0)),
				'section' => self::cell($r, 150, 175, 0),
				'sheet' => self::cell($r, 175, 198, 0),
				'parcel' => self::cell($r, 198, 224, 0),
				'sub' => self::cell($r, 224, 270, 0),
				'use' => '',
				'conduction' => self::cell($r, 270, 345),
				'date_start' => self::isoDate(self::cell($r, 345, 395)),
				'date_end' => self::isoDate(self::cell($r, 395, 450)),
				'surface' => $num(self::cell($r, 450, 490)),
				'surface_covered' => $num(self::cell($r, 490, 530)),
				'surface_uncovered' => $num(self::cell($r, 530, 570)),
				'volume' => $num(self::cell($r, 570, 615)),
				'seats' => is_numeric($seats) ? (int) $seats : null,
			);
		}
		return $res;
	}

	/**
	 * @return	array<int,array<string,string>>
	 */
	private function readOrganismi()
	{
		$res = array();
		foreach ($this->records('legami', array($this, 'isNumberedRow')) as $r) {
			$res[] = array(
				'cf' => self::cell($r, 30, 180),
				'name' => self::cell($r, 180, 385),
				'type' => self::cell($r, 385, 605),
				'date_start' => self::isoDate(self::cell($r, 780, 900)),
			);
		}
		return $res;
	}

	/**
	 * @return	array<int,array<string,string>>
	 */
	private function readDocumenti()
	{
		$res = array();
		foreach ($this->records('documenti', array($this, 'isNumberedRow')) as $r) {
			$res[] = array(
				'section' => self::cell($r, 24, 235),
				'type' => self::cell($r, 235, 525),
				'protocol' => self::cell($r, 525, 645),
				'date_signature' => self::isoDate(self::cell($r, 645, 715)),
				'date_start' => self::isoDate(self::cell($r, 715, 785)),
				'date_end' => self::isoDate(self::cell($r, 785, 900)),
			);
		}
		return $res;
	}

	/**
	 * @return	array<int,array<string,string>>
	 */
	private function readConti()
	{
		$res = array();
		foreach ($this->records('conti', array($this, 'isNumberedRow')) as $r) {
			$res[] = array(
				'iban' => str_replace(' ', '', self::cell($r, 24, 195)),
				'bank' => self::cell($r, 278, 510),
				'agency' => self::cell($r, 510, 785),
			);
		}
		return $res;
	}
}
