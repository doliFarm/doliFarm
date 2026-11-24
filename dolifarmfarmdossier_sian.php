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
 * \file       dolifarmfarmdossier_sian.php
 * \ingroup    dolifarm
 * \brief      Tab of a farm dossier with the data read from the SIAN: graphic cultivation plan (islands and fields)
 *             and documents of the paper dossier with their expiry
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
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
dol_include_once('/dolifarm/class/dolifarmdossierdoc.class.php');
dol_include_once('/dolifarm/lib/dolifarm_farmdossier.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "companies"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$docid = GETPOSTINT('docid');

$object = new DoliFarmFarmDossier($db);

// Link from a document (widget of the expiries): its dossier
if ($docid > 0 && $id <= 0) {
	$doc = new DoliFarmDossierDoc($db);
	if ($doc->fetch($docid) > 0) {
		$id = (int) $doc->fk_dossier;
	}
}
if ($id > 0 || $ref) {
	$object->fetch($id, $ref);
	$id = $object->id;
}

// Security check
if (!isModEnabled('dolifarm')) {
	accessforbidden('Module not enabled');
}
restrictedArea($user, 'dolifarm', 0, $object->table_element, $object->element, 'fk_soc', 'rowid');
if (!$user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read')) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden();
}
// Farm of the object: users restricted to their third parties (sales representatives)
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dolifarmCheckSocAccess($user, $object);


/*
 * View
 */

$title = $object->ref." - ".$langs->trans('DossierSianTab');

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-card-sian');

$head = dolifarmfarmdossierPrepareHead($object);
print dol_get_fiche_head($head, 'sian', $langs->trans("DoliFarmFarmDossier"), -1, $object->picto);

dolifarmFarmDossierBanner($object);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Graphic cultivation plan, last campaign first
$sql = "SELECT t.campaign, t.island, t.field_ref, t.land_use_code, t.land_use, t.destination, t.land_use_detail, t.quality, t.variety,";
$sql .= " t.size, t.date_start, t.date_end, t.maintenance, t.production_method";
$sql .= " FROM ".MAIN_DB_PREFIX."dolifarm_dossierpcg as t";
$sql .= " WHERE t.fk_dossier = ".((int) $object->id)." AND t.entity IN (".getEntity('dolifarmfarmdossier').")";
$sql .= " ORDER BY t.campaign DESC, t.island ASC, t.field_ref ASC, t.rowid ASC";
$resql = $db->query($sql);

print load_fiche_titre($langs->trans('DossierGraphicPlan'), '', 'fa-map');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("DossierCampaign").'</td>';
print '<td>'.$langs->trans("DossierIsland").'</td>';
print '<td>'.$langs->trans("DossierSianField").'</td>';
print '<td>'.$langs->trans("DossierLandUse").'</td>';
print '<td>'.$langs->trans("DossierLandUseDetails").'</td>';
print '<td class="right">'.$langs->trans("Surface").' (ha)</td>';
print '<td class="center">'.$langs->trans("Period").'</td>';
print '<td class="center">'.$langs->trans("DossierMaintenance").'</td>';
print '</tr>';

$num = 0;
$totals = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$num++;
		$totals[(string) $obj->campaign] = ($totals[(string) $obj->campaign] ?? 0) + (float) $obj->size;
		$details = array_filter(array((string) $obj->destination, (string) $obj->land_use_detail, (string) $obj->quality, (string) $obj->variety), 'strlen');
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag((string) $obj->campaign).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->island).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->field_ref).'</td>';
		print '<td class="tdoverflowmax250" title="'.dol_escape_htmltag(trim($obj->land_use_code.' '.$obj->land_use)).'">'.dol_escape_htmltag(trim($obj->land_use_code.' '.$obj->land_use)).'</td>';
		print '<td class="tdoverflowmax300 small" title="'.dol_escape_htmltag(implode(' / ', $details)).'">'.dol_escape_htmltag(implode(' / ', $details)).'</td>';
		print '<td class="right nowraponall">'.price((float) $obj->size, 0, $langs, 0, 4, 4).'</td>';
		print '<td class="center nowraponall">'.dol_print_date($db->jdate($obj->date_start), 'day').($obj->date_end ? ' - '.dol_print_date($db->jdate($obj->date_end), 'day') : '').'</td>';
		print '<td class="center">'.dol_escape_htmltag((string) $obj->maintenance).'</td>';
		print '</tr>';
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}
if ($num == 0) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('DossierGraphicPlanNone').'</span></td></tr>';
}
foreach ($totals as $campaign => $total) {
	print '<tr class="liste_total"><td colspan="5">'.$langs->trans('Total').' '.dol_escape_htmltag($campaign).'</td>';
	print '<td class="right nowraponall">'.price($total, 0, $langs, 0, 4, 4).'</td><td colspan="2"></td></tr>';
}
print '</table>';
print '</div>';

// Documents of the paper dossier
$sql = "SELECT t.rowid, t.label, t.section, t.protocol, t.date_signature, t.date_start, t.date_end, t.status";
$sql .= " FROM ".MAIN_DB_PREFIX."dolifarm_dossierdoc as t";
$sql .= " WHERE t.fk_dossier = ".((int) $object->id)." AND t.entity IN (".getEntity('dolifarmfarmdossier').")";
$sql .= " ORDER BY t.date_end IS NULL, t.date_end ASC, t.label ASC";
$resql = $db->query($sql);

print '<br>';
print load_fiche_titre($langs->trans('DossierDocuments'), '', 'fa-file-contract');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("DossierDocType").'</td>';
print '<td>'.$langs->trans("DossierDocSection").'</td>';
print '<td>'.$langs->trans("DossierDocProtocol").'</td>';
print '<td class="center">'.$langs->trans("DossierDocDateSignature").'</td>';
print '<td class="center">'.$langs->trans("DossierDocDateStart").'</td>';
print '<td class="center">'.$langs->trans("DossierDocDateEnd").'</td>';
print '</tr>';
$num = 0;
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$num++;
		$dateend = $db->jdate($obj->date_end);
		print '<tr class="oddeven"'.($docid == (int) $obj->rowid ? ' style="font-weight: bold"' : '').'>';
		print '<td class="tdoverflowmax250">'.img_picto('', 'fa-file-contract', 'class="pictofixedwidth"').dol_escape_htmltag((string) $obj->label).'</td>';
		print '<td class="tdoverflowmax250">'.dol_escape_htmltag((string) $obj->section).'</td>';
		print '<td class="nowraponall">'.dol_escape_htmltag((string) $obj->protocol).'</td>';
		print '<td class="center nowraponall">'.dol_print_date($db->jdate($obj->date_signature), 'day').'</td>';
		print '<td class="center nowraponall">'.dol_print_date($db->jdate($obj->date_start), 'day').'</td>';
		print '<td class="center nowraponall">'.dol_print_date($dateend, 'day').($dateend ? dolifarmExpiryBadge($dateend) : '').'</td>';
		print '</tr>';
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}
if ($num == 0) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('DossierDocumentsNone').'</span></td></tr>';
}
print '</table>';
print '</div>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
