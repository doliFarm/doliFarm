<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/dolifarmsianreader.class.php
 * \ingroup     dolifarm
 * \brief       Reading of the uses of ONE plant protection product from the SIAN database, on request
 *
 * The public database of the plant protection products of the SIAN (www.sian.it/mimfFitoPub) has no API and no
 * declared licence: it is read like a browser does, only when a user asks it for one product, with a pause
 * between the requests (about 5 requests + 1 for each crop). The pages give crop, pest and pre-harvest interval,
 * not the dose (only in the label). The uses read are stored as proposals (llx_dolifarm_agrodrug_sianuse) that
 * the user confirms; SIAN codes are matched to EPPO codes with llx_dolifarm_sian_map.
 */

dol_include_once('/dolifarm/class/dolifarmpppsync.class.php');
dol_include_once('/dolifarm/lib/dolifarm_eppo.lib.php');

/**
 * Reader of the SIAN database of plant protection products
 */
class DoliFarmSianReader
{
	const BASE_URL = 'https://www.sian.it/mimfFitoPub/';

	/** Pause between two requests, in microseconds */
	const PAUSE = 300000;

	/** Maximum number of pages of a list (20 rows each) */
	const MAX_PAGES = 30;

	/** Days after which the list of the pests of the SIAN is read again */
	const PEST_LIST_DAYS = 90;

	/**
	 * @var DoliDB	Database handler
	 */
	public $db;

	/**
	 * @var string	Error message
	 */
	public $error = '';

	/**
	 * @var int	Number of requests sent
	 */
	public $nbrequests = 0;

	/**
	 * @var int	Number of crops read
	 */
	public $nbcrops = 0;

	/**
	 * @var int	Number of proposals stored
	 */
	public $nbproposals = 0;

	/**
	 * @var string	File of the label saved, '' if none
	 */
	public $labelfile = '';

	/**
	 * @var string	Date of the label saved (dd/mm/yyyy), '' if none
	 */
	public $labeldate = '';

	/**
	 * @var CurlHandle|null	Handle kept for the whole session (cookies in memory)
	 */
	private $ch = null;

	/**
	 * @var array<string,string>	Normalized "class name" of a pest => SIAN code
	 */
	private $pestcodes = array();

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Read the uses of an agrodrug from the SIAN and store them as proposals
	 *
	 * @param	DoliFarmAgroDrug	$agrodrug	Agrodrug with its registration number
	 * @param	User				$user		User
	 * @param	string				$labeldir	Directory where to save the latest label, '' to not download it
	 * @return	int								Number of proposals, <0 if error
	 */
	public function read($agrodrug, User $user, $labeldir = '')
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		$regnumber = DoliFarmPppSync::regKey((string) $agrodrug->reg_number);
		if ($regnumber === '') {
			$this->error = $langs->trans('ErrorSianNoRegNumber');
			return -1;
		}
		if (!function_exists('curl_init')) {
			$this->error = $langs->trans('ErrorEppoNoCurl');
			return -1;
		}
		set_time_limit(600);
		$this->ch = curl_init();
		$result = $this->readProduct($agrodrug, $user, $regnumber, $labeldir);
		$this->ch = null;
		dol_syslog(__METHOD__.' reg='.$regnumber.' requests='.$this->nbrequests.' crops='.$this->nbcrops.' proposals='.$this->nbproposals.' result='.$result, LOG_INFO);
		return $result;
	}

	/**
	 * Steps of the reading, with the session open
	 *
	 * @param	DoliFarmAgroDrug	$agrodrug	Agrodrug
	 * @param	User				$user		User
	 * @param	string				$regnumber	Registration number without leading zeros
	 * @param	string				$labeldir	Directory of the label, '' for none
	 * @return	int								Number of proposals, <0 if error
	 */
	private function readProduct($agrodrug, User $user, $regnumber, $labeldir)
	{
		global $langs;

		if ($this->loadPestCodes() < 0) {
			return -1;
		}
		if ($this->request('home.get') === null) {
			return -1;
		}
		// Search by registration number, then the detail of the product with this number
		$page = $this->request('gestioneRicercaInizialeFito.do', array(
			array('ricerca.numRegistro', $regnumber), array('ricerca.tipoRicerca', '0'),
			array('_ricerca.tipologia', 'on'), array('_ricerca.usoProfRicIniz', 'on'), array('ricercIniz', 'Cerca'),
		));
		if ($page === null) {
			return -1;
		}
		$button = '';
		foreach ($this->tableRows($page) as $row) {
			if (preg_match('/name="(dettaglio\d+)"/', $row, $m) && preg_match('/<td[^>]*>\s*0*'.preg_quote($regnumber, '/').'\s*<\/td>/', $row)) {
				$button = $m[1];
				break;
			}
		}
		if ($button === '') {
			$this->error = $langs->trans('ErrorSianNotFound', $regnumber);
			return -1;
		}
		$form = $this->parseForm($page);
		if ($this->request($form['action'], array_merge($form['fields'], array(array($button, '')))) === null) {
			return -1;
		}

		// Uses: pages of 20 crops; for each crop the list of its pests
		$uses = array();
		$page = $this->request('impieghi.get');
		if ($page === null) {
			return -1;
		}
		$nbpages = max(1, count($this->pageButtons($page)));
		for ($p = 1; $p <= min($nbpages, self::MAX_PAGES); $p++) {
			if ($p > 1) {
				$form = $this->parseForm($page);
				$page = $this->request($form['action'], array_merge($form['fields'], array(array('butPagina', ' '.$p.' '))));
				if ($page === null) {
					return -1;
				}
			}
			$form = $this->parseForm($page);
			foreach ($this->parseUses($page) as $use) {
				$pestpage = $this->request($form['action'], array_merge($form['fields'], array(array($use['button'], ''))));
				if ($pestpage === null) {
					return -1;
				}
				$use['pests'] = $this->parsePests($pestpage);
				$uses[] = $use;
				$this->nbcrops++;
			}
		}
		if (empty($uses)) {
			$this->error = $langs->trans('ErrorSianNoUses', $regnumber);
			return -1;
		}

		if ($labeldir !== '') {
			$this->labeldate = $this->downloadLabel($regnumber, $labeldir);
		}
		return $this->storeProposals((int) $agrodrug->id, $uses, $user);
	}

	/**
	 * Download the latest label of the product (session on the product)
	 *
	 * @param	string	$regnumber	Registration number
	 * @param	string	$dir		Directory where to save it
	 * @return	string				Date of the label (dd/mm/yyyy), '' if not downloaded
	 */
	private function downloadLabel($regnumber, $dir)
	{
		$page = $this->request('storicoEtichette.get');
		if ($page === null || !preg_match('/name="scaricaEtichettaStorico0"/', $page)) {
			return '';
		}
		$date = '';
		if (preg_match('/name="data"[^>]*value="(\d{2})\/(\d{2})\/(\d{4})"/', $page, $m)) {
			$date = $m[1].'/'.$m[2].'/'.$m[3];
		}
		$form = $this->parseForm($page);
		$type = '';
		$pdf = $this->request($form['action'], array_merge($form['fields'], array(array('scaricaEtichettaStorico0', ''))), true, $type);
		if ($pdf === null || strpos($type, 'pdf') === false || substr($pdf, 0, 5) !== '%PDF-') {
			return '';
		}
		if (dol_mkdir($dir) < 0) {
			return '';
		}
		$name = 'etichetta_SIAN_'.$regnumber.($date !== '' ? '_'.substr($date, 6, 4).substr($date, 3, 2).substr($date, 0, 2) : '').'.pdf';
		if (file_put_contents($dir.'/'.$name, $pdf) === false) {
			return '';
		}
		dolChmod($dir.'/'.$name);
		$this->labelfile = $name;
		return $date;
	}

	/**
	 * Store the uses read as proposals of the agrodrug (the previous proposals are replaced)
	 *
	 * @param	int								$agrodrugid		Agrodrug
	 * @param	array<int,array<string,mixed>>	$uses			Uses read
	 * @param	User							$user			User
	 * @return	int												Number of proposals, <0 if error
	 */
	private function storeProposals($agrodrugid, $uses, User $user)
	{
		$prefix = $this->db->prefix();
		$now = $this->db->idate(dol_now());
		$this->db->begin();
		if (!$this->db->query("DELETE FROM ".$prefix."dolifarm_agrodrug_sianuse WHERE fk_agrodrug = ".((int) $agrodrugid))) {
			return $this->dbFail();
		}
		foreach ($uses as $use) {
			if (self::saveMap($this->db, 'crop', $use['crop_code'], $use['crop_label'], self::cropEppoProposal($this->db, $use['crop_label']), 0, false) < 0) {
				return $this->dbFail();
			}
			$pests = count($use['pests']) ? $use['pests'] : array(array('class' => '', 'label' => '', 'code' => ''));
			foreach ($pests as $pest) {
				$code = $pest['code'];
				if ($pest['label'] !== '' && $code === '') {
					// Pest not in the list of the SIAN: a code of ours, so that the user can choose its EPPO code once
					$code = 'N'.substr(md5(self::normalize($pest['class'].$pest['label'])), 0, 15);
					if (self::saveMap($this->db, 'pest', $code, $pest['class'].' - '.$pest['label'], '', 0, false) < 0) {
						return $this->dbFail();
					}
				}
				$sql = "INSERT INTO ".$prefix."dolifarm_agrodrug_sianuse (fk_agrodrug, crop_sian, crop_class, crop_label, pest_class, pest_label, pest_sian, safety_days, safety_days_greenhouse, safety_text, date_read, fk_user) VALUES (";
				$sql .= ((int) $agrodrugid).", '".$this->db->escape(dol_trunc($use['crop_code'], 16, 'right', 'UTF-8', 1))."', '".$this->db->escape(dol_trunc($use['crop_class'], 32, 'right', 'UTF-8', 1))."'";
				$sql .= ", '".$this->db->escape(dol_trunc($use['crop_label'], 250, 'right', 'UTF-8', 1))."', '".$this->db->escape(dol_trunc($pest['class'], 64, 'right', 'UTF-8', 1))."'";
				$sql .= ", '".$this->db->escape(dol_trunc($pest['label'], 250, 'right', 'UTF-8', 1))."', '".$this->db->escape($code)."'";
				$sql .= ", ".($use['safety_days'] === null ? 'NULL' : (int) $use['safety_days']).", ".($use['safety_days_greenhouse'] === null ? 'NULL' : (int) $use['safety_days_greenhouse']);
				$sql .= ", '".$this->db->escape(dol_trunc($use['safety_text'], 64, 'right', 'UTF-8', 1))."', '".$now."', ".((int) $user->id).")";
				if (!$this->db->query($sql)) {
					return $this->dbFail();
				}
				$this->nbproposals++;
			}
		}
		$this->db->commit();
		return $this->nbproposals;
	}

	/**
	 * Roll back after an error of the database
	 *
	 * @return	int		-1
	 */
	private function dbFail()
	{
		$this->error = $this->db->lasterror();
		$this->db->rollback();
		return -1;
	}

	/**
	 * Load the codes of the pests of the SIAN (list of the search page), read again every PEST_LIST_DAYS days.
	 * The SIAN code is an EPPO code for most pests: it becomes the proposed EPPO code when it exists in the EPPO data.
	 *
	 * @return	int		>0 if OK, <0 if error
	 */
	private function loadPestCodes()
	{
		$prefix = $this->db->prefix();
		$last = getDolGlobalInt('DOLIFARM_SIAN_PEST_LIST_DATE');
		if ($last > 0 && $last > dol_now() - self::PEST_LIST_DAYS * 86400) {
			$resql = $this->db->query("SELECT sian_code, sian_label FROM ".$prefix."dolifarm_sian_map WHERE map_type = 'pest'");
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$this->addPestCode($obj->sian_code, $obj->sian_label);
				}
				$this->db->free($resql);
			}
			if (count($this->pestcodes)) {
				return 1;
			}
		}
		$page = $this->request('ricercaInizialeFito.get');
		if ($page === null) {
			return -1;
		}
		if (!preg_match('/<select[^>]*name="ricerca\.avversitaRicIniz"[^>]*>(.*?)<\/select>/s', $page, $m)) {
			return 1;	// List not found: the pests stay without code and the user chooses them
		}
		preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>(.*?)<\/option>/s', $m[1], $options, PREG_SET_ORDER);
		$this->db->begin();
		foreach ($options as $option) {
			$code = strtoupper(trim(html_entity_decode($option[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			$label = trim(preg_replace('/\s+/', ' ', html_entity_decode($option[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			if ($code === '' || strlen($code) > 16) {
				continue;
			}
			$eppo = dolifarmEppoFetch($this->db, $code);
			if (self::saveMap($this->db, 'pest', $code, $label, $eppo ? $eppo->eppocode : '', 0, false) < 0) {
				return $this->dbFail();
			}
			$this->addPestCode($code, $label);
		}
		$this->db->commit();
		dolibarr_set_const($this->db, 'DOLIFARM_SIAN_PEST_LIST_DATE', (string) dol_now(), 'chaine', 0, 'Date of the list of the pests of the SIAN', 0);
		return 1;
	}

	/**
	 * Remember the code of a pest by its "class - name"; with the same name twice, the code that is an EPPO code wins
	 *
	 * @param	string	$code	SIAN code
	 * @param	string	$label	Class - name
	 * @return	void
	 */
	private function addPestCode($code, $label)
	{
		$key = self::normalize($label);
		if (!isset($this->pestcodes[$key]) || (!ctype_alpha(substr($this->pestcodes[$key], 0, 1)) && ctype_alpha(substr($code, 0, 1)))) {
			$this->pestcodes[$key] = $code;
		}
	}

	/**
	 * Rows of the uses of a page: crop, pre-harvest intervals and the button that opens its pests
	 *
	 * @param	string	$page	HTML
	 * @return	array<int,array<string,mixed>>
	 */
	private function parseUses($page)
	{
		$uses = array();
		foreach ($this->tableRows($page) as $row) {
			if (!preg_match('/name="(avversita\d+)"/', $row, $button)) {
				continue;
			}
			$hidden = $this->hiddenFields($row);
			$cells = $this->cells($row);
			$safety = isset($cells[1]) ? $cells[1] : '';
			$greenhouse = isset($cells[2]) ? $cells[2] : '';
			$uses[] = array(
				'button' => $button[1],
				'crop_code' => isset($hidden['codPianta']) ? strtoupper(trim($hidden['codPianta'])) : '',
				'crop_class' => isset($hidden['descrizioneCod']) ? trim($hidden['descrizioneCod']) : '',
				'crop_label' => isset($hidden['descrizionePianta']) ? trim($hidden['descrizionePianta']) : '',
				'safety_days' => (preg_match('/^\d+$/', $safety) ? (int) $safety : null),
				'safety_days_greenhouse' => (preg_match('/^\d+$/', $greenhouse) ? (int) $greenhouse : null),
				'safety_text' => (($safety !== '' && !preg_match('/^\d+$/', $safety)) ? $safety : ''),
			);
		}
		return array_filter($uses, function ($use) {
			return $use['crop_code'] !== '';
		});
	}

	/**
	 * Pests of a crop, with the following pages when there are more than 20
	 *
	 * @param	string	$page	HTML of the first page
	 * @return	array<int,array{class:string,label:string,code:string}>
	 */
	private function parsePests($page)
	{
		$pests = array();
		$nbpages = max(1, count($this->pageButtons($page)));
		for ($p = 1; $p <= min($nbpages, self::MAX_PAGES); $p++) {
			if ($p > 1) {
				$form = $this->parseForm($page);
				$page = $this->request($form['action'], array_merge($form['fields'], array(array('butPagina', ' '.$p.' '))));
				if ($page === null) {
					break;
				}
			}
			// Only the table of the pests, after its header "Classe avversità-Avversità"
			$start = strpos($page, 'Classe avversit');
			foreach ($this->tableRows($start === false ? '' : substr($page, $start)) as $row) {
				$cells = $this->cells($row);
				if (count($cells) != 1 || strpos($cells[0], '-') === false) {
					continue;
				}
				list($class, $label) = array_map('trim', explode('-', $cells[0], 2));
				$key = self::normalize($class.$label);
				$pests[$key] = array('class' => $class, 'label' => $label, 'code' => (isset($this->pestcodes[$key]) ? $this->pestcodes[$key] : ''));
			}
		}
		return array_values($pests);
	}

	/**
	 * Send a request in the session
	 *
	 * @param	string							$path		Path under BASE_URL
	 * @param	array<int,array{0:string,1:string}>|null	$post	Fields to post (repeated names allowed), null for GET
	 * @param	bool							$raw		Return the bytes without decoding (files)
	 * @param	string							$type		Content type of the answer
	 * @return	string|null								Content, null if error
	 */
	private function request($path, $post = null, $raw = false, &$type = '')
	{
		global $langs;

		if (!preg_match('/^[a-zA-Z]+\.(get|do)$/', $path)) {
			$this->error = $langs->trans('ErrorSianPage', $path);
			return null;
		}
		if ($this->nbrequests > 0) {
			usleep(self::PAUSE);
		}
		$this->nbrequests++;
		$ch = $this->ch;
		curl_setopt($ch, CURLOPT_URL, self::BASE_URL.$path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 90);
		curl_setopt($ch, CURLOPT_COOKIEFILE, '');	// cookies kept in memory for the session
		curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr doliFarm (on request of a user, one product)');
		if (getDolGlobalString('MAIN_CURL_DISABLE_VERIFYPEER')) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		if (getDolGlobalString('MAIN_PROXY_USE')) {
			curl_setopt($ch, CURLOPT_PROXY, getDolGlobalString('MAIN_PROXY_HOST').':'.getDolGlobalString('MAIN_PROXY_PORT'));
			if (getDolGlobalString('MAIN_PROXY_USER')) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, getDolGlobalString('MAIN_PROXY_USER').':'.getDolGlobalString('MAIN_PROXY_PASS'));
			}
		}
		if ($post !== null) {
			$body = array();
			foreach ($post as $field) {
				$body[] = rawurlencode($field[0]).'='.rawurlencode($field[1]);
			}
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $body));
		} else {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}
		$content = curl_exec($ch);
		$httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		dol_syslog(__METHOD__.' '.($post === null ? 'GET ' : 'POST ').$path.' http_code='.$httpcode);
		if ($content === false || $httpcode != 200) {
			$this->error = $langs->trans('ErrorSianPage', $path.' ('.($content === false ? curl_error($ch) : 'HTTP '.$httpcode).')');
			return null;
		}
		return $raw ? $content : self::toUtf8($content);
	}

	/**
	 * The pages are UTF-8 with some Windows-1252 characters (e.g. "Avversità"): those bytes are converted
	 *
	 * @param	string	$content	Bytes
	 * @return	string				UTF-8
	 */
	public static function toUtf8($content)
	{
		if (mb_check_encoding($content, 'UTF-8')) {
			return $content;
		}
		$fixed = preg_replace_callback('/[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF4][\x80-\xBF]{3}|([\x80-\xFF])/', function ($m) {
			return isset($m[1]) ? mb_convert_encoding($m[1], 'UTF-8', 'Windows-1252') : $m[0];
		}, $content);
		return is_string($fixed) ? $fixed : mb_scrub($content, 'UTF-8');
	}

	/**
	 * First form of a page: action and hidden fields, in their order (repeated names kept)
	 *
	 * @param	string	$page	HTML
	 * @return	array{action:string,fields:array<int,array{0:string,1:string}>}
	 */
	private function parseForm($page)
	{
		$form = array('action' => '', 'fields' => array());
		if (!preg_match('/<form[^>]*action="([^"]*)"[^>]*>(.*?)<\/form>/s', $page, $m)) {
			return $form;
		}
		$form['action'] = basename(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		preg_match_all('/<input([^>]*)>/s', $m[2], $inputs);
		foreach ($inputs[1] as $attributes) {
			$attr = $this->attributes($attributes);
			if (isset($attr['type'], $attr['name']) && strtolower($attr['type']) == 'hidden') {
				$form['fields'][] = array($attr['name'], isset($attr['value']) ? $attr['value'] : '');
			}
		}
		return $form;
	}

	/**
	 * Hidden fields of a fragment: name => value (last one wins)
	 *
	 * @param	string	$html	HTML
	 * @return	array<string,string>
	 */
	private function hiddenFields($html)
	{
		$fields = array();
		preg_match_all('/<input([^>]*)>/s', $html, $inputs);
		foreach ($inputs[1] as $attributes) {
			$attr = $this->attributes($attributes);
			if (isset($attr['type'], $attr['name']) && strtolower($attr['type']) == 'hidden') {
				$fields[$attr['name']] = isset($attr['value']) ? $attr['value'] : '';
			}
		}
		return $fields;
	}

	/**
	 * Attributes of a tag
	 *
	 * @param	string	$attributes		Text of the attributes
	 * @return	array<string,string>
	 */
	private function attributes($attributes)
	{
		$attr = array();
		preg_match_all('/([a-zA-Z_:.-]+)\s*=\s*"([^"]*)"/', $attributes, $pairs, PREG_SET_ORDER);
		foreach ($pairs as $pair) {
			$attr[strtolower($pair[1])] = html_entity_decode($pair[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		return $attr;
	}

	/**
	 * Rows (tr) of the tables of a page
	 *
	 * @param	string	$page	HTML
	 * @return	string[]
	 */
	private function tableRows($page)
	{
		preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $page, $rows);
		return $rows[1];
	}

	/**
	 * Text of the cells (td) of a row
	 *
	 * @param	string	$row	HTML of the row
	 * @return	string[]
	 */
	private function cells($row)
	{
		preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);
		return array_map(function ($cell) {
			return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		}, $cells[1]);
	}

	/**
	 * Buttons of the pages of a list
	 *
	 * @param	string	$page	HTML
	 * @return	string[]
	 */
	private function pageButtons($page)
	{
		preg_match_all('/name="butPagina"\s+value="\s*(\d+)\s*"/', $page, $m);
		return array_unique($m[1]);
	}

	/**
	 * Key to compare names: letters and digits only, upper case
	 *
	 * @param	string	$text	Text
	 * @return	string
	 */
	public static function normalize($text)
	{
		return strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', (string) $text));
	}

	/**
	 * EPPO code proposed for a crop of the SIAN: the confirmed correspondence, else the only active plant
	 * with this Italian name
	 *
	 * @param	DoliDB	$db		Database handler
	 * @param	string	$label	Name of the crop in the SIAN
	 * @return	string			EPPO code, '' if none or more than one
	 */
	public static function cropEppoProposal($db, $label)
	{
		$label = dol_strtolower(trim((string) $label));
		if ($label === '' || !dolifarmEppoDataAvailable()) {
			return '';
		}
		// The name, "<name> comune" (fagiolo comune) and the singular of a plural (cavoli => cavolo, pere => pera)
		$names = array($label, $label.' comune');
		if (preg_match('/^[a-z ]+[ie]$/', $label)) {
			$singular = substr($label, 0, -1).(substr($label, -1) == 'i' ? 'o' : 'a');
			$names[] = $singular;
			$names[] = $singular.' comune';
		}
		foreach ($names as $name) {
			$sql = "SELECT DISTINCT c.eppocode FROM ".$db->prefix()."dolifarm_eppo_name as n";
			$sql .= " INNER JOIN ".$db->prefix()."dolifarm_eppo_code as c ON c.eppocode = n.eppocode";
			$sql .= " WHERE n.lang = 'it' AND n.fullname = '".$db->escape($name)."' AND c.is_active = 1 AND c.datatype = 'PFL'";
			$sql .= $db->plimit(2);
			$codes = array();
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$codes[] = $obj->eppocode;
				}
				$db->free($resql);
			}
			if (count($codes) == 1) {
				return $codes[0];
			}
			if (count($codes) > 1) {
				return '';
			}
		}
		return '';
	}

	/**
	 * Create or update a correspondence SIAN => EPPO. A confirmed EPPO code is not replaced by a proposal.
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	string	$type		crop or pest
	 * @param	string	$code		SIAN code
	 * @param	string	$label		SIAN label
	 * @param	string	$eppo		EPPO code ('' for none)
	 * @param	int		$confirmed	1 when chosen by a user
	 * @param	bool	$force		Replace also a confirmed code (choice of a user)
	 * @return	int					>0 if OK, <0 if error
	 */
	public static function saveMap($db, $type, $code, $label, $eppo, $confirmed, $force)
	{
		$code = dol_trunc(strtoupper(trim((string) $code)), 16, 'right', 'UTF-8', 1);
		if ($code === '' || !in_array($type, array('crop', 'pest'))) {
			return 1;
		}
		$eppo = strtoupper(trim((string) $eppo));
		$label = dol_trunc((string) $label, 250, 'right', 'UTF-8', 1);
		$prefix = $db->prefix();
		$resql = $db->query("SELECT rowid, eppo_code, confirmed FROM ".$prefix."dolifarm_sian_map WHERE map_type = '".$db->escape($type)."' AND sian_code = '".$db->escape($code)."'");
		if (!$resql) {
			return -1;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (!$obj) {
			$sql = "INSERT INTO ".$prefix."dolifarm_sian_map (map_type, sian_code, sian_label, eppo_code, confirmed) VALUES ('".$db->escape($type)."', '".$db->escape($code)."', '".$db->escape($label)."', ".($eppo === '' ? 'NULL' : "'".$db->escape($eppo)."'").", ".((int) $confirmed).")";
		} elseif ($force || empty($obj->confirmed)) {
			$sql = "UPDATE ".$prefix."dolifarm_sian_map SET sian_label = '".$db->escape($label)."', eppo_code = ".($eppo === '' ? 'NULL' : "'".$db->escape($eppo)."'").", confirmed = ".((int) $confirmed)." WHERE rowid = ".((int) $obj->rowid);
		} else {
			$sql = "UPDATE ".$prefix."dolifarm_sian_map SET sian_label = '".$db->escape($label)."' WHERE rowid = ".((int) $obj->rowid);
		}
		return $db->query($sql) ? 1 : -1;
	}

	/**
	 * Proposals of an agrodrug, with the EPPO codes of the correspondences
	 *
	 * @param	DoliDB	$db				Database handler
	 * @param	int		$agrodrugid		Agrodrug
	 * @return	array<int,stdClass>		rowid => proposal (crop_eppo, crop_confirmed, pest_eppo, pest_confirmed)
	 */
	public static function fetchProposals($db, $agrodrugid)
	{
		$prefix = $db->prefix();
		$sql = "SELECT s.rowid, s.crop_sian, s.crop_class, s.crop_label, s.pest_class, s.pest_label, s.pest_sian, s.safety_days, s.safety_days_greenhouse, s.safety_text, s.date_read,";
		$sql .= " mc.eppo_code as crop_eppo, mc.confirmed as crop_confirmed, mp.eppo_code as pest_eppo, mp.confirmed as pest_confirmed";
		$sql .= " FROM ".$prefix."dolifarm_agrodrug_sianuse as s";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_sian_map as mc ON mc.map_type = 'crop' AND mc.sian_code = s.crop_sian";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_sian_map as mp ON mp.map_type = 'pest' AND mp.sian_code = s.pest_sian";
		$sql .= " WHERE s.fk_agrodrug = ".((int) $agrodrugid);
		$sql .= " ORDER BY s.crop_label, s.pest_class, s.pest_label, s.rowid";
		$rows = array();
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$rows[(int) $obj->rowid] = $obj;
			}
			$db->free($resql);
		}
		return $rows;
	}
}
