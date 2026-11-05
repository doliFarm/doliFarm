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
 *  \file       dolifarmplot_cropplans.php
 *  \ingroup    dolifarm
 *  \brief      Tab "History of the production plans" of a plot: what was grown and done on the plot, season by season
 *
 * The production plans belong to doliTrace: the tab exists only when doliTrace is enabled.
 * The CSV keeps the values for the later analyses (rotations, nitrogen and copper, emissions).
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
dol_include_once('/dolifarm/lib/dolifarm_plothistory.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "other"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new DoliFarmPlot($db);
$hookmanager->initHooks(array($object->element.'cropplans', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'

if (!isModEnabled('dolifarm') || !$user->hasRight('dolifarm', 'dolifarmplot', 'read') || !dolifarmPlotHistoryAvailable($user)) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden('', 0, 0, 1);
}
if (!empty($object->entity) && !in_array((int) $object->entity, array_map('intval', explode(',', getEntity($object->element))))) {
	accessforbidden();
}
dolifarmCheckSocAccess($user, $object);

dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
$plans = dolifarmPlotCropPlanHistory($db, $langs, $object);


/*
 * Actions
 */

if ($action == 'csv') {
	$filename = dol_sanitizeFileName('production_plans_'.$object->ref.'_'.dol_print_date(dol_now(), '%Y%m%d')).'.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	print dolifarmPlotCropPlanHistoryCsv($db, $langs, $object, $plans);
	$db->close();
	exit;
}


/*
 * View
 */

$title = $langs->trans('DoliFarmPlot').' - '.$langs->trans('PlotCropPlansTab');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-card_cropplans');

$head = dolifarmplotPrepareHead($object);
print dol_get_fiche_head($head, 'cropplans', $langs->trans("DoliFarmPlot"), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmplot_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '</div>';
print dol_get_fiche_end();

$buttons = '';
if (count($plans)) {
	$buttons .= dolGetButtonAction('', $langs->trans('PlotCropPlansCsv'), 'default', dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id, 'action' => 'csv']), '', true);
}
if ($user->hasRight('dolitrace', 'dolitracecropplan', 'write')) {
	$url = dolBuildUrl(dol_buildpath('/dolitrace/dolitracecropplan_card.php', 1), ['action' => 'create', 'fk_soc' => $object->fk_soc, 'fk_plot' => $object->id,
		'backtopageforcancel' => dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id])]);
	$buttons .= dolGetButtonAction('', $langs->trans('PlotCropPlansNew'), 'default', $url, '', true);
}
print load_fiche_titre($langs->trans('PlotCropPlansTab'), $buttons, 'fa-history');

$area = dolifarmPlotHistoryArea($object);
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('PlotCropPlansHelp');
if ($area > 0) {
	print ' '.$langs->trans('PlotCropPlansArea', price($area, 0, $langs, 0, -1, 4));
}
print '</div>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="center">'.$langs->trans('PlotCropPlansSeason').'</td>';
print '<td>'.$langs->trans('PlotCropPlansPlan').'</td>';
print '<td>'.$langs->trans('PlotCropPlansCrop').'</td>';
print '<td class="center">'.$langs->trans('PlotCropPlansPeriod').'</td>';
print '<td>'.$langs->trans('PlotCropPlansBio').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansOperations').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansProtections').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansFertilizations').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansNitrogen').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansCopper').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansHarvested').'</td>';
print '<td class="right">'.$langs->trans('PlotCropPlansYield').'</td>';
print '<td class="right">'.$langs->trans('Status').'</td>';
print '</tr>';

$static = new DoliTraceCropplan($db);
$num = function ($value) use ($langs) {
	return price($value, 0, $langs, 0, -1, 2);
};
// Newest first on screen
foreach (array_reverse($plans) as $plan) {
	$static->id = $plan['id'];
	$static->ref = $plan['ref'];
	$static->label = $plan['label'];
	$static->status = $plan['status'];
	print '<tr class="oddeven">';
	print '<td class="center">'.((int) $plan['season']).'</td>';
	print '<td class="nowraponall">'.$static->getNomUrl(1).'</td>';
	print '<td>'.dol_escape_htmltag($plan['crop']);
	if ($plan['variety'] !== '') {
		print ' <span class="opacitymedium">'.dol_escape_htmltag($plan['variety']).'</span>';
	}
	if ($plan['family'] !== '') {
		print '<br><span class="opacitymedium small">'.dol_escape_htmltag($plan['family']).'</span>';
	}
	if ($plan['same_family'] !== '') {
		print ' '.img_warning($langs->trans('PlotCropPlansSameFamily', $plan['same_family']));
	}
	print '</td>';
	print '<td class="center nowraponall">'.dol_print_date($plan['date_start'], 'day').($plan['date_end'] ? ' - '.dol_print_date($plan['date_end'], 'day') : '').'</td>';
	print '<td>';
	if ($plan['bio'] !== '') {
		print '<span class="badge badge-status'.(dolifarmBioIsOrganic($plan['bio']) ? '4' : '0').'">'.dol_escape_htmltag(dolifarmPlotHistoryBioLabel($db, $langs, $plan['bio'])).'</span>';
	}
	print '</td>';
	print '<td class="right">'.($plan['operations'] ?: '').'</td>';
	print '<td class="right">'.($plan['protections'] ?: '').'</td>';
	print '<td class="right">'.($plan['fertilizations'] ?: '').'</td>';
	print '<td class="right">'.($plan['n_applied'] > 0 ? $num($plan['n_applied']) : '').'</td>';
	print '<td class="right">'.($plan['cu_applied'] > 0 ? $num($plan['cu_applied']) : '').'</td>';
	print '<td class="right nowraponall">';
	foreach ($plan['harvested'] as $unit => $qty) {
		print '<div>'.$num($qty).' '.dol_escape_htmltag((string) $unit).'</div>';
	}
	print '</td>';
	print '<td class="right nowraponall">';
	foreach ($plan['yield_ha'] as $unit => $qty) {
		print '<div>'.$num($qty).' '.dol_escape_htmltag((string) $unit).'/ha</div>';
	}
	print '</td>';
	print '<td class="right">'.$static->getLibStatut(5).'</td>';
	print '</tr>';
}
if (!count($plans)) {
	print '<tr class="oddeven"><td colspan="13"><span class="opacitymedium">'.$langs->trans('PlotCropPlansNone').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
