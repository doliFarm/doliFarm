<?php
/* Copyright (C) 2026      SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/dolifarmepposync.class.php
 * \ingroup     dolifarm
 * \brief       Update of the imported EPPO Codes with the EPPO REST API (codes created or changed since the last update)
 *
 * Only the endpoints of the EPPO Codes are used (taxons/list, taxon overview and names): no hosts, pests,
 * distribution or categorisation (EPPO Open Data Licence). Rate limit of the API: 2000 requests per IP
 * in 10 seconds, HTTP 429 beyond; the calls are sequential and a 429 is retried after a pause.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * Update of the EPPO Codes from the EPPO API
 */
class DoliFarmEppoSync
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
	 * @var int		Number of codes changed or created
	 */
	public $nbcodes = 0;

	/**
	 * @var int		Number of API calls
	 */
	public $nbcalls = 0;

	/**
	 * @var string[]	Languages of the names kept for the search (as in the import of the file)
	 */
	public $langs = array('la', 'it', 'en');

	const DEFAULT_URL = 'https://api.eppo.int/gd/v2';
	const PAGE_SIZE = 1000;
	const MAX_RETRY = 3;

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
	 * Scheduled job: update the EPPO Codes
	 *
	 * @return	int		0 if OK, <>0 if KO (convention of the scheduled jobs)
	 */
	public function cronSync()
	{
		$result = $this->sync();
		if ($result < 0) {
			$this->output = $this->error;
			return 1;
		}
		return 0;
	}

	/**
	 * Check the API key with a call that needs it
	 *
	 * @return	int		Return integer <0 if KO, >0 if OK
	 */
	public function checkKey()
	{
		$res = $this->call('/taxons/taxon/VITVI/overview');
		return is_array($res) ? 1 : -1;
	}

	/**
	 * Update the EPPO Codes created or changed since the last update (or since the date of the imported file)
	 *
	 * @param	int		$maxcalls	Maximum number of API calls (0 = setup DOLIFARM_EPPO_SYNC_MAX_CALLS, default 2000)
	 * @return	int					Return integer <0 if KO, number of codes updated if OK
	 */
	public function sync($maxcalls = 0)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		$this->error = '';
		$this->nbcodes = 0;
		$this->nbcalls = 0;
		if ($maxcalls <= 0) {
			$maxcalls = getDolGlobalInt('DOLIFARM_EPPO_SYNC_MAX_CALLS', 2000);
		}

		$since = getDolGlobalString('DOLIFARM_EPPO_SYNC_DATE', getDolGlobalString('DOLIFARM_EPPO_DATA_DATE'));
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
			$this->error = $langs->trans('ErrorEppoSyncNoData');
			return -1;
		}
		if (getDolGlobalString('DOLIFARM_EPPO_API_KEY') === '') {
			$this->error = $langs->trans('ErrorEppoNoApiKey');
			return -1;
		}
		// The day of the start: the changes of this day are read again at the next update (the update is idempotent)
		$today = dol_print_date(dol_now(), '%Y-%m-%d', 'gmt');
		dol_syslog(__METHOD__.' since='.$since);

		// Codes created or changed since the date
		$changed = array();
		$offset = 0;
		do {
			$res = $this->call('/taxons/list', array('createdFromDate' => $since, 'updatedFromDate' => $since, 'orderBy' => 'eppocode', 'limit' => self::PAGE_SIZE, 'offset' => $offset));
			if (!is_array($res)) {
				return -1;
			}
			foreach ((isset($res['data']) ? $res['data'] : array()) as $row) {
				if (!empty($row['eppocode'])) {
					$changed[strtoupper($row['eppocode'])] = $row;
				}
			}
			$total = isset($res['pagination']['total']) ? (int) $res['pagination']['total'] : 0;
			$offset += self::PAGE_SIZE;
		} while ($offset < $total);

		if (count($changed) + $this->nbcalls > $maxcalls) {
			$this->error = $langs->trans('ErrorEppoSyncTooManyCalls', count($changed), $maxcalls);
			return -1;
		}

		foreach ($changed as $code => $row) {
			if ($this->updateCode($code, $row) < 0) {
				return -1;
			}
			$this->nbcodes++;
		}

		dolibarr_set_const($this->db, 'DOLIFARM_EPPO_SYNC_DATE', $today, 'chaine', 0, 'Date of the last update of the EPPO Codes from the API', 0);
		$this->output = $langs->trans('EppoSyncDone', $this->nbcodes, $this->nbcalls);
		dol_syslog(__METHOD__.' done: '.$this->nbcodes.' codes, '.$this->nbcalls.' calls');
		return $this->nbcodes;
	}

	/**
	 * Update a code and its names from the API
	 *
	 * @param	string					$code	EPPO code
	 * @param	array<string,mixed>		$row	Row of taxons/list (is_active, replacedby, datatype, dates)
	 * @return	int								Return integer <0 if KO, >0 if OK
	 */
	private function updateCode($code, $row)
	{
		$names = $this->call('/taxons/taxon/'.rawurlencode($code).'/names');
		if (!is_array($names)) {
			return -1;
		}

		$prefname = '';
		$authority = '';
		$best = array('it' => '', 'en' => '');
		$namerows = array();
		foreach ($names as $name) {
			$lang = isset($name['lang_iso']) ? (string) $name['lang_iso'] : '';
			$fullname = isset($name['fullname']) ? trim((string) $name['fullname']) : '';
			if ($fullname === '' || empty($name['name_id']) || !in_array($lang, $this->langs)) {
				continue;
			}
			$preferred = !empty($name['preferred']);
			if ($preferred && $prefname === '') {
				$prefname = $fullname;
				$authority = isset($name['author']) ? trim((string) $name['author']) : '';
			}
			if (isset($best[$lang]) && ($best[$lang] === '' || dol_strlen($fullname) < dol_strlen($best[$lang]))) {
				$best[$lang] = $fullname;
			}
			$namerows[] = "(".((int) $name['name_id']).", '".$this->db->escape($code)."', '".$this->db->escape($lang)."', '".$this->db->escape(dol_substr($fullname, 0, 255))."', ".$this->quote(isset($name['author']) ? trim((string) $name['author']) : '').", ".($preferred ? 1 : 0).", NULL)";
		}

		$this->db->begin();
		$sql = "INSERT INTO ".$this->db->prefix()."dolifarm_eppo_code";
		$sql .= " (eppocode, datatype, is_active, prefname, authority, name_it, name_en, replacedby, date_eppo_creation, date_eppo_modification)";
		$sql .= " VALUES ('".$this->db->escape($code)."', ".$this->quote(isset($row['datatype']) ? substr((string) $row['datatype'], 0, 3) : '').", ".(empty($row['is_active']) ? 0 : 1);
		$sql .= ", ".$this->quote($prefname).", ".$this->quote($authority).", ".$this->quote($best['it']).", ".$this->quote($best['en']);
		$sql .= ", ".$this->quote(isset($row['replacedby']) ? strtoupper((string) $row['replacedby']) : '');
		$sql .= ", ".$this->quoteDate(isset($row['datecreate']) ? (string) $row['datecreate'] : '').", ".$this->quoteDate(isset($row['dateupdate']) ? (string) $row['dateupdate'] : '').")";
		$sql .= " ON DUPLICATE KEY UPDATE datatype = VALUES(datatype), is_active = VALUES(is_active), replacedby = VALUES(replacedby), date_eppo_modification = VALUES(date_eppo_modification)";
		// A code without names in the API keeps the names of the file
		if ($namerows) {
			$sql .= ", prefname = VALUES(prefname), authority = VALUES(authority), name_it = VALUES(name_it), name_en = VALUES(name_en)";
		}
		$ok = $this->db->query($sql);
		if ($ok && $namerows) {
			$ok = $this->db->query("DELETE FROM ".$this->db->prefix()."dolifarm_eppo_name WHERE eppocode = '".$this->db->escape($code)."'");
			if ($ok) {
				$sql = "INSERT INTO ".$this->db->prefix()."dolifarm_eppo_name (eppo_name_id, eppocode, lang, fullname, authority, preferred, import_key)";
				$sql .= " VALUES ".implode(', ', $namerows);
				$sql .= " ON DUPLICATE KEY UPDATE eppocode = VALUES(eppocode), lang = VALUES(lang), fullname = VALUES(fullname), authority = VALUES(authority), preferred = VALUES(preferred)";
				$ok = $this->db->query($sql);
			}
		}
		if (!$ok) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Call the EPPO API (GET). The key is sent in the header X-Api-Key and never written in the logs.
	 *
	 * @param	string					$path		Path after the base URL (/taxons/list, ...)
	 * @param	array<string,mixed>		$params		Query parameters
	 * @return	array<mixed>|false					Decoded JSON, false if KO
	 */
	private function call($path, $params = array())
	{
		global $langs;

		if (!function_exists('curl_init')) {
			$this->error = $langs->trans('ErrorEppoNoCurl');
			return false;
		}
		$base = rtrim(getDolGlobalString('DOLIFARM_EPPO_API_URL', self::DEFAULT_URL), '/');
		$url = $base.$path.($params ? '?'.http_build_query($params) : '');

		for ($try = 0; $try <= self::MAX_RETRY; $try++) {
			$this->nbcalls++;
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			// Same option as getURLContent(), for servers without the certificates of the authorities
			if (getDolGlobalString('MAIN_CURL_DISABLE_VERIFYPEER')) {
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Api-Key: '.getDolGlobalString('DOLIFARM_EPPO_API_KEY'), 'Accept: application/json'));
			curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr doliFarm');
			$response = curl_exec($ch);
			$httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curlerror = curl_error($ch);
			unset($ch);

			dol_syslog(__METHOD__.' GET '.$path.' http_code='.$httpcode);
			if ($httpcode == 429 && $try < self::MAX_RETRY) {
				// Rate limit (sliding window of 10 seconds): wait and try again
				sleep(11);
				continue;
			}
			if ($response === false || $httpcode == 0) {
				$this->error = $langs->trans('ErrorEppoApi', $path, $curlerror);
				return false;
			}
			if ($httpcode == 401 || $httpcode == 403) {
				$this->error = $langs->trans('ErrorEppoApiKey');
				return false;
			}
			if ($httpcode != 200) {
				$this->error = $langs->trans('ErrorEppoApi', $path, 'HTTP '.$httpcode);
				return false;
			}
			$data = json_decode((string) $response, true);
			if (!is_array($data)) {
				$this->error = $langs->trans('ErrorEppoApi', $path, 'JSON');
				return false;
			}
			return $data;
		}
		$this->error = $langs->trans('ErrorEppoApi', $path, 'HTTP 429');
		return false;
	}

	/**
	 * Quote a string for the SQL, NULL if empty
	 *
	 * @param	string	$value	Value
	 * @return	string
	 */
	private function quote($value)
	{
		return ($value === '' ? 'NULL' : "'".$this->db->escape(dol_substr($value, 0, 255))."'");
	}

	/**
	 * Quote the date part of a date or datetime for the SQL, NULL if empty or not valid
	 *
	 * @param	string	$value	Value (YYYY-MM-DD...)
	 * @return	string
	 */
	private function quoteDate($value)
	{
		return (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $reg) ? "'".$reg[1]."'" : 'NULL');
	}
}
