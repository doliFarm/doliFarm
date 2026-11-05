<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    dolifarm_farmsummary.php
 * \ingroup dolifarm
 * \brief   Tab "Farm" of a managed farm (third party): summary of dossier, land, means, expiries and, with doliTrace, plans and records
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farmsummary.lib.php');

$langs->loadLangs(array('dolifarm@dolifarm', 'companies', 'other'));
$withtrace = isModEnabled('dolitrace');
if ($withtrace) {
	$langs->load('dolitrace@dolitrace');
}

$socid = GETPOSTINT('socid');
if ($user->socid > 0) {
	$socid = $user->socid;
}

$object = new Societe($db);
$hookmanager->initHooks(array('thirdpartydolifarmsummary', 'globalcard'));

if (!isModEnabled('dolifarm') || !$user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read')) {
	accessforbidden();
}
if ($socid <= 0 || $object->fetch($socid) <= 0) {
	accessforbidden('', 0, 0, 1);
}
restrictedArea($user, 'societe', $socid, '&societe');


/*
 * View
 */

$form = new Form($db);
$ha = function ($value) {
	return price2num($value, 4) != 0 ? price($value, 0, '', 1, 4, 4) : '0';
};

/**
 * Section title with an optional link to the full list
 *
 * @param	string	$title		Title (already translated)
 * @param	string	$picto		Picto
 * @param	string	$url		Url of the list, '' for none
 * @param	int		$colspan	Columns of the table
 * @return	void
 */
$printTitle = function ($title, $picto, $url = '', $colspan = 2) use ($langs) {
	print '<tr class="liste_titre"><th colspan="'.((int) $colspan - ($url ? 1 : 0)).'">'.img_picto('', $picto, 'class="pictofixedwidth"').$title.'</th>';
	if ($url) {
		print '<th class="right"><a href="'.$url.'">'.$langs->trans('FullList').'</a></th>';
	}
	print '</tr>';
};

/**
 * Rows of objects with some of their fields
 *
 * @param	CommonObject	$obj		Empty object of the class
 * @param	int[]			$ids		Ids to show
 * @param	string[]		$fields		Fields to show after the ref
 * @param	int				$colspan	Columns of the table
 * @return	void
 */
$printObjects = function ($obj, $ids, $fields, $colspan) use ($langs) {
	if (empty($ids)) {
		print '<tr class="oddeven"><td colspan="'.((int) $colspan).'"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		return;
	}
	foreach ($ids as $id) {
		if ($obj->fetch($id) <= 0) {
			continue;
		}
		if ((string) $obj->ref === '') {
			// Records imported without a ref: the link still opens the card
			$obj->ref = '#'.$obj->id;
		}
		print '<tr class="oddeven">';
		print '<td class="nowraponall tdoverflowmax150">'.$obj->getNomUrl(1).'</td>';
		foreach ($fields as $key) {
			$css = (in_array($obj->fields[$key]['type'], array('date', 'datetime', 'double(24,8)', 'real', 'price')) ? 'nowraponall' : 'tdoverflowmax150');
			print '<td class="'.$css.'">'.$obj->showOutputField($obj->fields[$key], $key, $obj->$key, '').'</td>';
		}
		print '<td class="right nowraponall">'.$obj->getLibStatut(5).'</td>';
		print '</tr>';
	}
};

$title = $object->name.' - '.$langs->trans('FarmSummaryTab');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-thirdparty_farmsummary');

$head = societe_prepare_head($object);
print dol_get_fiche_head($head, 'dolifarmfarm', $langs->trans('ThirdParty'), -1, 'company');

$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

print '<div class="underbanner clearboth"></div>';
print dol_get_fiche_end();

if (!dolifarmIsManagedFarm($socid)) {
	print info_admin($langs->trans('FarmSummaryNotManaged'), 0, 0, 'warning');
}

print '<div class="fichecenter"><div class="fichehalfleft">';

// Dossier
$dossierid = dolifarmGetFarmDossier($db, $socid);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
$printTitle($langs->trans('DoliFarmFarmDossier'), 'fa-folder-open', dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1).'?search_fk_soc='.$socid);
if ($dossierid > 0) {
	$dossier = new DoliFarmFarmDossier($db);
	$dossier->fetch($dossierid);
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.$dossier->getNomUrl(1).' '.$dossier->getLibStatut(5).'</td></tr>';
	foreach (array('paying_agency', 'sheet_number', 'date_signature', 'date_validity_end', 'fk_soc_caa', 'ote_code', 'fk_soc_bio_body', 'date_bio_membership') as $key) {
		if ($dossier->$key === null || $dossier->$key === '' || (in_array($key, array('fk_soc_caa', 'fk_soc_bio_body')) && (int) $dossier->$key <= 0)) {
			continue;
		}
		$value = $dossier->showOutputField($dossier->fields[$key], $key, $dossier->$key, '');
		if ($key == 'date_validity_end') {
			$value .= ' '.dolifarmExpiryBadge($dossier->$key, 0);
		}
		print '<tr class="oddeven"><td>'.$langs->trans($dossier->fields[$key]['label']).'</td><td>'.$value.'</td></tr>';
	}
	print '<tr class="oddeven"><td colspan="2" class="right"><a href="'.dol_buildpath('/dolifarm/dolifarmfarmdossier_document.php', 1).'?id='.$dossierid.'">'.img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans('FarmSummaryDossierPdf').'</a></td></tr>';
} else {
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('FarmSummaryNoDossier').'</span>';
	if ($user->hasRight('dolifarm', 'dolifarmfarmdossier', 'write')) {
		print ' <a href="'.dol_buildpath('/dolifarm/dolifarmfarmdossier_card.php', 1).'?action=create&fk_soc='.$socid.'">'.img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('New').'</a>';
	}
	print '</td></tr>';
}
print '</table></div><br>';

// Land
if ($user->hasRight('dolifarm', 'dolifarmplot', 'read')) {
	$land = dolifarmFarmSummaryLand($db, $langs, $socid);
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$printTitle($langs->trans('FarmSummaryLand'), 'fa-map', dol_buildpath('/dolifarm/dolifarmplot_list.php', 1).'?search_fk_soc='.$socid, 3);
	print '<tr class="oddeven"><td>'.$langs->trans('FarmSummaryNbPlots').'</td><td class="right" colspan="2">'.$land['nb'].'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('FarmSummaryCadastral').'</td><td class="right" colspan="2">'.$ha($land['cadastral']).' ha</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('FarmSummaryConducted').'</td><td class="right" colspan="2">'.$ha($land['conducted']).' ha</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('FarmSummarySau').'</td><td class="right" colspan="2"><strong>'.$ha($land['sau']).' ha</strong></td></tr>';
	foreach (array('bytitle' => 'FarmSummaryByTitle', 'bybio' => 'FarmSummaryByBio') as $group => $label) {
		if (empty($land[$group])) {
			continue;
		}
		print '<tr class="liste_titre"><td>'.$langs->trans($label).'</td><td class="right">'.$langs->trans('FarmSummaryNbPlotsShort').'</td><td class="right">'.$langs->trans('FarmSummarySauShort').'</td></tr>';
		foreach ($land[$group] as $name => $row) {
			$share = ($land['sau'] > 0 ? ' <span class="opacitymedium">('.round(100 * $row['sau'] / $land['sau']).'&nbsp;%)</span>' : '');
			print '<tr class="oddeven"><td class="tdoverflowmax200">'.dol_escape_htmltag($name).'</td><td class="right">'.$row['nb'].'</td><td class="right nowraponall">'.$ha($row['sau']).' ha'.$share.'</td></tr>';
		}
	}
	print '</table></div><br>';
}

// Machines, buildings, livestock, operators
$means = array(
	array('dolifarmmachine', 'dolifarm_machine', 'DoliFarmMachines', 'fa-tractor', '/dolifarm/dolifarmmachine_list.php'),
	array('dolifarmbuilding', 'dolifarm_building', 'FarmSummaryBuildings', 'fa-warehouse', '/dolifarm/dolifarmbuilding_list.php'),
	array('dolifarmlivestock', 'dolifarm_livestock', 'FarmSummaryLivestock', 'fa-horse', '/dolifarm/dolifarmlivestock_list.php'),
	array('dolifarmoperator', 'dolifarm_operator', 'FarmSummaryOperators', 'fa-user-tie', '/dolifarm/dolifarmoperator_list.php'),
);
$rows = '';
foreach ($means as $mean) {
	if (!$user->hasRight('dolifarm', $mean[0], 'read')) {
		continue;
	}
	$nb = dolifarmFarmSummaryCount($db, $mean[1], $mean[0], $socid, ' AND (t.status IS NULL OR t.status <> 9)');
	$value = (string) $nb;
	if ($mean[0] == 'dolifarmlivestock' && $nb > 0) {
		$value .= ' <span class="opacitymedium">('.$langs->trans('FarmSummaryHeads', dolifarmFarmSummaryHeads($db, $socid)).')</span>';
	}
	$rows .= '<tr class="oddeven"><td>'.img_picto('', $mean[3], 'class="pictofixedwidth"').'<a href="'.dol_buildpath($mean[4], 1).'?search_fk_soc='.$socid.'">'.$langs->trans($mean[2]).'</a></td><td class="right">'.$value.'</td></tr>';
}
if ($rows) {
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	$printTitle($langs->trans('FarmSummaryMeans'), 'fa-tools');
	print $rows;
	print '</table></div><br>';
}

// Expiries
$expiries = dolifarmExpiryList($db, $user, 0, array('fk_soc' => $socid));
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
$printTitle($langs->trans('FarmSummaryExpiries', dolifarmExpiryDays()).' ('.count($expiries).')', 'fa-calendar-times', '', 4);
if (empty($expiries)) {
	print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('ExpiryNone', dolifarmExpiryDays()).'</span></td></tr>';
}
$objects = array();
foreach (array_slice($expiries, 0, 10) as $row) {
	$def = $row['def'];
	if (!isset($objects[$def['class']])) {
		if ($def['element'] != 'societe') {
			dol_include_once('/dolifarm/class/'.$def['element'].'.class.php');
		}
		$objects[$def['class']] = new $def['class']($db);
	}
	$obj = $objects[$def['class']];
	$obj->id = $row['id'];
	$obj->ref = $row['ref'];
	$obj->label = $row['label'];
	if ($def['element'] == 'societe') {
		$obj->name = $row['ref'];
		$obj->nom = $row['ref'];
	}
	print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($row['date'], 'day').'</td>';
	print '<td class="tdoverflowmax150">'.$langs->trans($def['label']).'</td>';
	print '<td class="tdoverflowmax150">'.$obj->getNomUrl(1).'</td>';
	print '<td class="right nowraponall">'.dolifarmExpiryBadge($row['date'], $def['milestone']).'</td></tr>';
}
if (count($expiries) > 10) {
	print '<tr class="oddeven"><td colspan="4" class="center"><span class="opacitymedium">'.$langs->trans('ExpiryMore', count($expiries) - 10).'</span></td></tr>';
}
print '</table></div>';

print '</div><div class="fichehalfright">';

if ($withtrace) {
	// Production plans of the last season
	if ($user->hasRight('dolitrace', 'dolitracecropplan', 'read')) {
		dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
		$season = dolifarmFarmSummaryLastSeason($db, $socid);
		$ids = array();
		if ($season !== '') {
			$ids = dolifarmFarmSummaryIds($db, "SELECT t.rowid FROM ".$db->prefix()."dolitrace_cropplan as t WHERE t.fk_soc = ".((int) $socid)
				." AND t.entity IN (".getEntity('dolitracecropplan').") AND t.season = '".$db->escape($season)."' ORDER BY t.date_start DESC, t.rowid DESC".$db->plimit(10));
		}
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		$printTitle($langs->trans('FarmSummaryPlans').($season !== '' ? ' - '.dol_escape_htmltag($season) : ''), 'fa-seedling',
			dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1).'?search_fk_soc='.$socid.($season !== '' ? '&search_season='.urlencode($season) : ''), 4);
		$printObjects(new DoliTraceCropplan($db), $ids, array('fk_plot', 'fk_crop'), 4);
		print '</table></div><br>';
	}

	// Last harvests (farm of the production plan)
	if ($user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
		dol_include_once('/dolitrace/class/dolitraceharvest.class.php');
		$ids = dolifarmFarmSummaryIds($db, "SELECT h.rowid FROM ".$db->prefix()."dolitrace_harvest as h"
			." INNER JOIN ".$db->prefix()."dolitrace_cropplan as cp ON cp.rowid = h.fk_cropplan"
			." WHERE cp.fk_soc = ".((int) $socid)." AND h.entity IN (".getEntity('dolitraceharvest').")"
			." ORDER BY h.date_harvest DESC, h.rowid DESC".$db->plimit(10));
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		$printTitle($langs->trans('FarmSummaryHarvests'), 'fa-tractor', dol_buildpath('/dolitrace/dolitraceharvest_list.php', 1), 5);
		$printObjects(new DoliTraceHarvest($db), $ids, array('date_harvest', 'fk_product_out', 'batch_number'), 5);
		print '</table></div><br>';
	}

	// Last field operations (farm of the production plan)
	if ($user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
		dol_include_once('/dolitrace/class/dolitraceoperation.class.php');
		$ids = dolifarmFarmSummaryIds($db, "SELECT o.rowid FROM ".$db->prefix()."dolitrace_operation as o"
			." INNER JOIN ".$db->prefix()."dolitrace_cropplan as cp ON cp.rowid = o.fk_cropplan"
			." WHERE cp.fk_soc = ".((int) $socid)." AND o.entity IN (".getEntity('dolitraceoperation').")"
			." ORDER BY o.date_op DESC, o.rowid DESC".$db->plimit(5));
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		$printTitle($langs->trans('FarmSummaryOperations'), 'fa-tasks', dol_buildpath('/dolitrace/dolitraceoperation_list.php', 1), 4);
		$printObjects(new DoliTraceOperation($db), $ids, array('date_op', 'label'), 4);
		print '</table></div><br>';
	}

	// Last treatments and fertilizations
	if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
		dol_include_once('/dolitrace/class/dolitracetreatment.class.php');
		$ids = dolifarmFarmSummaryIds($db, "SELECT t.rowid FROM ".$db->prefix()."dolitrace_treatment as t"
			." WHERE t.fk_soc = ".((int) $socid)." AND t.entity IN (".getEntity('dolitracetreatment').")"
			." ORDER BY t.date_start DESC, t.rowid DESC".$db->plimit(5));
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		$printTitle($langs->trans('FarmSummaryTreatments'), 'fa-spray-can', dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?search_fk_soc='.$socid, 4);
		$printObjects(new DoliTraceTreatment($db), $ids, array('date_start', 'kind'), 4);
		print '</table></div><br>';
	}

	// Organic inspections
	if ($user->hasRight('dolitrace', 'dolitracebioinspection', 'read')) {
		dol_include_once('/dolitrace/class/dolitracebioinspection.class.php');
		$ids = dolifarmFarmSummaryIds($db, "SELECT t.rowid FROM ".$db->prefix()."dolitrace_bioinspection as t"
			." WHERE t.fk_soc = ".((int) $socid)." AND t.entity IN (".getEntity('dolitracebioinspection').")"
			." ORDER BY t.date_inspection DESC, t.rowid DESC".$db->plimit(3));
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		$printTitle($langs->trans('FarmSummaryInspections'), 'fa-clipboard-check', dol_buildpath('/dolitrace/dolitracebioinspection_list.php', 1).'?socid='.$socid, 4);
		$printObjects(new DoliTraceBioInspection($db), $ids, array('date_inspection', 'outcome'), 4);
		print '</table></div>';
	}
} else {
	print '<div class="opacitymedium">'.$langs->trans('FarmSummaryNoTrace').'</div>';
}

print '</div></div>';
print '<div class="clearboth"></div>';

llxFooter();
$db->close();
