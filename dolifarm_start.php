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
 *  \file       dolifarm_start.php
 *  \ingroup    dolifarm
 *  \brief      Getting started: the steps of the first setup of a farm with their status, a link to do each step and
 *              its guided tour
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

dol_include_once('/dolifarm/lib/dolifarm_start.lib.php');

$langs->loadLangs(array('dolifarm@dolifarm', 'admin', 'companies'));

// Security check: internal users of the module
if (!isModEnabled('dolifarm') || $user->socid > 0) {
	accessforbidden();
}

$steps = dolifarmStartSteps($db, $user);
$required = 0;
$requireddone = 0;
foreach ($steps as $step) {
	if (!$step['optional']) {
		$required++;
		$requireddone += ($step['done'] ? 1 : 0);
	}
}
$percent = ($required > 0 ? (int) round($requireddone / $required * 100) : 100);

/*
 * View
 */

$title = $langs->trans('StartTitle');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-start');

$morehtmlright = '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?dolifarmtour=1').'"><span class="fa fa-question-circle paddingright"></span>'.$langs->trans('StartTourAll').'</a>';
print load_fiche_titre($title, $morehtmlright, 'fa-flag-checkered');

print '<p class="opacitymedium">'.$langs->trans('StartHelp').'</p>';
print '<div id="dolifarm-start-progress"><div class="dolifarm-start-progress"><span style="width: '.$percent.'%"></span></div>';
print '<div class="small marginbottomonly">'.$langs->trans('StartProgress', $requireddone, $required, $percent).'</div></div>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent" id="dolifarm-start-table">';
$group = '';
foreach ($steps as $step) {
	if ($step['group'] != $group) {
		$group = $step['group'];
		print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('StartGroup'.$group).'</th><th class="right">'.$langs->trans('Number').'</th><th></th></tr>';
	}
	print '<tr class="oddeven'.($step['done'] ? '' : ' dolifarm-start-todo').'">';
	print '<td class="center width25">';
	if ($step['done']) {
		print '<span class="fa fa-check-circle" style="color: #2e7d32" title="'.dol_escape_htmltag($langs->trans('StartDone')).'"></span>';
	} elseif ($step['optional']) {
		print '<span class="far fa-circle opacitymedium" title="'.dol_escape_htmltag($langs->trans('StartOptional')).'"></span>';
	} else {
		print '<span class="far fa-circle" style="color: #c62828" title="'.dol_escape_htmltag($langs->trans('StartToDo')).'"></span>';
	}
	print '</td>';
	print '<td><strong>'.$langs->trans($step['label']).'</strong>'.($step['optional'] ? ' <span class="opacitymedium small">('.$langs->trans('StartOptional').')</span>' : '');
	print '<br><span class="opacitymedium small">'.$langs->trans($step['help']).'</span></td>';
	print '<td class="right nowraponall">'.($step['count'] > 0 ? $step['count'] : '').'</td>';
	print '<td class="right nowraponall">';
	print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($step['url']).'">'.$langs->trans($step['done'] ? 'StartOpen' : 'StartDo').'</a>';
	if ($step['tour'] !== '') {
		print ' <a class="button buttongen smallpaddingimp" href="'.dol_escape_htmltag($step['url'].(str_contains($step['url'], '?') ? '&' : '?').'dolifarmtour=1').'" title="'.dol_escape_htmltag($langs->trans('TourButtonHelp')).'"><span class="fa fa-question-circle"></span></a>';
	}
	print '</td></tr>';
}
print '</table>';
print '</div>';

if (!empty($user->admin)) {
	print '<br><div class="info">'.$langs->trans('StartDemoHelp').'</div>';
}

llxFooter();
$db->close();
