<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    lib/dolifarm_biobody.lib.php
 * \ingroup dolifarm
 * \brief   Organic control bodies: official list (dictionary dolifarm_c_biocontrolbody, code IT-BIO-xxx) and third
 *          parties of type "Organic control body" (TE_BIOCTRL) with their code in the extrafield dolifarm_bio_body
 *
 * A body exists once as a third party (contacts, invoices of the certification, documents), created from the official
 * list when it is chosen in a selector. The organic certificate of the other third parties (suppliers, buyers) refers
 * to the code of the dictionary.
 */

dol_include_once('/dolifarm/lib/dolifarm.lib.php');

/**
 * Entries of the official list, active ones, by code
 *
 * @param	DoliDB	$db		Database handler
 * @param	bool	$all	Include the disabled ones
 * @return	array<string,stdClass>
 */
function dolifarmBioBodyList($db, $all = false)
{
	$list = array();
	$sql = "SELECT rowid, code, label, acronym, address, zip, town, country_code, phone, email, url, active FROM ".$db->prefix()."dolifarm_c_biocontrolbody";
	if (!$all) {
		$sql .= " WHERE active = 1";
	}
	$sql .= " ORDER BY code";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$list[(string) $obj->code] = $obj;
	}
	return $list;
}

/**
 * Text of a body of the official list: code and short name
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$code	Code (IT-BIO-xxx); a text that is not a code is returned as it is
 * @return	string
 */
function dolifarmBioBodyLabel($db, $code)
{
	static $cache = null;
	if ($cache === null) {
		$cache = dolifarmBioBodyList($db, true);
	}
	$code = (string) $code;
	if ($code === '' || !isset($cache[$code])) {
		return $code;
	}
	$obj = $cache[$code];
	return $code.' - '.((string) $obj->acronym !== '' ? $obj->acronym : $obj->label);
}

/**
 * Normalised text for the comparisons: upper case letters and digits only
 *
 * @param	string	$text	Text
 * @return	string
 */
function dolifarmBioBodyNormalize($text)
{
	return preg_replace('/[^A-Z0-9]/', '', strtoupper(dol_string_unaccent((string) $text)));
}

/**
 * Code of the official list for a free text: a code in any format (IT BIO 6, IT-BIO-006) or the name of the body
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$text	Text
 * @return	string			Code, '' when not recognised
 */
function dolifarmBioBodyMatch($db, $text)
{
	$text = trim((string) $text);
	if ($text === '') {
		return '';
	}
	$list = dolifarmBioBodyList($db, true);
	if (preg_match('/\bIT\W*BIO\W*0*(\d{1,3})\W*(BZ)?\b/i', $text, $reg)) {
		$code = 'IT-BIO-'.sprintf('%03d', (int) $reg[1]).(empty($reg[2]) ? '' : 'BZ');
		if (isset($list[$code])) {
			return $code;
		}
	}
	$norm = dolifarmBioBodyNormalize($text);
	if ($norm === '') {
		return '';
	}
	foreach ($list as $code => $obj) {
		$acronym = dolifarmBioBodyNormalize($obj->acronym);
		$label = dolifarmBioBodyNormalize($obj->label);
		if (($acronym !== '' && ($norm == $acronym || str_starts_with($norm, $acronym))) || ($label !== '' && ($norm == $label || str_contains($label, $norm) && strlen($norm) >= 4))) {
			return $code;
		}
	}
	return '';
}

/**
 * Third parties of type organic control body, with their code
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<int,array{name:string,code:string}>
 */
function dolifarmBioBodyThirdparties($db)
{
	$list = array();
	$typentid = dolifarmGetTypentId($db, 'TE_BIOCTRL');
	if ($typentid <= 0) {
		return $list;
	}
	$sql = "SELECT s.rowid, s.nom, se.dolifarm_bio_body FROM ".$db->prefix()."societe as s";
	$sql .= " LEFT JOIN ".$db->prefix()."societe_extrafields as se ON se.fk_object = s.rowid";
	$sql .= " WHERE s.fk_typent = ".((int) $typentid)." AND s.status = 1 AND s.entity IN (".getEntity('societe').")";
	$sql .= " ORDER BY s.nom";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$list[(int) $obj->rowid] = array('name' => (string) $obj->nom, 'code' => (string) $obj->dolifarm_bio_body);
	}
	return $list;
}

/**
 * Options of the selector of a body: the third parties of type organic control body, then the bodies of the official
 * list that are not a third party yet (value "dict:" and the code)
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<string,string>
 */
function dolifarmBioBodyOptions($db)
{
	global $langs;

	$langs->load('dolifarm@dolifarm');
	$options = array();
	$codes = array();
	foreach (dolifarmBioBodyThirdparties($db) as $id => $soc) {
		$options[(string) $id] = ($soc['code'] !== '' ? $soc['code'].' - ' : '').$soc['name'];
		$codes[$soc['code']] = 1;
	}
	foreach (dolifarmBioBodyList($db) as $code => $obj) {
		if (!isset($codes[$code])) {
			$options['dict:'.$code] = $code.' - '.$obj->label.' ('.$langs->transnoentities('BioBodyFromList').')';
		}
	}
	return $options;
}

/**
 * Third party of a body of the official list: the existing one with the code, or a new one with the data of the list
 * (type organic control body, supplier: the body invoices the certification)
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User
 * @param	string	$code	Code of the list
 * @param	string	$error	Error message
 * @param	bool	$created	True when the third party was created
 * @return	int				Id of the third party, <0 if KO
 */
function dolifarmBioBodyThirdparty($db, $user, $code, &$error, &$created = false)
{
	global $langs;

	$created = false;
	foreach (dolifarmBioBodyThirdparties($db) as $id => $soc) {
		if ($soc['code'] === $code) {
			return $id;
		}
	}
	$list = dolifarmBioBodyList($db, true);
	if (!isset($list[$code])) {
		$error = $langs->trans('ErrorBioBodyNotInList', $code);
		return -1;
	}
	$obj = $list[$code];
	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
	$soc = new Societe($db);
	$soc->name = (string) $obj->label;
	$soc->name_alias = (string) $obj->acronym;
	$soc->address = (string) $obj->address;
	$soc->zip = (string) $obj->zip;
	$soc->town = (string) $obj->town;
	$soc->country_id = (int) dol_getIdFromCode($db, ((string) $obj->country_code !== '' ? $obj->country_code : 'IT'), 'c_country', 'code', 'rowid');
	$soc->phone = (string) $obj->phone;
	$soc->email = (string) $obj->email;
	$soc->url = (string) $obj->url;
	$soc->typent_id = dolifarmGetTypentId($db, 'TE_BIOCTRL');
	$soc->client = 0;
	$soc->fournisseur = 1;
	$soc->code_client = -1;
	$soc->code_fournisseur = -1;
	$soc->status = 1;
	$soc->array_options = array('options_dolifarm_bio_body' => $code);
	$id = $soc->create($user);
	if ($id <= 0) {
		$error = $soc->error ?: implode(', ', $soc->errors);
		return -1;
	}
	$created = true;
	dol_syslog(__FUNCTION__.' third party '.$id.' created for the organic control body '.$code, LOG_INFO);
	return (int) $id;
}

/**
 * Posted value of a selector of a body ("dict:" and a code) changed into the id of its third party, created when
 * needed, before the generic actions of the card
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User
 * @param	string	$key	Name of the field
 * @return	int				<0 if KO, 0 nothing to do, >0 if OK
 */
function dolifarmBioBodyHandlePost($db, $user, $key)
{
	global $langs;

	$value = GETPOST($key, 'alphanohtml');
	if (!str_starts_with($value, 'dict:')) {
		return 0;
	}
	$langs->load('dolifarm@dolifarm');
	$error = '';
	$created = false;
	$id = dolifarmBioBodyThirdparty($db, $user, substr($value, 5), $error, $created);
	if ($id < 0) {
		setEventMessages($error, null, 'errors');
		$_POST[$key] = '';
		return -1;
	}
	if ($created) {
		setEventMessages($langs->trans('BioBodyThirdpartyCreated'), null, 'mesgs');
	}
	$_POST[$key] = (string) $id;
	return 1;
}

/**
 * Field of a card (create or edit form) shown as the selector of a body: third parties of the bodies and official list
 *
 * @param	CommonObject	$object	Object
 * @param	string			$key	Name of the field
 * @return	void
 */
function dolifarmBioBodyPrepareField($object, $key)
{
	if (!isset($object->fields[$key])) {
		return;
	}
	$object->fields[$key]['type'] = 'select';
	$object->fields[$key]['arrayofkeyval'] = dolifarmBioBodyOptions($object->db);
	$object->fields[$key]['help'] = 'BioBodySelectHelp';
	unset($object->fields[$key]['picto']);
}

/**
 * Migration of the data of the bodies, at the activation (idempotent): the third parties of type organic control body
 * without code get the code of the list that matches their name; the organic certificates of the third parties with a
 * free text get the code that matches the text. The texts that do not match are kept.
 *
 * @param	DoliDB	$db		Database handler
 * @return	int				Number of values changed
 */
function dolifarmBioBodyMigrate($db)
{
	$nb = 0;
	$list = dolifarmBioBodyList($db, true);
	if (empty($list)) {
		return 0;
	}
	$typentid = dolifarmGetTypentId($db, 'TE_BIOCTRL');
	$sql = "SELECT s.rowid, s.nom, s.name_alias, s.fk_typent, se.rowid as efid, se.dolifarm_bio_body FROM ".$db->prefix()."societe as s";
	$sql .= " LEFT JOIN ".$db->prefix()."societe_extrafields as se ON se.fk_object = s.rowid";
	$sql .= " WHERE (se.dolifarm_bio_body IS NOT NULL AND se.dolifarm_bio_body <> '')".($typentid > 0 ? " OR s.fk_typent = ".((int) $typentid) : '');
	$resql = $db->query($sql);
	$rows = array();
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$rows[] = $obj;
	}
	foreach ($rows as $obj) {
		$current = (string) $obj->dolifarm_bio_body;
		if ($current !== '' && isset($list[$current])) {
			continue;
		}
		if ($current !== '') {
			$code = dolifarmBioBodyMatch($db, $current);
		} else {
			$code = dolifarmBioBodyMatch($db, $obj->nom);
			if ($code === '' && (string) $obj->name_alias !== '') {
				$code = dolifarmBioBodyMatch($db, $obj->name_alias);
			}
		}
		if ($code === '') {
			continue;
		}
		if ($obj->efid > 0) {
			$res = $db->query("UPDATE ".$db->prefix()."societe_extrafields SET dolifarm_bio_body = '".$db->escape($code)."' WHERE rowid = ".((int) $obj->efid));
		} else {
			$res = $db->query("INSERT INTO ".$db->prefix()."societe_extrafields (fk_object, dolifarm_bio_body) VALUES (".((int) $obj->rowid).", '".$db->escape($code)."')");
		}
		if ($res) {
			$nb++;
		}
	}
	return $nb;
}

/**
 * Third parties whose organic control body is a text that is not a code of the list (to correct by hand)
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<int,array{name:string,text:string}>
 */
function dolifarmBioBodyUnmatched($db)
{
	$list = dolifarmBioBodyList($db, true);
	$rows = array();
	$sql = "SELECT s.rowid, s.nom, se.dolifarm_bio_body FROM ".$db->prefix()."societe as s";
	$sql .= " INNER JOIN ".$db->prefix()."societe_extrafields as se ON se.fk_object = s.rowid";
	$sql .= " WHERE se.dolifarm_bio_body IS NOT NULL AND se.dolifarm_bio_body <> '' AND s.entity IN (".getEntity('societe').")";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		if (!isset($list[(string) $obj->dolifarm_bio_body])) {
			$rows[(int) $obj->rowid] = array('name' => (string) $obj->nom, 'text' => (string) $obj->dolifarm_bio_body);
		}
	}
	return $rows;
}
