<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
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
 *  \file       dolifarmindex.php
 *  \ingroup    dolifarm
 *  \brief      Area of doliFarm (top menu): dashboard of one farm or of all the farms the user can see. Situation of
 *              the three areas of the work, setup to complete, alerts, expiry dates, activity of the campaign, farms,
 *              external databases (docs/dashboard_progetto.md).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
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

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolifarm/lib/dolifarm_area.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "boxes", "errors", "companies"));

if (!isModEnabled('dolifarm')) {
	accessforbidden('Module not enabled');
}

// Scope: one farm (single-farm mode, or chosen) or all the farms the user can see
$multifarm = (bool) getDolGlobalInt('DOLIFARM_MULTIFARM');
$sale = ($multifarm ? dolifarmSaleFilterValue() : 0);
$farms = dolifarmAreaFarms($db, $user, $sale);
$socid = GETPOSTINT('socid');
if ($socid > 0 && !isset($farms[$socid])) {
	$socid = 0;
}
if (!$multifarm || count($farms) == 1) {
	$socid = (int) array_key_first($farms);
}
$scope = ($socid > 0 ? array($socid) : array_keys($farms));
$baseurl = $_SERVER['PHP_SELF'].($sale ? '?search_sale='.$sale : '');

$canread = $user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read');

/*
 * View
 */

$title = $langs->trans("DoliFarmArea");
llxHeader("", $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-index');

// Choice of the scope and filter on the sales representative (multi-farm mode)
$morehtmlright = '';
if ($multifarm && count($farms) + ($sale ? 1 : 0) > 1) {
	$morehtmlright .= '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="inline-block">';
	$morehtmlright .= dolifarmSaleFilterHtml($sale);
	$morehtmlright .= '<div class="divsearchfield">'.img_picto('', 'company', 'class="pictofixedwidth"');
	$morehtmlright .= '<select name="socid" id="socid" class="flat maxwidth250" onchange="this.form.submit()">';
	$morehtmlright .= '<option value="0">'.$langs->trans('AreaAllFarms').' ('.count($farms).')</option>';
	foreach ($farms as $id => $name) {
		$morehtmlright .= '<option value="'.$id.'"'.($id == $socid ? ' selected' : '').'>'.dol_escape_htmltag($name).'</option>';
	}
	$morehtmlright .= '</select>'.ajax_combobox('socid').'</div>';
	$morehtmlright .= '<input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Refresh')).'">';
	$morehtmlright .= '</form>';
}
print load_fiche_titre($title, $morehtmlright, dolifarm_get_fa_icon('dolifarm'));

if (empty($farms) && !$sale) {
	print dolifarmNoManagedFarmWarning();
}

if ($canread) {
	$areas = dolifarmAreaSituation($db, $user, $langs, $scope);
	if ($socid > 0) {
		$soc = new Societe($db);
		$soc->fetch($socid);
		$situationtitle = $langs->trans('AreaBoxTitleFarm').': '.$soc->name;
	} else {
		$situationtitle = $langs->trans('AreaBoxTitleFarms').' ('.count($farms).')';
	}

	// Situation of the three areas, full width
	print dolifarmAreaSituationHtml($areas, $situationtitle, $langs);

	print '<div class="fichecenter"><div class="fichehalfleft">';
	// Setup to complete (only when something is left), expiry dates
	print dolifarmAreaConfigHtml($areas, $langs);
	include_once dol_buildpath('/dolifarm/core/boxes/dolifarm_Expiry.php', 0);
	$box = new dolifarm_Expiry($db, '');
	if ($socid > 0) {
		$box->filters = array('fk_soc' => $socid);
	}
	$box->loadBox(10);
	$box->showBox();
	print '</div><div class="fichehalfright">';
	// Alerts, activity of the campaign
	print dolifarmAreaAlertsHtml(dolifarmAreaAlerts($db, $user, $langs, $scope), $langs);
	print dolifarmAreaActivityHtml(dolifarmAreaActivity($db, $user, $langs, $scope), $areas['notebook']['work'], $langs);
	print '</div></div>';
	print '<div class="clearboth"></div>';

	// Farms, only for the scope "all the farms"
	if ($socid <= 0 && count($farms) > 1) {
		print dolifarmAreaFarmsHtml(dolifarmAreaFarmRows($db, $user, $langs, $farms), $baseurl, $langs);
	}
}

// External databases: for everybody, the links to the setup only for the administrators
print dolifarmAreaExternalHtml(dolifarmAreaExternal($db, $user, $langs), $user, $langs);

// Widgets of the module still useful here: monitoring of the production plans, top planned products (doliTrace)
if (isModEnabled('dolitrace')) {
	print '<div class="fichecenter"><div class="fichehalfleft">';
	foreach (array('dolifarm_CropPlansMonitoring', 'dolifarmwidgettopplannedproducts') as $i => $class) {
		if ($i == 1) {
			print '</div><div class="fichehalfright">';
		}
		include_once dol_buildpath('/dolifarm/core/boxes/'.$class.'.php', 0);
		if (class_exists($class)) {
			$box = new $class($db, '');
			$box->loadBox(5);
			$box->showBox();
		}
	}
	print '</div></div>';
}

// The boxes of this page are not movable nor closable
print '<style>.page-index .boxhandle, .page-index .boxclose { display: none !important; } .page-index .box { cursor: default !important; }</style>';

llxFooter();
$db->close();
