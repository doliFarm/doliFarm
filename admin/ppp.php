<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    admin/ppp.php
 * \ingroup dolifarm
 * \brief   Catalogue of the plant protection products of the Ministry of Health: state, sync, upload of the file
 */


// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
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
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/dolifarm/lib/dolifarm.lib.php');
dol_include_once('/dolifarm/class/dolifarmpppsync.class.php');

$langs->loadLangs(array("errors", "admin", "dolifarm@dolifarm"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');


/*
 * Actions
 */

$sync = new DoliFarmPppSync($db);
if ($action == 'sync') {
	$result = $sync->sync(GETPOSTINT('force') > 0);
	if ($result < 0) {
		setEventMessages($sync->error, null, 'errors');
	} elseif ($result == 0) {
		setEventMessages($langs->trans('PppSyncSameFile', getDolGlobalString('DOLIFARM_PPP_FILE')).' '.$langs->trans('PppAgroDrugsUpdated', $sync->nbagrodrugs), null, 'mesgs');
	} else {
		setEventMessages($sync->getSummary(), null, 'mesgs');
	}
}
// File downloaded by hand from the site of the Ministry, for servers without access to internet
if ($action == 'upload') {
	if (empty($_FILES['pppfile']['tmp_name']) || !empty($_FILES['pppfile']['error']) || !preg_match('/\.csv$/i', $_FILES['pppfile']['name'])) {
		setEventMessages($langs->trans('ErrorPppNoUpload'), null, 'errors');
	} else {
		$result = $sync->importContent((string) file_get_contents($_FILES['pppfile']['tmp_name']), dol_sanitizeFileName($_FILES['pppfile']['name']));
		if ($result < 0) {
			setEventMessages($sync->error, null, 'errors');
		} else {
			setEventMessages($sync->getSummary(), null, 'mesgs');
		}
	}
}


/*
 * View
 */

$title = "DolifarmSetup";
llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-dolifarm page-admin_ppp');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = dolifarmAdminPrepareHead();
print dol_get_fiche_head($head, 'ppp', $langs->trans($title), -1, 'dolifarm@dolifarm');

print '<span class="opacitymedium">'.$langs->trans('PppPageHelp').'</span><br><br>';

$counts = array();
$total = 0;
$resql = $db->query("SELECT admin_status, COUNT(*) as nb FROM ".$db->prefix()."dolifarm_ppp_catalog GROUP BY admin_status");
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$class = DoliFarmPppSync::statusClass($obj->admin_status);
		$class = ($class === '' ? 'other' : $class);
		$counts[$class] = (isset($counts[$class]) ? $counts[$class] : 0) + (int) $obj->nb;
		$total += (int) $obj->nb;
	}
	$db->free($resql);
} else {
	setEventMessages($db->lasterror(), null, 'errors');
}
$nbagrodrugs = 0;
$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."dolifarm_agrodrug WHERE date_catalog_sync IS NOT NULL");
if ($resql && ($obj = $db->fetch_object($resql))) {
	$nbagrodrugs = (int) $obj->nb;
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('PppCatalog').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('PppSource').'</td><td>'.$langs->trans('PppAttribution').' - <a href="'.dol_escape_htmltag(getDolGlobalString('DOLIFARM_PPP_DATASET_URL', DoliFarmPppSync::DATASET_URL)).'" target="_blank" rel="noopener noreferrer">dati.salute.gov.it</a></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PppFile').'</td><td>'.(getDolGlobalString('DOLIFARM_PPP_FILE') ? dol_escape_htmltag(getDolGlobalString('DOLIFARM_PPP_FILE')) : '<span class="opacitymedium">'.$langs->trans('PppNeverSynced').'</span>').'</td></tr>';
$syncdate = getDolGlobalInt('DOLIFARM_PPP_SYNC_DATE');
print '<tr class="oddeven"><td>'.$langs->trans('PppSyncDate').'</td><td>'.($syncdate ? dol_print_date($syncdate, 'dayhour') : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PppNbProducts').'</td><td>'.price($total, 0, $langs, 0, 0);
foreach (array('authorized', 'suspended', 'revoked', 'expired', 'other') as $class) {
	if (!empty($counts[$class])) {
		print ' &nbsp; <span class="opacitymedium">'.$langs->trans('PppStatus_'.$class).':</span> '.price($counts[$class], 0, $langs, 0, 0);
	}
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PppNbAgroDrugs').'</td><td>'.$nbagrodrugs.'</td></tr>';
print '</table>';
print '</div>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sync">';
print '<input type="submit" class="button small reposition" value="'.$langs->trans('PppSyncNow').'">';
print ' <label><input type="checkbox" name="force" value="1"> '.$langs->trans('PppSyncForce').'</label>';
print ' <span class="opacitymedium">'.$langs->trans('PppSyncCron', $langs->transnoentities('DolifarmPppSyncJob')).'</span>';
print '</form>';

print '<br>';
print load_fiche_titre($langs->trans('PppUpload'), '', '');
print '<span class="opacitymedium">'.$langs->trans('PppUploadHelp').'</span><br><br>';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload">';
print '<input type="file" name="pppfile" accept=".csv"> ';
print '<input type="submit" class="button small reposition" value="'.$langs->trans('Upload').'">';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
