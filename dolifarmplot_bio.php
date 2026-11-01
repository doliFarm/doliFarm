<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 *  \file       dolifarmplot_bio.php
 *  \ingroup    dolifarm
 *  \brief      Tab of the organic status of a plot: history of the periods (conventional, conversion, organic)
 *
 * The register (doliTrace) reads the status at the date of each record from this history.
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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
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
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/dolifarm/class/dolifarmplot.class.php');
dol_include_once('/dolifarm/lib/dolifarm_plot.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_bio.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "other"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$lineid = GETPOSTINT('lineid');

$object = new DoliFarmPlot($db);
$hookmanager->initHooks(array($object->element.'bio', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'

$permissiontoread = $user->hasRight('dolifarm', 'dolifarmplot', 'read');
$permissiontoadd = $user->hasRight('dolifarm', 'dolifarmplot', 'write');

if (!isModEnabled('dolifarm') || !$permissiontoread) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden('', 0, 0, 1);
}
if (!empty($object->entity) && !in_array((int) $object->entity, array_map('intval', explode(',', getEntity($object->element))))) {
	accessforbidden();
}
dolifarmCheckSocAccess($user, $object);

$selfurl = dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id]);
$statuses = dolifarmBioPeriodStatuses();
$reasons = dolifarmBioPeriodReasons();


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook) && $permissiontoadd) {
	if ($cancel) {
		$action = '';
	}

	if ($action == 'add') {
		$error = '';
		$datestart = dol_mktime(0, 0, 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear'), 'tzserver');
		$reason = GETPOST('reason', 'aZ09');
		$result = dolifarmBioAddPeriod(
			$db,
			$user,
			$object->id,
			GETPOST('status', 'aZ09'),
			$datestart,
			GETPOSTINT('conversion_years'),
			($reason == '-1' ? '' : $reason),
			GETPOST('reference', 'alphanohtml'),
			GETPOST('note', 'alphanohtml'),
			$error
		);
		if ($result > 0) {
			header('Location: '.$selfurl);
			exit;
		}
		setEventMessages($error, null, 'errors');
	}

	if ($action == 'close') {
		$error = '';
		$dateend = dol_mktime(0, 0, 0, GETPOSTINT('date_endmonth'), GETPOSTINT('date_endday'), GETPOSTINT('date_endyear'), 'tzserver');
		if (empty($dateend)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('DateEnd')), null, 'errors');
		} elseif (dolifarmBioClosePeriod($db, $user, $object->id, $dateend, $error) > 0) {
			header('Location: '.$selfurl);
			exit;
		} else {
			setEventMessages($error, null, 'errors');
		}
		$action = '';
	}

	if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes' && $lineid > 0) {
		$error = '';
		if (dolifarmBioDeleteLastPeriod($db, $user, $object->id, $lineid, $error) > 0) {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
			header('Location: '.$selfurl);
			exit;
		}
		setEventMessages($error, null, 'errors');
		$action = '';
	}
}


/*
 * View
 */

/**
 * Label of a code of the dictionary of the organic statuses
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Languages
 * @param	string		$code	Code
 * @return	string
 */
function dolifarmPlotBioLabel($db, $langs, $code)
{
	if ((string) $code === '') {
		return '';
	}
	$resql = $db->query("SELECT label FROM ".$db->prefix()."dolifarm_c_biostatus WHERE code = '".$db->escape($code)."'");
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if ($obj) {
			return $langs->trans($obj->label);
		}
	}
	return $code;
}

$form = new Form($db);

$title = $langs->trans('DoliFarmPlot').' - '.$langs->trans('BioTab');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-card_bio');

$head = dolifarmplotPrepareHead($object);
print dol_get_fiche_head($head, 'bio', $langs->trans("DoliFarmPlot"), -1, $object->picto);

if ($action == 'delete' && $lineid > 0) {
	print $form->formconfirm(dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id, 'lineid' => $lineid]), $langs->trans('BioDeletePeriod'), $langs->trans('BioConfirmDeletePeriod'), 'confirm_delete', '', 0, 1);
}

$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmplot_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

$history = dolifarmBioHistory($db, $object->id);
$now = dol_now();
$today = dolifarmBioStatusAt($db, $object->id, $now, (string) $object->fk_status_bio);
$last = (count($history) ? $history[count($history) - 1] : null);

print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('BioStatusToday').'</td><td>';
if ($today !== '') {
	print '<span class="badge badge-status'.(dolifarmBioIsOrganic($today) ? '4' : '0').'">'.dol_escape_htmltag(dolifarmPlotBioLabel($db, $langs, $today)).'</span>';
} else {
	print '<span class="opacitymedium">'.$langs->trans('BioStatusUnknown').'</span>';
}
print '</td></tr>';
if (!empty($object->date_conversion_start)) {
	print '<tr><td>'.$langs->trans('Dateconversionstart').'</td><td>'.dol_print_date($object->date_conversion_start, 'day').'</td></tr>';
}
if (!empty($object->date_conversion_end)) {
	print '<tr><td>'.$langs->trans('Dateconversionend').'</td><td>'.dol_print_date($object->date_conversion_end, 'day').'</td></tr>';
}
print '</table>';

print '<div class="opacitymedium margintoponly marginbottomonly">'.$langs->trans('BioHistoryHelp').'</div>';

// End of the conversion reached and no organic period yet
if ($last && $last->status == 'CONVERSION' && $last->date_end === null && dolifarmBioConversionEnd($last) < $now) {
	print info_admin($langs->trans('BioConversionEnded', dol_print_date(dolifarmBioConversionEnd($last), 'day')), 0, 0, 'warning');
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('BioPeriodStatus').'</td>';
print '<td class="center">'.$langs->trans('DateStart').'</td>';
print '<td class="center">'.$langs->trans('DateEnd').'</td>';
print '<td class="center">'.$form->textwithpicto($langs->trans('BioConversionYears'), $langs->trans('BioConversionYearsHelp')).'</td>';
print '<td>'.$langs->trans('BioReason').'</td>';
print '<td>'.$form->textwithpicto($langs->trans('BioReference'), $langs->trans('BioReferenceHelp')).'</td>';
print '<td>'.$langs->trans('Note').'</td>';
print '<td></td>';
print '</tr>';

foreach ($history as $period) {
	print '<tr class="oddeven">';
	print '<td>'.(isset($statuses[$period->status]) ? $langs->trans($statuses[$period->status]) : dol_escape_htmltag($period->status)).'</td>';
	print '<td class="center">'.dol_print_date($period->date_start, 'day').'</td>';
	print '<td class="center">';
	if ($period->date_end !== null) {
		print dol_print_date($period->date_end, 'day');
	} elseif ($period->status == 'CONVERSION') {
		print '<span class="opacitymedium">'.$langs->trans('BioPlannedEnd', dol_print_date(dolifarmBioConversionEnd($period), 'day')).'</span>';
	}
	print '</td>';
	print '<td class="center">'.($period->status == 'CONVERSION' ? (int) $period->conversion_years : '').'</td>';
	print '<td>';
	if ((string) $period->reason !== '') {
		print (isset($reasons[$period->reason]) ? $langs->trans($reasons[$period->reason]) : dol_escape_htmltag($period->reason));
	}
	print '</td>';
	print '<td>'.dol_escape_htmltag((string) $period->reference).'</td>';
	print '<td>'.dol_escape_htmltag((string) $period->note).'</td>';
	print '<td class="right nowraponall">';
	if ($permissiontoadd && $last && (int) $period->rowid == (int) $last->rowid) {
		print '<a class="reposition" href="'.dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id, 'action' => 'delete', 'lineid' => $period->rowid, 'token' => newToken()]).'">'.img_delete().'</a>';
	}
	print '</td>';
	print '</tr>';
}
if (empty($history)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('BioHistoryNone').'</span></td></tr>';
}
print '</table>';
print '</div>';

if ($permissiontoadd) {
	$liststatus = array();
	foreach ($statuses as $code => $key) {
		$liststatus[$code] = $langs->trans($key);
	}
	$listreason = array();
	foreach ($reasons as $code => $key) {
		$listreason[$code] = $langs->trans($key);
	}
	$poststatus = GETPOST('status', 'aZ09');
	$postyears = GETPOSTINT('conversion_years');
	$postdate = (GETPOSTISSET('date_startyear') ? dol_mktime(0, 0, 0, GETPOSTINT('date_startmonth'), GETPOSTINT('date_startday'), GETPOSTINT('date_startyear')) : -1);

	// New period
	print '<form method="POST" action="'.$selfurl.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	print '<input type="hidden" name="action" value="add">';
	print '<div class="div-table-responsive-no-min margintoponly">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="6">'.$langs->trans('BioAddPeriod').'</td></tr>';
	print '<tr class="oddeven">';
	print '<td>'.$form->selectarray('status', $liststatus, ($poststatus ? $poststatus : 'CONVERSION'), 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
	print '<td class="nowraponall">'.$langs->trans('DateStart').' '.$form->selectDate($postdate, 'date_start', 0, 0, 0, '', 1, 0).'</td>';
	print '<td class="nowraponall">'.$form->textwithpicto($langs->trans('BioConversionYears'), $langs->trans('BioConversionYearsHelp'));
	print ' <input type="number" min="1" max="3" step="1" name="conversion_years" class="width50" value="'.($postyears > 0 ? $postyears : dolifarmBioDefaultConversionYears($db, $object->id)).'"></td>';
	print '<td>'.$form->selectarray('reason', $listreason, GETPOST('reason', 'aZ09'), 1, 0, 0, '', 0, 0, 0, '', 'maxwidth200').'</td>';
	print '<td><input type="text" name="reference" class="minwidth100" maxlength="128" placeholder="'.dol_escape_htmltag($langs->trans('BioReference')).'" value="'.dol_escape_htmltag(GETPOST('reference', 'alphanohtml')).'">';
	print ' <input type="text" name="note" class="minwidth100" maxlength="255" placeholder="'.dol_escape_htmltag($langs->trans('Note')).'" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td>';
	print '<td class="center"><input type="submit" class="button buttongen small" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td>';
	print '</tr>';
	print '</table>';
	print '</div>';
	print '</form>';

	// Close the open period (exit from the control system, end of the conversion before a period BIO)
	if ($last && $last->date_end === null) {
		print '<form method="POST" action="'.$selfurl.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
		print '<input type="hidden" name="action" value="close">';
		print '<div class="margintoponly">'.$langs->trans('BioClosePeriod').' '.$form->selectDate(-1, 'date_end', 0, 0, 0, '', 1, 0);
		print ' <input type="submit" class="button buttongen small" value="'.dol_escape_htmltag($langs->trans('Close')).'"></div>';
		print '</form>';
	}
}

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
