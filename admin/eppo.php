<?php
/* Copyright (C) 2026      SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    admin/eppo.php
 * \ingroup dolifarm
 * \brief   EPPO Codes: import of the EPPO file "XML full" and state of the data
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/dolifarm/lib/dolifarm.lib.php');
dol_include_once('/dolifarm/class/dolifarmeppoimporter.class.php');

$langs->loadLangs(array("errors", "admin", "dolifarm@dolifarm"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// The file stays in the documents of the module, so that the import can be run again from the page
$eppodir = $conf->dolifarm->dir_output.'/eppo';


/*
 * Actions
 */

$file = '';
if ($action == 'upload') {
	if (empty($_FILES['eppofile']['tmp_name']) || !empty($_FILES['eppofile']['error'])) {
		$errorcode = empty($_FILES['eppofile']['error']) ? 0 : (int) $_FILES['eppofile']['error'];
		setEventMessages($langs->trans($errorcode == UPLOAD_ERR_INI_SIZE || $errorcode == UPLOAD_ERR_FORM_SIZE ? 'ErrorEppoFileTooLarge' : 'ErrorEppoNoFile'), null, 'errors');
	} elseif (!preg_match('/\.(xml|xml\.gz|gz)$/i', $_FILES['eppofile']['name'])) {
		setEventMessages($langs->trans('ErrorEppoBadFile'), null, 'errors');
	} else {
		dol_mkdir($eppodir);
		$file = $eppodir.'/'.(preg_match('/\.gz$/i', $_FILES['eppofile']['name']) ? 'fullcodes.xml.gz' : 'fullcodes.xml');
		// Only one copy of the file: the other format is removed
		foreach (array('fullcodes.xml', 'fullcodes.xml.gz') as $tmpname) {
			if (file_exists($eppodir.'/'.$tmpname)) {
				dol_delete_file($eppodir.'/'.$tmpname);
			}
		}
		$result = dol_move_uploaded_file($_FILES['eppofile']['tmp_name'], $file, 1, 0, $_FILES['eppofile']['error']);
		if ($result !== 1) {
			setEventMessages($langs->trans('ErrorEppoNoFile'), null, 'errors');
			$file = '';
		}
	}
}
if ($action == 'import') {
	foreach (array('fullcodes.xml.gz', 'fullcodes.xml') as $tmpname) {
		if (empty($file) && file_exists($eppodir.'/'.$tmpname)) {
			$file = $eppodir.'/'.$tmpname;
		}
	}
	if (empty($file)) {
		setEventMessages($langs->trans('ErrorEppoNoFile'), null, 'errors');
	}
}
if ($action == 'fillcrops') {
	dol_include_once('/dolifarm/lib/dolifarm_eppo.lib.php');
	$result = dolifarmEppoFillCrops($db);
	if ($result < 0) {
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		setEventMessages($langs->trans('EppoCropsFilled', $result), null, 'mesgs');
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}
if (($action == 'upload' || $action == 'import') && $file) {
	@set_time_limit(0);
	$importer = new DoliFarmEppoImporter($db);
	$result = $importer->importFile($file);
	if ($result < 0) {
		setEventMessages($importer->error, null, 'errors');
	} else {
		setEventMessages($langs->trans('EppoImportDone', $importer->nbcodes, $importer->nbnames, dol_print_date($db->jdate($importer->dateexport), 'day')), null, 'mesgs');
	}
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$title = "DolifarmSetup";

llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-dolifarm page-admin_eppo');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = dolifarmAdminPrepareHead();
print dol_get_fiche_head($head, 'eppo', $langs->trans($title), -1, 'dolifarm@dolifarm');

print '<span class="opacitymedium">'.$langs->trans('EppoPageHelp').'</span><br><br>';

// State of the data, with the attribution required by the EPPO Open Data Licence
$counts = array();
$total = 0;
$sql = "SELECT datatype, COUNT(*) as nb, SUM(is_active) as nbactive FROM ".$db->prefix()."dolifarm_eppo_code GROUP BY datatype ORDER BY datatype";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$counts[(string) $obj->datatype] = $obj;
		$total += (int) $obj->nb;
	}
	$db->free($resql);
} else {
	setEventMessages($db->lasterror(), null, 'errors');
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('EppoData').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('EppoSource').'</td><td>'.$langs->trans('EppoAttribution').' - <a href="https://www.eppo.int" target="_blank" rel="noopener noreferrer">www.eppo.int</a></td></tr>';
$datadate = getDolGlobalString('DOLIFARM_EPPO_DATA_DATE');
print '<tr class="oddeven"><td>'.$langs->trans('EppoDataDate').'</td><td>'.($datadate ? dol_print_date($db->jdate($datadate), 'day') : '<span class="opacitymedium">'.$langs->trans('EppoNotImported').'</span>').'</td></tr>';
$importdate = getDolGlobalString('DOLIFARM_EPPO_IMPORT_DATE');
print '<tr class="oddeven"><td>'.$langs->trans('EppoImportDate').'</td><td>'.($importdate ? dol_print_date((int) $importdate, 'dayhour') : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('EppoNbCodes').'</td><td>'.price($total, 0, $langs, 0, 0).'</td></tr>';
print '</table>';
print '</div>';

if ($counts) {
	print '<br><div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('EppoDatatype').'</td><td class="right">'.$langs->trans('EppoNbCodes').'</td><td class="right">'.$langs->trans('EppoNbActive').'</td></tr>';
	foreach ($counts as $datatype => $obj) {
		$label = $langs->trans('EppoDatatype'.$datatype);
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($datatype).' - '.dol_escape_htmltag($label == 'EppoDatatype'.$datatype ? '' : $label).'</td>';
		print '<td class="right">'.price($obj->nb, 0, $langs, 0, 0).'</td><td class="right">'.price($obj->nbactive, 0, $langs, 0, 0).'</td></tr>';
	}
	print '</table>';
	print '</div>';
}

// Crops without EPPO code: filled from the scientific name
if ($total > 0) {
	$nbcrops = 0;
	$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."dolifarm_crops WHERE (eppo_code IS NULL OR eppo_code = '') AND entity IN (".getEntity('dolifarmcrops').")";
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$nbcrops = (int) $obj->nb;
	}
	print '<br>';
	print load_fiche_titre($langs->trans('EppoCropsTitle'), '', '');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="fillcrops">';
	print $langs->trans('EppoCropsWithoutCode', $nbcrops).' ';
	if ($nbcrops > 0) {
		print '<input type="submit" class="button small reposition" value="'.$langs->trans('EppoCropsFill').'">';
	}
	print '</form>';
}

// Import
print '<br>';
print load_fiche_titre($langs->trans('EppoImport'), '', '');
print '<span class="opacitymedium">'.$langs->trans('EppoImportHelp', ini_get('upload_max_filesize'), ini_get('post_max_size')).'</span><br><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload">';
print '<input type="file" name="eppofile" accept=".xml,.gz"> ';
print '<input type="submit" class="button small reposition" value="'.$langs->trans('EppoUploadAndImport').'">';
print '</form>';

$present = '';
foreach (array('fullcodes.xml.gz', 'fullcodes.xml') as $tmpname) {
	if (empty($present) && file_exists($eppodir.'/'.$tmpname)) {
		$present = $tmpname;
	}
}
if ($present) {
	print '<br><form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="import">';
	print $langs->trans('EppoFilePresent', $present, dol_print_date(dol_filemtime($eppodir.'/'.$present), 'dayhour')).' ';
	print '<input type="submit" class="button small reposition" value="'.$langs->trans('EppoImportAgain').'">';
	print '</form>';
}

print '<br><span class="opacitymedium">'.$langs->trans('EppoImportCli').'</span><br>';
print '<code>php '.dol_escape_htmltag(dol_buildpath('/dolifarm/scripts/eppo_import.php', 0)).' /path/fullcodes.xml.gz</code>';

print dol_get_fiche_end();

llxFooter();
$db->close();
