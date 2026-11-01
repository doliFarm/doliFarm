<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       ajax/cadastralinfo.php
 * \ingroup    dolifarm
 * \brief      Return the cadastral parcel at a point, read from the public WMS of the Agenzia delle Entrate.
 *
 * Input:  lat, lon (WGS84 decimal degrees)
 * Output: JSON {success, reference, comune_code, comune_name, province, section, sheet, parcel, error}
 * The WMS request is built here with fixed parameters: this is not an open proxy.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

/**
 * Print the JSON answer and stop
 *
 * @param	array<string,mixed>	$data	Data to return
 * @return	void
 */
function dolifarm_cadastralinfo_answer($data)
{
	top_httphead('application/json');
	print json_encode($data);
	exit;
}

if (!isModEnabled('dolifarm') || !$user->hasRight('dolifarm', 'dolifarmplot', 'read')) {
	http_response_code(403);
	dolifarm_cadastralinfo_answer(array('success' => false, 'error' => 'NotEnoughPermissions'));
}
// Read only: release the session lock, so the requests of the same map run in parallel
session_write_close();

$lat = (float) price2num(GETPOST('lat', 'alpha'));
$lon = (float) price2num(GETPOST('lon', 'alpha'));

// The service covers Italy only
if ($lat < 35 || $lat > 48 || $lon < 6 || $lon > 19) {
	dolifarm_cadastralinfo_answer(array('success' => false, 'error' => 'CadastralPointOutsideItaly'));
}

// GetFeatureInfo on a tiny box centred on the point (EPSG:4258 = ETRS89, axis order lon,lat in WMS 1.1.1)
$delta = 0.00001;
$bbox = sprintf('%.6F,%.6F,%.6F,%.6F', $lon - $delta, $lat - $delta, $lon + $delta, $lat + $delta);
$url = 'https://wms.cartografia.agenziaentrate.gov.it/inspire/wms/ows01.php?language=ita&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetFeatureInfo'
	.'&LAYERS=CP.CadastralParcel&QUERY_LAYERS=CP.CadastralParcel&STYLES=&FORMAT=image/png&SRS=EPSG:4258'
	.'&BBOX='.$bbox.'&WIDTH=101&HEIGHT=101&X=50&Y=50&INFO_FORMAT=text/html&FEATURE_COUNT=1';

$result = getURLContent($url, 'GET', '', 1, array(), array('https'), 0, -1, 10, 20);
if (empty($result['http_code']) || $result['http_code'] != 200) {
	dol_syslog('dolifarm cadastralinfo: WMS error '.(empty($result['curl_error_msg']) ? '' : $result['curl_error_msg']), LOG_ERR);
	dolifarm_cadastralinfo_answer(array('success' => false, 'error' => 'CadastralServiceUnavailable'));
}

// Example: <th ...>NationalCadastralReference</th><td>F943_027100.47</td>
$reg = array();
if (!preg_match('/NationalCadastralReference\s*<\/th>\s*<td>\s*([^<\s]+)\s*<\/td>/i', $result['content'], $reg)) {
	dolifarm_cadastralinfo_answer(array('success' => false, 'error' => 'CadastralParcelNotFound'));
}
$reference = $reg[1];

// Reference: comune code (4) + section (1, "_" when the comune has no sections) + sheet (4) + annex (1) + development (1) + "." + parcel
// Examples: F943_027100.47 (Noto, no section), H501A096400.594 (Roma, section A)
$parts = array();
if (!preg_match('/^([A-Z]\d{3})([A-Z0-9_])([0-9A-Z]{4})([0-9A-Z])([0-9A-Z])\.(.+)$/', $reference, $parts)) {
	dolifarm_cadastralinfo_answer(array('success' => true, 'reference' => $reference, 'comune_code' => '', 'comune_name' => '', 'province' => '', 'section' => '', 'sheet' => '', 'parcel' => ''));
}

// Name of the comune from its cadastral code (dictionary loaded from the ISTAT list)
$comune_name = '';
$province = '';
$sql = "SELECT label, province FROM ".MAIN_DB_PREFIX."dolifarm_c_cadastral_comune";
$sql .= " WHERE code = '".$db->escape($parts[1])."' AND active = 1";
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	$comune_name = $obj->label;
	$province = (string) $obj->province;
}

dolifarm_cadastralinfo_answer(array(
	'success' => true,
	'reference' => $reference,
	'comune_code' => $parts[1],
	'comune_name' => $comune_name,
	'province' => $province,
	'section' => ($parts[2] == '_' ? '' : $parts[2]),
	'sheet' => ltrim($parts[3], '0'),
	'parcel' => $parts[6],
));
