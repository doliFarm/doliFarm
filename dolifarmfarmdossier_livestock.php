<?php
/* Copyright (C) 2026       SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       dolifarmfarmdossier_livestock.php
 * \ingroup    dolifarm
 * \brief      Tab of a farm dossier with its livestock (consistenza zootecnica)
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
dol_include_once('/dolifarm/class/dolifarmlivestock.class.php');
dol_include_once('/dolifarm/lib/dolifarm_farmdossier.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "companies"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');

$object = new DoliFarmFarmDossier($db);
$child = new DoliFarmLivestock($db);

if ($id > 0 || $ref) {
	$object->fetch($id, $ref);
	$id = $object->id;
}

// Security check
if (!isModEnabled('dolifarm')) {
	accessforbidden('Module not enabled');
}
restrictedArea($user, 'dolifarm', $object->id, $object->table_element, $object->element, 'fk_soc', 'rowid');
if (!$user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read') || !$user->hasRight('dolifarm', 'dolifarmlivestock', 'read')) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden();
}


/*
 * View
 */

$form = new Form($db);
$title = $object->ref." - ".$langs->trans('DoliFarmLivestocks');

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-card-livestock');

$head = dolifarmfarmdossierPrepareHead($object);
print dol_get_fiche_head($head, 'livestock', $langs->trans("DoliFarmFarmDossier"), -1, $object->picto);

$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

$sql = "SELECT t.rowid, t.ref, t.label, t.bdn_code, t.fk_species, t.nb_heads, t.date_start, t.date_end, t.status, s.label as species_label";
$sql .= " FROM ".MAIN_DB_PREFIX."dolifarm_livestock as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."dolifarm_c_livestock_species as s ON s.code = t.fk_species";
$sql .= " WHERE t.fk_dossier = ".((int) $object->id);
$sql .= " AND t.entity IN (".getEntity($child->element).")";
$sql .= " ORDER BY t.status ASC, t.ref ASC";

$resql = $db->query($sql);
if ($resql) {
	$newbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/dolifarm/dolifarmlivestock_card.php', 1).'?action=create&fk_dossier='.((int) $object->id).'&fk_soc='.((int) $object->fk_soc).'&backtopage='.urlencode($_SERVER['PHP_SELF'].'?id='.$object->id), '', $user->hasRight('dolifarm', 'dolifarmlivestock', 'write'));
	print load_fiche_titre($langs->trans('DoliFarmLivestocks'), $newbutton, $child->picto);

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Ref").'</td>';
	print '<td>'.$langs->trans("Label").'</td>';
	print '<td>'.$langs->trans("BdnCode").'</td>';
	print '<td>'.$langs->trans("LivestockSpecies").'</td>';
	print '<td class="right">'.$langs->trans("NbHeads").'</td>';
	print '<td class="center">'.$langs->trans("LivestockPeriod").'</td>';
	print '<td class="right">'.$langs->trans("Status").'</td>';
	print '</tr>';

	$total = 0;
	$num = $db->num_rows($resql);
	while ($obj = $db->fetch_object($resql)) {
		$child->id = $obj->rowid;
		$child->ref = $obj->ref;
		$child->label = $obj->label;
		$child->status = $obj->status;
		if ((int) $obj->status == $child::STATUS_VALIDATED) {
			$total += (int) $obj->nb_heads;
		}

		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.$child->getNomUrl(1).'</td>';
		print '<td class="tdoverflowmax200">'.dol_escape_htmltag((string) $obj->label).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->bdn_code).'</td>';
		print '<td>'.dol_escape_htmltag((string) ($obj->species_label ? $obj->species_label : $obj->fk_species)).'</td>';
		print '<td class="right">'.($obj->nb_heads !== null ? (int) $obj->nb_heads : '').'</td>';
		print '<td class="center nowraponall">'.dol_print_date($db->jdate($obj->date_start), 'day').($obj->date_end ? ' - '.dol_print_date($db->jdate($obj->date_end), 'day') : '').'</td>';
		print '<td class="right">'.$child->getLibStatut(5).'</td>';
		print '</tr>';
	}
	if ($num == 0) {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	} else {
		print '<tr class="liste_total">';
		print '<td colspan="4">'.$langs->trans("Total").' <span class="opacitymedium">('.$langs->trans("Enabled").')</span></td>';
		print '<td class="right">'.$total.'</td>';
		print '<td colspan="2"></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';

	$db->free($resql);
} else {
	dol_print_error($db);
}

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
