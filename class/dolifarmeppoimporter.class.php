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
 * \file        class/dolifarmeppoimporter.class.php
 * \ingroup     dolifarm
 * \brief       Import of the EPPO Codes from the EPPO file "XML full" (fullcodes.xml, also gzip compressed)
 *
 * Only the EPPO Codes are imported (code, type, status, names): no hosts, distribution or categorisation,
 * which the EPPO Open Data Licence does not cover. The import can run again: the rows are updated by code
 * and by name id, the names no longer in the file are removed at the end of a complete import.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * Import of the EPPO Codes
 */
class DoliFarmEppoImporter
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
	 * @var int		Number of codes imported
	 */
	public $nbcodes = 0;

	/**
	 * @var int		Number of names imported
	 */
	public $nbnames = 0;

	/**
	 * @var string	Export date of the file (YYYY-MM-DD)
	 */
	public $dateexport = '';

	/**
	 * @var string	Import key written on the rows of this import
	 */
	public $import_key = '';

	/**
	 * @var string[]	Languages of the names kept for the search
	 */
	public $langs = array('la', 'it', 'en');

	/**
	 * @var array<int,string>	Rows of codes waiting to be written
	 */
	private $codebuffer = array();

	/**
	 * @var array<int,string>	Rows of names waiting to be written
	 */
	private $namebuffer = array();

	const BATCH_SIZE = 500;

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
	 * Import the EPPO file
	 *
	 * @param	string	$file	Path of fullcodes.xml or of its gzip compressed copy
	 * @return	int				Return integer <0 if KO, number of codes imported if OK
	 */
	public function importFile($file)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		$this->error = '';
		if (!class_exists('XMLReader')) {
			$this->error = $langs->trans('ErrorEppoNoXmlReader');
			return -1;
		}
		if (!is_readable($file)) {
			$this->error = $langs->trans('ErrorFileNotFound', basename($file));
			return -1;
		}
		$handle = fopen($file, 'rb');
		$magic = ($handle ? fread($handle, 2) : '');
		if ($handle) {
			fclose($handle);
		}
		if ($magic !== "\x1f\x8b") {
			return $this->importXml($file);
		}

		// Gzip file: Dolibarr disables the stream wrapper compress.zlib, so the file is uncompressed with
		// the zlib functions into a temporary file next to it
		$tmpfile = $file.'.tmp.xml';
		$gz = gzopen($file, 'rb');
		$out = fopen($tmpfile, 'wb');
		if (!$gz || !$out) {
			$this->error = $langs->trans('ErrorEppoBadFile');
			return -1;
		}
		while (!gzeof($gz)) {
			$data = gzread($gz, 1048576);
			if ($data === false || fwrite($out, $data) === false) {
				$this->error = $langs->trans('ErrorEppoBadFile');
				break;
			}
		}
		gzclose($gz);
		fclose($out);
		$result = ($this->error ? -1 : $this->importXml($tmpfile));
		@unlink($tmpfile);
		return $result;
	}

	/**
	 * Import the uncompressed EPPO file
	 *
	 * @param	string	$file	Path of fullcodes.xml
	 * @return	int				Return integer <0 if KO, number of codes imported if OK
	 */
	private function importXml($file)
	{
		global $langs;

		$reader = new XMLReader();
		if (!@$reader->open($file, null, LIBXML_NONET | LIBXML_COMPACT)) {
			$this->error = $langs->trans('ErrorEppoBadFile');
			return -1;
		}

		$this->import_key = dol_print_date(dol_now(), 'dayhourlog');
		$this->nbcodes = 0;
		$this->nbnames = 0;
		dol_syslog(__METHOD__.' file='.$file.' import_key='.$this->import_key);

		// Root element <codes dateexport="...">, then its first <code>
		$root = false;
		$found = false;
		while (@$reader->read()) {
			if ($reader->nodeType != XMLReader::ELEMENT) {
				continue;
			}
			if (!$root) {
				if ($reader->name != 'codes') {
					break;
				}
				$root = true;
				$this->dateexport = substr((string) $reader->getAttribute('dateexport'), 0, 10);
				continue;
			}
			$found = ($reader->name == 'code');
			break;
		}
		// One <code> at a time, from sibling to sibling: the memory does not grow with the size of the file
		while ($found) {
			$xml = @simplexml_load_string($reader->readOuterXml());
			if ($xml === false) {
				$this->error = $langs->trans('ErrorEppoBadFile');
				$reader->close();
				return -1;
			}
			$this->addCode($xml);
			if (count($this->codebuffer) >= self::BATCH_SIZE && $this->flush() < 0) {
				$reader->close();
				return -1;
			}
			$found = @$reader->next('code');
		}
		$reader->close();

		if (!$root || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateexport)) {
			$this->error = $langs->trans('ErrorEppoBadFile');
			return -1;
		}
		if ($this->flush() < 0) {
			return -1;
		}
		if ($this->nbcodes == 0) {
			$this->error = $langs->trans('ErrorEppoBadFile');
			return -1;
		}

		// Complete import: the names no longer in the file are removed (the codes stay, EPPO deactivates them)
		$sql = "DELETE FROM ".$this->db->prefix()."dolifarm_eppo_name WHERE import_key IS NULL OR import_key <> '".$this->db->escape($this->import_key)."'";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		// Shared data: constants of entity 0
		dolibarr_set_const($this->db, 'DOLIFARM_EPPO_DATA_DATE', $this->dateexport, 'chaine', 0, 'Export date of the EPPO Codes', 0);
		dolibarr_set_const($this->db, 'DOLIFARM_EPPO_IMPORT_DATE', (string) dol_now(), 'chaine', 0, 'Date of the last import of the EPPO Codes', 0);

		dol_syslog(__METHOD__.' done: '.$this->nbcodes.' codes, '.$this->nbnames.' names, export of '.$this->dateexport);
		return $this->nbcodes;
	}

	/**
	 * Add a <code> element to the buffers
	 *
	 * @param	SimpleXMLElement	$xml	Element <code>
	 * @return	void
	 */
	private function addCode($xml)
	{
		$eppocode = strtoupper(trim((string) $xml->eppocode));
		if ($eppocode === '') {
			return;
		}

		$prefname = '';
		$authority = '';
		$best = array('it' => '', 'en' => '');
		if (isset($xml->names->name)) {
			foreach ($xml->names->name as $name) {
				$lang = trim((string) $name->lang);
				$fullname = trim((string) $name->fullname);
				if ($fullname === '' || (string) $name['isactive'] === 'false' || !in_array($lang, $this->langs)) {
					continue;
				}
				$preferred = ((string) $name['ispreferred'] === 'true');
				if ($preferred && $prefname === '') {
					$prefname = $fullname;
					$authority = trim((string) $name->authority);
				}
				// Common name: the shortest active one (e.g. "frumento" rather than "fromento tenero")
				if (isset($best[$lang]) && ($best[$lang] === '' || dol_strlen($fullname) < dol_strlen($best[$lang]))) {
					$best[$lang] = $fullname;
				}
				$this->namebuffer[] = "(".((int) $name['id']).", '".$this->db->escape($eppocode)."', '".$this->db->escape($lang)."', '".$this->db->escape(dol_substr($fullname, 0, 255))."', ".$this->quote(trim((string) $name->authority)).", ".($preferred ? 1 : 0).", '".$this->db->escape($this->import_key)."')";
			}
		}

		$parent = '';
		if (isset($xml->parents->parent)) {
			foreach ($xml->parents->parent as $tmpparent) {
				if ((string) $tmpparent['linktype'] == 'taxo') {
					$parent = strtoupper(trim((string) $tmpparent));
					break;
				}
			}
		}

		$this->codebuffer[] = "('".$this->db->escape($eppocode)."', ".((int) $xml['id']).", ".$this->quote(substr((string) $xml['type'], 0, 3)).", ".((string) $xml['isactive'] === 'false' ? 0 : 1)
			.", ".$this->quote($prefname).", ".$this->quote($authority).", ".$this->quote($best['it']).", ".$this->quote($best['en']).", ".$this->quote($parent)
			.", ".$this->quoteDate((string) $xml['creation']).", ".$this->quoteDate((string) $xml['modification']).", '".$this->db->escape($this->import_key)."')";
	}

	/**
	 * Write the buffers into the database
	 *
	 * @return	int		Return integer <0 if KO, >0 if OK
	 */
	private function flush()
	{
		if ($this->codebuffer) {
			// replacedby is not in the file: it is kept as written by the EPPO API
			$sql = "INSERT INTO ".$this->db->prefix()."dolifarm_eppo_code";
			$sql .= " (eppocode, eppo_id, datatype, is_active, prefname, authority, name_it, name_en, parent_code, date_eppo_creation, date_eppo_modification, import_key)";
			$sql .= " VALUES ".implode(', ', $this->codebuffer);
			$sql .= " ON DUPLICATE KEY UPDATE eppo_id = VALUES(eppo_id), datatype = VALUES(datatype), is_active = VALUES(is_active), prefname = VALUES(prefname), authority = VALUES(authority)";
			$sql .= ", name_it = VALUES(name_it), name_en = VALUES(name_en), parent_code = VALUES(parent_code), date_eppo_creation = VALUES(date_eppo_creation)";
			$sql .= ", date_eppo_modification = VALUES(date_eppo_modification), import_key = VALUES(import_key)";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$this->nbcodes += count($this->codebuffer);
			$this->codebuffer = array();
		}
		foreach (array_chunk($this->namebuffer, self::BATCH_SIZE * 2) as $rows) {
			$sql = "INSERT INTO ".$this->db->prefix()."dolifarm_eppo_name (eppo_name_id, eppocode, lang, fullname, authority, preferred, import_key)";
			$sql .= " VALUES ".implode(', ', $rows);
			$sql .= " ON DUPLICATE KEY UPDATE eppocode = VALUES(eppocode), lang = VALUES(lang), fullname = VALUES(fullname), authority = VALUES(authority)";
			$sql .= ", preferred = VALUES(preferred), import_key = VALUES(import_key)";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$this->nbnames += count($rows);
		}
		$this->namebuffer = array();
		return 1;
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
	 * Quote a date YYYY-MM-DD for the SQL, NULL if empty or not valid
	 *
	 * @param	string	$value	Value
	 * @return	string
	 */
	private function quoteDate($value)
	{
		return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? "'".$value."'" : 'NULL');
	}
}
