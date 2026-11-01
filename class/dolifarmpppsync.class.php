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
 * \file        class/dolifarmpppsync.class.php
 * \ingroup     dolifarm
 * \brief       Catalogue of the plant protection products of the Italian Ministry of Health (open data, weekly)
 *
 * Source: dataset "fitosanitari" of www.dati.salute.gov.it (Italian Open Data Licence v2.0). The name of the
 * file changes with its date (PROD_FTS_6_YYYYMMDD.csv): the link of the latest file is read from the page of
 * the dataset. The file has no uses, doses or labels: only registration, holder, dates, substances and
 * administrative status. The agrodrugs with the same registration number get the status and the dates.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

/**
 * Sync of the catalogue of the plant protection products
 */
class DoliFarmPppSync
{
	/**
	 * @var DoliDB	Database handler
	 */
	public $db;

	/**
	 * @var string	Last error
	 */
	public $error = '';

	/**
	 * @var string	Output of the scheduled job
	 */
	public $output = '';

	/**
	 * @var int		Rows of the file read
	 */
	public $nbread = 0;

	/**
	 * @var int		Rows created in the catalogue
	 */
	public $nbcreated = 0;

	/**
	 * @var int		Rows changed in the catalogue
	 */
	public $nbupdated = 0;

	/**
	 * @var int		Agrodrugs updated
	 */
	public $nbagrodrugs = 0;

	const DATASET_URL = 'https://www.dati.salute.gov.it/it/dataset/fitosanitari/';
	const SITE_URL = 'https://www.dati.salute.gov.it';
	const BATCH = 200;

	/**
	 * @var string[]	Columns of the file that must be there
	 */
	public static $requiredColumns = array('num_registrazione', 'denominazione_prodotto', 'ragione_sociale', 'data_registrazione', 'data_scadenza_autorizzazione', 'sostanze_attive', 'stato_amministrativo');

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
	 * Scheduled job: update the catalogue when the Ministry publishes a new file
	 *
	 * @return	int		0 if OK, <>0 if KO (convention of the scheduled jobs)
	 */
	public function cronSync()
	{
		$result = $this->sync(false);
		if ($result < 0) {
			$this->output = $this->error;
			return 1;
		}
		$this->output = $this->getSummary();
		return 0;
	}

	/**
	 * Summary of the last run
	 *
	 * @return	string
	 */
	public function getSummary()
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		return $langs->transnoentities('PppSyncSummary', $this->nbread, $this->nbcreated, $this->nbupdated, $this->nbagrodrugs);
	}

	/**
	 * Download the latest file of the dataset and update the catalogue and the agrodrugs
	 *
	 * @param	bool	$force		Also when the file is the same of the last sync
	 * @return	int					Return integer <0 if KO, 0 if the file is the same, >0 if OK
	 */
	public function sync($force = false)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		$this->error = '';
		$url = $this->getLatestFileUrl();
		if ($url === '') {
			return -1;
		}
		$file = basename((string) parse_url($url, PHP_URL_PATH));
		if (!$force && $file === getDolGlobalString('DOLIFARM_PPP_FILE')) {
			dol_syslog(__METHOD__.' file '.$file.' already imported');
			// The status of the agrodrugs depends also on the date: a product can expire between two files
			$this->updateAgroDrugs();
			return 0;
		}
		$res = getURLContent($url, 'GET', '', 1, array(), array('https'), 0, -1, 10, 300);
		if (!empty($res['curl_error_no']) || (int) $res['http_code'] != 200 || $res['content'] === '') {
			$this->error = $langs->trans('ErrorPppDownload', $file, !empty($res['curl_error_msg']) ? $res['curl_error_msg'] : 'HTTP '.$res['http_code']);
			return -1;
		}
		return $this->importContent($res['content'], $file);
	}

	/**
	 * Link of the latest CSV file, read from the page of the dataset
	 *
	 * @return	string		URL, '' if KO
	 */
	public function getLatestFileUrl()
	{
		global $langs;

		$page = getDolGlobalString('DOLIFARM_PPP_DATASET_URL', self::DATASET_URL);
		$res = getURLContent($page, 'GET', '', 1, array(), array('https'), 0, -1, 10, 60);
		if (!empty($res['curl_error_no']) || (int) $res['http_code'] != 200) {
			$this->error = $langs->trans('ErrorPppDownload', $page, !empty($res['curl_error_msg']) ? $res['curl_error_msg'] : 'HTTP '.$res['http_code']);
			return '';
		}
		if (!preg_match_all('/href="([^"]*PROD_FTS_6_(\d{8})\.csv)"/i', (string) $res['content'], $matches, PREG_SET_ORDER)) {
			$this->error = $langs->trans('ErrorPppNoFile', $page);
			return '';
		}
		usort($matches, function ($a, $b) {
			return strcmp($b[2], $a[2]);
		});
		$href = html_entity_decode($matches[0][1]);
		if (str_starts_with($href, '/')) {
			$href = self::SITE_URL.$href;
		}
		if (!str_starts_with($href, 'https://')) {
			$this->error = $langs->trans('ErrorPppNoFile', $page);
			return '';
		}
		return $href;
	}

	/**
	 * Import the content of a file of the dataset (downloaded or uploaded by the administrator)
	 *
	 * @param	string	$content	Content of the CSV file
	 * @param	string	$file		Name of the file
	 * @return	int					Return integer <0 if KO, >0 if OK
	 */
	public function importContent($content, $file)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		// The first import writes about 18000 rows (about one minute)
		if (function_exists('set_time_limit')) {
			set_time_limit(600);
		}
		$this->nbread = 0;
		$this->nbcreated = 0;
		$this->nbupdated = 0;
		if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
			$content = substr($content, 3);
		}
		if (!utf8_check($content)) {
			$content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
		}
		$in = fopen('php://temp', 'r+');
		fwrite($in, $content);
		unset($content);
		rewind($in);
		$header = fgetcsv($in, 0, ';', '"', '\\');
		$header = is_array($header) ? array_map(function ($name) {
			return strtolower(trim((string) $name));
		}, $header) : array();
		$missing = array_diff(self::$requiredColumns, $header);
		if (count($missing)) {
			fclose($in);
			$this->error = $langs->trans('ErrorPppColumns', implode(', ', $missing));
			return -1;
		}

		// Hash of the rows already there: only new or changed rows are written
		$known = array();
		$resql = $this->db->query("SELECT reg_key, row_hash FROM ".$this->db->prefix()."dolifarm_ppp_catalog");
		if (!$resql) {
			fclose($in);
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$known[$obj->reg_key] = (string) $obj->row_hash;
		}
		$this->db->free($resql);

		$this->db->begin();
		$inserts = array();
		$seen = array();
		while (($values = fgetcsv($in, 0, ';', '"', '\\')) !== false) {
			if ($values === array(null) || count($values) < count($header)) {
				continue;
			}
			$row = array_combine($header, array_slice($values, 0, count($header)));
			$key = self::regKey($row['num_registrazione']);
			if ($key === '' || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = 1;
			$this->nbread++;
			// The prefix changes when the reading of the values changes, so that the rows are written again
			$hash = md5('v2;'.implode(';', $values));
			if (isset($known[$key]) && $known[$key] === $hash) {
				continue;
			}
			$data = $this->rowValues($row, $hash, $file);
			if (isset($known[$key])) {
				$set = array();
				foreach ($data as $col => $value) {
					$set[] = $col.' = '.$value;
				}
				if (!$this->db->query("UPDATE ".$this->db->prefix()."dolifarm_ppp_catalog SET ".implode(', ', $set)." WHERE reg_key = '".$this->db->escape($key)."'")) {
					return $this->fail($in);
				}
				$this->nbupdated++;
			} else {
				$inserts[] = '('."'".$this->db->escape($key)."', ".implode(', ', $data).')';
				if (count($inserts) >= self::BATCH && !$this->flush($inserts)) {
					return $this->fail($in);
				}
			}
		}
		fclose($in);
		if (count($inserts) && !$this->flush($inserts)) {
			return $this->fail(null);
		}
		if ($this->nbread == 0) {
			$this->db->rollback();
			$this->error = $langs->trans('ErrorPppColumns', 'num_registrazione');
			return -1;
		}
		dolibarr_set_const($this->db, 'DOLIFARM_PPP_FILE', dol_trunc($file, 64, 'right', 'UTF-8', 1), 'chaine', 0, 'Last file of the catalogue of plant protection products', 0);
		dolibarr_set_const($this->db, 'DOLIFARM_PPP_SYNC_DATE', (string) dol_now(), 'chaine', 0, 'Date of the last sync of the catalogue', 0);
		$this->db->commit();
		$this->updateAgroDrugs();
		dol_syslog(__METHOD__.' file='.$file.' read='.$this->nbread.' created='.$this->nbcreated.' updated='.$this->nbupdated.' agrodrugs='.$this->nbagrodrugs, LOG_INFO);
		return 1;
	}

	/**
	 * Repair characters encoded twice in UTF-8, as found in the file of the Ministry (Ö written as Ã + U+0096).
	 * Only a pair of characters U+0080-U+00FF whose bytes form one valid UTF-8 character of two bytes is replaced:
	 * ordinary accented text (à, è, ...) is not changed.
	 *
	 * @param	string	$value	Value read from the file
	 * @return	string			Repaired value
	 */
	public static function fixDoubleUtf8($value)
	{
		if (!str_contains($value, "\xC3") && !str_contains($value, "\xC2")) {
			return $value;
		}
		$fixed = preg_replace_callback('/[\xC2\xC3][\x80-\xBF][\xC2\xC3][\x80-\xBF]/', function ($m) {
			$b1 = ((ord($m[0][0]) & 0x03) << 6) | (ord($m[0][1]) & 0x3F);
			$b2 = ((ord($m[0][2]) & 0x03) << 6) | (ord($m[0][3]) & 0x3F);
			return ($b1 >= 0xC2 && $b1 <= 0xDF && $b2 >= 0x80 && $b2 <= 0xBF) ? chr($b1).chr($b2) : $m[0];
		}, $value);
		return is_string($fixed) ? $fixed : $value;
	}

	/**
	 * Values of a row for the SQL, in the order of the columns of insertColumns()
	 *
	 * @param	array<string,string>	$row	Row of the file
	 * @param	string					$hash	Hash of the row
	 * @param	string					$file	Name of the file
	 * @return	array<string,string>			Column => quoted value
	 */
	private function rowValues($row, $hash, $file)
	{
		$get = function ($name) use ($row) {
			$value = isset($row[$name]) ? trim(self::fixDoubleUtf8((string) $row[$name])) : '';
			return ($value === '-' ? '' : $value);
		};
		return array(
			'reg_number' => $this->quote($get('num_registrazione'), 16),
			'label' => $this->quote($get('denominazione_prodotto'), 255),
			'holder' => $this->quote($get('ragione_sociale'), 255),
			'date_registration' => $this->quoteDate($get('data_registrazione')),
			'date_expiry' => $this->quoteDate($get('data_scadenza_autorizzazione')),
			'hazard' => $this->quote($get('indicazioni_di_pericolo'), 0),
			'activity' => $this->quote($get('attivita'), 128),
			'formulation_code' => $this->quote($get('codice_formulazione'), 16),
			'formulation' => $this->quote($get('descrizione_formulazione'), 128),
			'substances' => $this->quote($get('sostanze_attive'), 0),
			'contents' => $this->quote($get('contenuto_per_100g_di_prodotto'), 0),
			'parallel_import' => (strtoupper($get('importazione_parallela')) == 'SI' ? '1' : '0'),
			'admin_status' => $this->quote($get('stato_amministrativo'), 128),
			'revocation_reason' => $this->quote($get('motivo_della revoca'), 255),
			'date_revocation_decree' => $this->quoteDate($get('data_decreto_revoca')),
			'date_revocation' => $this->quoteDate($get('data_decorrenza_revoca')),
			'row_hash' => "'".$this->db->escape($hash)."'",
			'source_file' => $this->quote($file, 64),
		);
	}

	/**
	 * Write the pending new rows
	 *
	 * @param	string[]	$inserts	Values of the rows, emptied
	 * @return	bool
	 */
	private function flush(&$inserts)
	{
		$cols = 'reg_key, reg_number, label, holder, date_registration, date_expiry, hazard, activity, formulation_code, formulation, substances, contents, parallel_import, admin_status, revocation_reason, date_revocation_decree, date_revocation, row_hash, source_file';
		$ok = $this->db->query("INSERT INTO ".$this->db->prefix()."dolifarm_ppp_catalog (".$cols.") VALUES ".implode(', ', $inserts));
		if ($ok) {
			$this->nbcreated += count($inserts);
		}
		$inserts = array();
		return (bool) $ok;
	}

	/**
	 * Rollback after a SQL error
	 *
	 * @param	resource|null	$in		File to close
	 * @return	int						-1
	 */
	private function fail($in)
	{
		$this->error = $this->db->lasterror();
		if ($in) {
			fclose($in);
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Copy status and dates of the catalogue on the agrodrugs with the same registration number. Empty fields of the
	 * agrodrug (holder, substances, formulation...) are filled; the values written by the user are kept.
	 *
	 * @return	int		Number of agrodrugs updated, <0 if KO
	 */
	public function updateAgroDrugs()
	{
		$this->nbagrodrugs = 0;
		$resql = $this->db->query("SELECT rowid, reg_number FROM ".$this->db->prefix()."dolifarm_agrodrug WHERE reg_number IS NOT NULL AND reg_number <> ''");
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$agrodrugs = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$key = self::regKey($obj->reg_number);
			if ($key !== '') {
				$agrodrugs[(int) $obj->rowid] = $key;
			}
		}
		$this->db->free($resql);
		foreach ($agrodrugs as $id => $key) {
			$sql = self::agroDrugUpdateSql($this->db, $id, $key);
			if ($sql !== '' && $this->db->query($sql)) {
				$this->nbagrodrugs++;
			}
		}
		return $this->nbagrodrugs;
	}

	/**
	 * SQL that copies the catalogue on one agrodrug
	 *
	 * @param	DoliDB	$db		Database handler
	 * @param	int		$id		Id of the agrodrug
	 * @param	string	$key	Registration key (regKey())
	 * @return	string			SQL, '' if the product is not in the catalogue
	 */
	public static function agroDrugUpdateSql($db, $id, $key)
	{
		$row = self::fetchCatalog($db, $key);
		if (!$row) {
			return '';
		}
		$p = $db->prefix();
		$quote = function ($value, $len) use ($db) {
			return ($value === null || $value === '' ? 'NULL' : "'".$db->escape(dol_trunc((string) $value, $len, 'right', 'UTF-8', 1))."'");
		};
		$date = function ($value) use ($db) {
			return ($value === null || $value === '' ? 'NULL' : "'".$db->escape(substr((string) $value, 0, 10))."'");
		};
		$sql = "UPDATE ".$p."dolifarm_agrodrug SET";
		$sql .= " admin_status = ".$quote($row->admin_status, 128);
		$sql .= ", date_revocation = ".$date($row->date_revocation);
		$sql .= ", auth_expiration_date = ".($row->date_expiry ? $date($row->date_expiry) : 'auth_expiration_date');
		$sql .= ", date_catalog_sync = '".$db->idate(dol_now())."'";
		// Empty fields only
		foreach (array('reg_date' => $date($row->date_registration), 'manufacturer' => $quote($row->holder, 255), 'active_substance' => $quote(str_replace('|', ' + ', (string) $row->substances), 65000),
			'content_percentage' => $quote(str_replace('|', ' + ', (string) $row->contents), 64), 'formulation' => $quote($row->formulation, 128), 'hazard_classification' => $quote($row->hazard, 128)) as $col => $value) {
			if ($value === 'NULL') {
				continue;
			}
			$sql .= ", ".$col." = (CASE WHEN ".$col." IS NULL".($col == 'reg_date' ? '' : " OR ".$col." = ''")." THEN ".$value." ELSE ".$col." END)";
		}
		$sql .= " WHERE rowid = ".((int) $id);
		return $sql;
	}

	/**
	 * Row of the catalogue for a registration number
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	string	$regnumber	Registration number (any format: 010573, 10573, "Reg. n. 10573")
	 * @return	stdClass|null
	 */
	public static function fetchCatalog($db, $regnumber)
	{
		$key = self::regKey($regnumber);
		if ($key === '') {
			return null;
		}
		$resql = $db->query("SELECT * FROM ".$db->prefix()."dolifarm_ppp_catalog WHERE reg_key = '".$db->escape($key)."'");
		if (!$resql) {
			return null;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return $obj ?: null;
	}

	/**
	 * Key of a registration number: digits only, without leading zeros (the file has 010573, the SIAN 10573)
	 *
	 * @param	string|null		$regnumber	Registration number
	 * @return	string
	 */
	public static function regKey($regnumber)
	{
		$digits = preg_replace('/\D/', '', (string) $regnumber);
		$key = ltrim((string) $digits, '0');
		return ($key === '' ? '' : substr($key, 0, 16));
	}

	/**
	 * Class of an administrative status
	 *
	 * @param	string|null		$status		Status written by the Ministry
	 * @return	string						authorized, suspended, revoked, expired, '' if unknown or empty
	 */
	public static function statusClass($status)
	{
		$status = strtolower(trim((string) $status));
		if ($status === '') {
			return '';
		}
		if (str_starts_with($status, 'autorizzat') || str_starts_with($status, 'ri-registrat') || str_starts_with($status, 'riregistrat') || str_starts_with($status, 'rinnovat')) {
			return 'authorized';
		}
		if (str_starts_with($status, 'sospes')) {
			return 'suspended';
		}
		if (str_starts_with($status, 'revocat')) {
			return 'revoked';
		}
		if (str_starts_with($status, 'scadut')) {
			return 'expired';
		}
		return '';
	}

	/**
	 * Quote a string for the SQL, NULL if empty
	 *
	 * @param	string	$value	Value
	 * @param	int		$len	Maximum length, 0 for none
	 * @return	string
	 */
	private function quote($value, $len)
	{
		if ($value === '') {
			return 'NULL';
		}
		return "'".$this->db->escape($len > 0 ? dol_trunc($value, $len, 'right', 'UTF-8', 1) : $value)."'";
	}

	/**
	 * Quote a date dd/mm/yyyy for the SQL, NULL if empty or not valid
	 *
	 * @param	string	$value	Value
	 * @return	string
	 */
	private function quoteDate($value)
	{
		if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $reg) && checkdate((int) $reg[2], (int) $reg[1], (int) $reg[3])) {
			return "'".$reg[3].'-'.$reg[2].'-'.$reg[1]."'";
		}
		return 'NULL';
	}
}
