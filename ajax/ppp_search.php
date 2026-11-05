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
 *       \file       ajax/ppp_search.php
 *       \brief      Search of the plant protection products: agrodrugs of the farm and authorised products of the
 *                   catalogue of the Ministry of Health (select2 format)
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
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	http_response_code(500);
	die("Include of main fails");
}

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

dol_include_once('/dolifarm/lib/dolifarm_ppp.lib.php');

$term = GETPOST('term', 'alphanohtml');

// Security check: the agrodrugs are read by doliFarm and by the treatment register of doliTrace
$canwritetreatment = (isModEnabled('dolitrace') && $user->hasRight('dolitrace', 'dolitracetreatment', 'write'));
if (!isModEnabled('dolifarm') || empty($user->id) || (!$user->hasRight('dolifarm', 'dolifarmagrodrug', 'read') && !$canwritetreatment)) {
	httponly_accessforbidden('Module dolifarm not enabled or no permission');
}
// A product of the catalogue becomes an agrodrug: only for the users that can create agrodrugs or treatment records
$catalog = (GETPOSTINT('catalog') && ($user->hasRight('dolifarm', 'dolifarmagrodrug', 'write') || $canwritetreatment));

$langs->load('dolifarm@dolifarm');
top_httphead('application/json');
print json_encode(array('results' => dolifarmAgroDrugSearch($db, $langs, $term, $catalog)));

$db->close();
