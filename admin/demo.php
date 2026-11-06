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
 * \file    admin/demo.php
 * \ingroup dolifarm
 * \brief   Demo data of the suite from the setup: only for a demo instance, hidden unless DOLIFARM_DEMO_TOOLS is set
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

$langs->loadLangs(array("errors", "admin", "dolifarm@dolifarm"));

// Access control: administrators only, and only on an instance where the demo tools are enabled
if (!$user->admin) {
	accessforbidden();
}
$enabled = getDolGlobalInt('DOLIFARM_DEMO_TOOLS') > 0;

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');


/*
 * Actions
 */

$demolog = array();
$demoerrors = -1;
if ($action == 'confirm_load' && $confirm == 'yes' && $enabled) {
	dol_include_once('/dolifarm/class/dolifarmdemo.class.php');
	// About a minute: the page waits for the end, also if the browser gives up
	@set_time_limit(0);
	ignore_user_abort(true);
	dol_syslog('admin/demo.php demo data loaded by '.$user->login, LOG_NOTICE);
	$demo = new DoliFarmDemo($db, $user);
	$demo->echo = false;
	$demoerrors = $demo->run();
	$demolog = $demo->log;
	// The demo sets the language of the instance
	$langs->loadLangs(array("errors", "admin", "dolifarm@dolifarm"));
	if ($demoerrors) {
		setEventMessages($langs->trans('DemoDataErrors', $demoerrors), null, 'warnings');
	} else {
		setEventMessages($langs->trans('DemoDataDone'), null, 'mesgs');
	}
}


/*
 * View
 */

$form = new Form($db);

$title = "DolifarmSetup";
llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-dolifarm page-admin_demo');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = dolifarmAdminPrepareHead();
print dol_get_fiche_head($head, 'demo', $langs->trans($title), -1, 'dolifarm@dolifarm');

if ($action == 'load' && $enabled) {
	print $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('DemoDataLoad'), $langs->trans('DemoDataConfirm', getDolGlobalString('MAIN_INFO_SOCIETE_NOM')), 'confirm_load', '', 0, 1, 250, 600);
}

print '<span class="opacitymedium">'.$langs->trans('DemoDataHelp').'</span><br><br>';

if (!$enabled) {
	print info_admin($langs->trans('DemoDataDisabled', 'DOLIFARM_DEMO_TOOLS'), 0, 0, 'warning');
} else {
	print info_admin($langs->trans('DemoDataWarning'), 0, 0, 'warning');

	// What is in the instance now
	$counts = array(
		'ThirdParties' => "SELECT COUNT(*) as nb FROM ".$db->prefix()."societe WHERE entity IN (".getEntity('societe').")",
		'Products' => "SELECT COUNT(*) as nb FROM ".$db->prefix()."product WHERE entity IN (".getEntity('product').")",
		'DoliFarmFarmDossiers' => "SELECT COUNT(*) as nb FROM ".$db->prefix()."dolifarm_farmdossier WHERE entity IN (".getEntity('dolifarmfarmdossier').")",
	);
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('DemoDataInstance').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('CompanyName').'</td><td>'.dol_escape_htmltag(getDolGlobalString('MAIN_INFO_SOCIETE_NOM')).'</td></tr>';
	foreach ($counts as $label => $sql) {
		$resql = $db->query($sql);
		$obj = $resql ? $db->fetch_object($resql) : null;
		print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td>'.($obj ? (int) $obj->nb : '?').'</td></tr>';
	}
	print '</table>';
	print '</div>';

	print '<div class="tabsAction">';
	print dolGetButtonAction('', $langs->trans('DemoDataLoad'), 'danger', $_SERVER['PHP_SELF'].'?action=load&token='.newToken(), '', 1);
	print '</div>';
}

if (count($demolog)) {
	print load_fiche_titre($langs->trans('DemoDataLog'), '', '');
	print '<pre class="small" style="max-height: 600px; overflow: auto;">'.dol_escape_htmltag(implode("\n", $demolog)).'</pre>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
