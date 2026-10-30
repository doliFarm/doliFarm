<?php
/* Copyright (C) 2026 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       ajax/cadastralwms.php
 * \ingroup    dolifarm
 * \brief      Tiles of the cadastral map (public WMS of the Agenzia delle Entrate) for the Leaflet maps of the plots.
 *
 * The browser cannot use the WMS directly, so the tiles go through Dolibarr. Only GetMap requests on the
 * cadastral layers are accepted, every parameter is checked against a whitelist and the URL is rebuilt here:
 * this is not an open proxy. Answer: the image, or a transparent pixel if the service fails.
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
 * Send a transparent 1x1 PNG and stop (the map shows nothing for this tile)
 *
 * @param	int		$httpcode	HTTP status
 * @return	void
 */
function dolifarm_cadastralwms_empty($httpcode = 200)
{
	http_response_code($httpcode);
	top_httphead('image/png');
	print base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
	exit;
}

/**
 * Value of a WMS parameter, whatever the case of its name (Leaflet may send them in lower or upper case)
 *
 * @param	string	$name	Parameter name in capitals
 * @return	string
 */
function dolifarm_cadastralwms_param($name)
{
	foreach ($_GET as $key => $value) {
		if (is_string($value) && strtoupper($key) == $name) {
			return trim($value);
		}
	}
	return '';
}

if (!isModEnabled('dolifarm') || !$user->hasRight('dolifarm', 'dolifarmplot', 'read')) {
	dolifarm_cadastralwms_empty(403);
}
// Read only: release the session lock, so the requests of the same map run in parallel
session_write_close();

// Whitelist of the parameters of a GetMap request
$allowedlayers = array('CP.CadastralParcel', 'CP.CadastralZoning');
$allowedformats = array('image/png', 'image/jpeg');

$version = dolifarm_cadastralwms_param('VERSION');
if (!in_array($version, array('1.1.1', '1.3.0'))) {
	$version = '1.1.1';
}
$layers = array_values(array_intersect(explode(',', dolifarm_cadastralwms_param('LAYERS')), $allowedlayers));
$format = dolifarm_cadastralwms_param('FORMAT');
$srs = strtoupper(dolifarm_cadastralwms_param($version == '1.3.0' ? 'CRS' : 'SRS'));
$bbox = explode(',', dolifarm_cadastralwms_param('BBOX'));
$width = (int) dolifarm_cadastralwms_param('WIDTH');
$height = (int) dolifarm_cadastralwms_param('HEIGHT');
$transparent = (strtoupper(dolifarm_cadastralwms_param('TRANSPARENT')) == 'FALSE' ? 'FALSE' : 'TRUE');

$valid = strtoupper(dolifarm_cadastralwms_param('REQUEST')) == 'GETMAP'
	&& !empty($layers)
	&& in_array($format, $allowedformats)
	&& in_array($srs, array('EPSG:4258', 'EPSG:6706'))
	&& count($bbox) == 4
	&& $width >= 1 && $width <= 2048 && $height >= 1 && $height <= 2048;
if ($valid) {
	foreach ($bbox as $i => $coord) {
		if (!is_numeric($coord)) {
			$valid = false;
			break;
		}
		$bbox[$i] = (float) $coord;
	}
}
if (!$valid) {
	dolifarm_cadastralwms_empty(400);
}

// URL rebuilt from the checked values (commas and slashes not encoded: the service does not accept them encoded)
$url = 'https://wms.cartografia.agenziaentrate.gov.it/inspire/wms/ows01.php?SERVICE=WMS&REQUEST=GetMap'
	.'&VERSION='.$version
	.'&LAYERS='.implode(',', $layers)
	.'&STYLES='
	.'&FORMAT='.$format
	.'&TRANSPARENT='.$transparent
	.'&'.($version == '1.3.0' ? 'CRS' : 'SRS').'='.$srs
	.'&BBOX='.implode(',', array_map(function ($v) {
		return sprintf('%.8F', $v);
	}, $bbox))
	.'&WIDTH='.$width.'&HEIGHT='.$height;

// getURLContent checks the certificate in production (or as set in the setup) and uses the proxy of the setup
$result = getURLContent($url, 'GET', '', 1, array(), array('https'), 0, -1, 10, 30);
$contenttype = empty($result['content_type']) ? '' : (string) $result['content_type'];
if (empty($result['http_code']) || $result['http_code'] != 200 || !preg_match('/^image\/(png|jpeg)/i', $contenttype) || $result['content'] === '') {
	// The service answers errors in XML with HTTP 200: never forward them as an image
	dol_syslog('dolifarm cadastralwms: WMS error http='.(empty($result['http_code']) ? 0 : $result['http_code']).' type='.$contenttype.' '.(empty($result['curl_error_msg']) ? '' : $result['curl_error_msg']), LOG_ERR);
	dolifarm_cadastralwms_empty();
}

top_httphead(preg_match('/jpeg/i', $contenttype) ? 'image/jpeg' : 'image/png');
header('Cache-Control: private, max-age=86400');
print $result['content'];
