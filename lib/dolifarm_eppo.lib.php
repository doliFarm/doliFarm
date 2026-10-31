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
 * \file    lib/dolifarm_eppo.lib.php
 * \ingroup dolifarm
 * \brief   EPPO Codes imported in doliFarm: search, check and display (crops here, pests in doliTrace)
 */

/**
 * Datatypes of the EPPO Codes used by the suite
 *
 * @return	array<string,string[]>	Use => EPPO datatypes
 */
function dolifarmEppoDatatypes()
{
	return array(
		'crop' => array('PFL'),
		'pest' => array('GAI', 'GAF', 'PFL', 'NTX'),	// weeds are plants, abiotic damages are non-taxonomic entries
	);
}

/**
 * Check the syntax of an EPPO code
 *
 * @param	string	$code	Code
 * @return	bool
 */
function dolifarmEppoCodeSyntaxOk($code)
{
	return (bool) preg_match('/^[0-9A-Z]{3,10}$/', $code);
}

/**
 * Load an EPPO code from the imported data
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$code	EPPO code
 * @return	object|null|false	Row of llx_dolifarm_eppo_code, null if not found, false if error
 */
function dolifarmEppoFetch($db, $code)
{
	static $cache = array();

	$code = strtoupper(trim((string) $code));
	if ($code === '' || !dolifarmEppoCodeSyntaxOk($code)) {
		return null;
	}
	if (array_key_exists($code, $cache)) {
		return $cache[$code];
	}
	$sql = "SELECT eppocode, datatype, is_active, prefname, authority, name_it, name_en, replacedby";
	$sql .= " FROM ".$db->prefix()."dolifarm_eppo_code WHERE eppocode = '".$db->escape($code)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return false;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	$cache[$code] = $obj ? $obj : null;
	return $cache[$code];
}

/**
 * Whether the EPPO Codes have been imported
 *
 * @return	bool
 */
function dolifarmEppoDataAvailable()
{
	return getDolGlobalString('DOLIFARM_EPPO_DATA_DATE') !== '';
}

/**
 * Check an EPPO code typed for a record
 *
 * @param	DoliDB		$db			Database handler
 * @param	string		$code		EPPO code (already in upper case)
 * @param	string		$use		'crop' or 'pest'
 * @return	string					'' if OK, else the error message
 */
function dolifarmEppoCheckCode($db, $code, $use)
{
	global $langs;

	if ($code === '') {
		return '';
	}
	$langs->load('dolifarm@dolifarm');
	if (!dolifarmEppoCodeSyntaxOk($code)) {
		return $langs->trans('ErrorEppoCodeSyntax', $code);
	}
	// Without the imported data only the syntax can be checked
	if (!dolifarmEppoDataAvailable()) {
		return '';
	}
	$obj = dolifarmEppoFetch($db, $code);
	if ($obj === false) {
		return $db->lasterror();
	}
	if ($obj === null) {
		return $langs->trans('ErrorEppoCodeNotFound', $code);
	}
	$datatypes = dolifarmEppoDatatypes();
	if (isset($datatypes[$use]) && !in_array($obj->datatype, $datatypes[$use])) {
		return $langs->trans('ErrorEppoCodeWrongType', $code, $langs->transnoentitiesnoconv('EppoDatatype'.$obj->datatype));
	}
	return '';
}

/**
 * Find the EPPO code of a latin name: the active code with this name, only when there is one
 *
 * @param	DoliDB		$db			Database handler
 * @param	string		$name		Latin name (e.g. scientific name of a crop)
 * @param	string		$use		'crop' or 'pest'
 * @return	string					EPPO code, '' if none or more than one
 */
function dolifarmEppoFindByLatinName($db, $name, $use)
{
	$name = trim((string) $name);
	$datatypes = dolifarmEppoDatatypes();
	if ($name === '' || !dolifarmEppoDataAvailable() || !isset($datatypes[$use])) {
		return '';
	}
	$sql = "SELECT DISTINCT c.eppocode FROM ".$db->prefix()."dolifarm_eppo_name as n";
	$sql .= " INNER JOIN ".$db->prefix()."dolifarm_eppo_code as c ON c.eppocode = n.eppocode";
	$sql .= " WHERE n.lang = 'la' AND n.fullname = '".$db->escape($name)."' AND c.is_active = 1";
	$sql .= " AND c.datatype IN ('".implode("','", $datatypes[$use])."')";
	$sql .= $db->plimit(2);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return '';
	}
	$codes = array();
	while ($obj = $db->fetch_object($resql)) {
		$codes[] = $obj->eppocode;
	}
	$db->free($resql);
	return (count($codes) == 1 ? $codes[0] : '');
}

/**
 * Fill the empty EPPO codes of the crops from their scientific name (only unique matches)
 *
 * @param	DoliDB	$db		Database handler
 * @return	int				Number of crops filled, <0 if KO
 */
function dolifarmEppoFillCrops($db)
{
	$sql = "SELECT rowid, scientific_name FROM ".$db->prefix()."dolifarm_crops";
	$sql .= " WHERE (eppo_code IS NULL OR eppo_code = '') AND scientific_name IS NOT NULL AND scientific_name <> ''";
	$sql .= " AND entity IN (".getEntity('dolifarmcrops').")";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	$tofill = array();
	while ($obj = $db->fetch_object($resql)) {
		$code = dolifarmEppoFindByLatinName($db, $obj->scientific_name, 'crop');
		if ($code !== '') {
			$tofill[(int) $obj->rowid] = $code;
		}
	}
	$db->free($resql);
	foreach ($tofill as $rowid => $code) {
		$sql = "UPDATE ".$db->prefix()."dolifarm_crops SET eppo_code = '".$db->escape($code)."' WHERE rowid = ".((int) $rowid);
		if (!$db->query($sql)) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			return -1;
		}
	}
	return count($tofill);
}

/**
 * Label of an EPPO code: code, latin name and common name in the language of the user
 *
 * @param	object		$obj		Row of llx_dolifarm_eppo_code
 * @param	bool		$withcode	Include the code
 * @return	string					Plain text
 */
function dolifarmEppoLabel($obj, $withcode = true)
{
	global $langs;

	$common = (substr($langs->defaultlang, 0, 2) == 'it' ? $obj->name_it : $obj->name_en);
	$label = ($withcode ? $obj->eppocode.' - ' : '').$obj->prefname;
	if (!empty($common) && $common != $obj->prefname) {
		$label .= ' ('.$common.')';
	}
	if (empty($obj->is_active)) {
		$label .= ' ['.$langs->transnoentitiesnoconv('EppoInactive').(empty($obj->replacedby) ? '' : ' → '.$obj->replacedby).']';
	}
	return $label;
}

/**
 * HTML to show an EPPO code in a card or a list
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$code	EPPO code
 * @return	string			HTML
 */
function dolifarmEppoShow($db, $code)
{
	global $langs;

	$code = (string) $code;
	if ($code === '') {
		return '';
	}
	$obj = dolifarmEppoFetch($db, $code);
	if (!$obj) {
		return dol_escape_htmltag($code);
	}
	$langs->load('dolifarm@dolifarm');
	$title = $langs->trans('EppoAttribution').(getDolGlobalString('DOLIFARM_EPPO_DATA_DATE') ? ' - '.$langs->trans('EppoDataDate').': '.dol_print_date($db->jdate(getDolGlobalString('DOLIFARM_EPPO_DATA_DATE')), 'day') : '');
	$out = '<span class="classfortooltip'.(empty($obj->is_active) ? ' opacitymedium' : '').'" title="'.dolPrintHTMLForAttribute($title).'">';
	$out .= '<strong>'.dol_escape_htmltag($obj->eppocode).'</strong> '.dol_escape_htmltag(dolifarmEppoLabel($obj, false));
	$out .= '</span>';
	return $out;
}

/**
 * Search the EPPO Codes by code, latin name or common name (also synonyms)
 *
 * @param	DoliDB		$db			Database handler
 * @param	string		$term		Text typed
 * @param	string[]	$datatypes	EPPO datatypes allowed (empty = all)
 * @param	int			$limit		Maximum number of results
 * @return	array<string,array{id:string,text:string}>|false	Code => result, false if error
 */
function dolifarmEppoSearch($db, $term, $datatypes = array(), $limit = 30)
{
	$term = trim((string) $term);
	if (dol_strlen($term) < 2) {
		return array();
	}
	$typefilter = '';
	$datatypes = preg_grep('/^[A-Z]{3}$/', (array) $datatypes);
	if ($datatypes) {
		$typefilter = " AND c.datatype IN ('".implode("','", $datatypes)."')";
	}
	$fields = "c.eppocode, c.datatype, c.is_active, c.prefname, c.authority, c.name_it, c.name_en, c.replacedby";

	$results = array();
	$queries = array();
	// Code typed: codes that start with it
	if (preg_match('/^[0-9A-Za-z]+$/', $term)) {
		$queries[] = "SELECT ".$fields.", '' as matched FROM ".$db->prefix()."dolifarm_eppo_code as c"
			." WHERE c.eppocode LIKE '".$db->escape(strtoupper($term))."%'".$typefilter
			." ORDER BY c.is_active DESC, c.eppocode".$db->plimit($limit);
	}
	// Names (latin, Italian, English, synonyms) with a word that starts with the text
	$like = $db->escape($db->escapeforlike($term));
	$queries[] = "SELECT ".$fields.", n.fullname as matched FROM ".$db->prefix()."dolifarm_eppo_name as n"
		." INNER JOIN ".$db->prefix()."dolifarm_eppo_code as c ON c.eppocode = n.eppocode"
		." WHERE (n.fullname LIKE '".$like."%' OR n.fullname LIKE '% ".$like."%')".$typefilter
		." ORDER BY c.is_active DESC, CASE WHEN n.fullname = '".$db->escape($term)."' THEN 0 WHEN n.fullname LIKE '".$like."%' THEN 1 ELSE 2 END,"
		." CASE n.lang WHEN 'it' THEN 0 WHEN 'la' THEN 1 ELSE 2 END, n.preferred DESC, LENGTH(n.fullname), c.eppocode".$db->plimit($limit * 2);

	foreach ($queries as $sql) {
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			return false;
		}
		while (($obj = $db->fetch_object($resql)) && count($results) < $limit) {
			if (isset($results[$obj->eppocode])) {
				continue;
			}
			$text = dolifarmEppoLabel($obj);
			if ($obj->matched !== '' && stripos($text, $obj->matched) === false) {
				$text .= ' - '.$obj->matched;
			}
			$results[$obj->eppocode] = array('id' => $obj->eppocode, 'text' => $text);
		}
		$db->free($resql);
	}
	return $results;
}

/**
 * HTML of the field to choose an EPPO code: a select with search on the imported data (ajax),
 * a plain input when the data have not been imported
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$htmlname	Name and id of the field
 * @param	string	$value		Current code
 * @param	string	$use		'crop' or 'pest'
 * @param	string	$morecss	More css on the field
 * @return	string				HTML
 */
function dolifarmEppoSelect($db, $htmlname, $value, $use, $morecss = 'minwidth300')
{
	global $conf, $langs;

	$langs->load('dolifarm@dolifarm');
	$value = strtoupper(trim((string) $value));
	if (!dolifarmEppoDataAvailable() || empty($conf->use_javascript_ajax)) {
		return '<input type="text" class="flat maxwidth100" name="'.dol_escape_htmltag($htmlname).'" id="'.dol_escape_htmltag($htmlname).'" value="'.dol_escape_htmltag($value).'" maxlength="10">';
	}

	$out = '<select class="flat '.dol_escape_htmltag($morecss).'" name="'.dol_escape_htmltag($htmlname).'" id="'.dol_escape_htmltag($htmlname).'">';
	$out .= '<option value=""></option>';
	if ($value !== '') {
		$obj = dolifarmEppoFetch($db, $value);
		$out .= '<option value="'.dol_escape_htmltag($value).'" selected>'.dol_escape_htmltag($obj ? dolifarmEppoLabel($obj) : $value).'</option>';
	}
	$out .= '</select>';
	$out .= ' <span class="opacitymedium small">'.dol_escape_htmltag($langs->trans('EppoAttribution')).'</span>';

	$url = dol_buildpath('/dolifarm/ajax/eppo_search.php', 1);
	$out .= '<script>
	$(document).ready(function() {
		$('.json_encode('#'.$htmlname).').select2({
			width: "resolve",
			allowClear: true,
			placeholder: '.json_encode($langs->transnoentities('EppoSearchPlaceholder')).',
			minimumInputLength: 2,
			language: (typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,
			ajax: {
				url: '.json_encode($url).',
				dataType: "json",
				delay: 300,
				data: function(params) {
					return { term: params.term, use: '.json_encode($use).' };
				},
				processResults: function(data) {
					return { results: data.results || [] };
				}
			}
		});
	});
	</script>';
	return $out;
}
