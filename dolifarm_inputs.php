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
 *  \file       dolifarm_inputs.php
 *  \ingroup    dolifarm
 *  \brief      Register of the technical inputs: plant protection products, fertilisers and corroborants for organic
 *              production, with the check of annex I and II of Reg. EU 2021/1165, and the rules
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/dolifarm/lib/dolifarm_organic.lib.php');
dol_include_once('/dolifarm/class/dolifarmpppsync.class.php');
dol_include_once('/dolifarm/class/dolifarmagrodrug.class.php');
dol_include_once('/dolifarm/class/dolifarmfertilizer.class.php');

$langs->loadLangs(array('dolifarm@dolifarm'));

$canagro = $user->hasRight('dolifarm', 'dolifarmagrodrug', 'read');
$canfert = $user->hasRight('dolifarm', 'dolifarmfertilizer', 'read');
if (!$canagro && !$canfert) {
	accessforbidden();
}
$tab = GETPOST('tab', 'aZ09');
if (!in_array($tab, array('ppp', 'fert', 'corr', 'norm'))) {
	$tab = ($canagro ? 'ppp' : 'fert');
}
if (($tab == 'ppp' && !$canagro) || (in_array($tab, array('fert', 'corr')) && !$canfert)) {
	accessforbidden();
}
$filter = GETPOST('filter', 'aZ09');
$search = trim(GETPOST('search', 'alphanohtml'));
$onlyannex = GETPOSTINT('onlyannex');

$form = new Form($db);
$self = dol_buildpath('/dolifarm/dolifarm_inputs.php', 1);


/*
 * View
 */

llxHeader('', $langs->trans('InputsRegister'), '', '', 0, 0, '', '', '', 'mod-dolifarm page-inputs');

$head = array();
$h = 0;
foreach (array('ppp' => 'InputsTabPpp', 'fert' => 'InputsTabFert', 'corr' => 'InputsTabCorr', 'norm' => 'InputsTabNorm') as $key => $label) {
	if (($key == 'ppp' && !$canagro) || (in_array($key, array('fert', 'corr')) && !$canfert)) {
		continue;
	}
	$head[$h][0] = $self.'?tab='.$key;
	$head[$h][1] = $langs->trans($label);
	$head[$h][2] = $key;
	$h++;
}
print load_fiche_titre($langs->trans('InputsRegister'), '', 'fa-leaf');
print dol_get_fiche_head($head, $tab, '', -1, '');

if ($tab == 'ppp') {
	// Agrodrugs of the farm, with the check for organic production
	print '<span class="opacitymedium">'.$langs->trans('InputsPppHelp').'</span><br><br>';
	print '<form method="GET" action="'.$self.'">';
	print '<input type="hidden" name="tab" value="ppp">';
	print $langs->trans('OrganicResult').' ';
	print $form->selectarray('filter', array('yes' => $langs->trans('OrganicResultYes'), 'verify' => $langs->trans('OrganicResultVerify'), 'no' => $langs->trans('OrganicResultNo')), $filter, 1);
	print ' <input type="submit" class="button small" value="'.$langs->trans('Refresh').'">';
	print '</form><br>';

	$sql = "SELECT a.rowid, a.reg_number, a.manufacturer, a.active_substance, a.admin_status, a.auth_expiration_date,";
	$sql .= " (SELECT COUNT(*) FROM ".$db->prefix()."dolifarm_agrodrug_use as u WHERE u.fk_agrodrug = a.rowid) as nbuses";
	$sql .= " FROM ".$db->prefix()."dolifarm_agrodrug as a WHERE a.entity IN (".getEntity('dolifarmagrodrug').") ORDER BY a.label";
	$resql = $db->query($sql);
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Label').'</td><td>'.$langs->trans('Regnumber').'</td><td>'.$langs->trans('Manufacturer').'</td><td>'.$langs->trans('Activesubstance').'</td>';
	print '<td>'.$langs->trans('AgroDrugAdminStatus').'</td><td class="center">'.$langs->trans('Authexpirationdate').'</td><td class="center">'.$langs->trans('InputsUses').'</td><td>'.$langs->trans('OrganicResult').'</td></tr>';
	$nb = 0;
	$agrodrug = new DoliFarmAgroDrug($db);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$check = dolifarmOrganicAgroDrugCheck($db, (int) $obj->rowid);
		if ($filter !== '' && $check['result'] != $filter) {
			continue;
		}
		$agrodrug->fetch((int) $obj->rowid);
		$nb++;
		print '<tr class="oddeven"><td class="tdoverflowmax200">'.$agrodrug->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->reg_number).'</td>';
		print '<td class="tdoverflowmax150">'.dol_escape_htmltag((string) $obj->manufacturer).'</td>';
		print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag((string) $obj->active_substance).'">'.dol_escape_htmltag((string) $obj->active_substance).'</td>';
		print '<td>'.dol_escape_htmltag((string) $obj->admin_status).'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($obj->auth_expiration_date), 'day').'</td>';
		print '<td class="center">'.((int) $obj->nbuses).'</td>';
		print '<td>'.dolifarmOrganicBadge($check['result']);
		if (count($check['reasons'])) {
			print '<br><span class="opacitymedium small">'.dol_escape_htmltag(implode('; ', $check['reasons'])).'</span>';
		}
		print '</td></tr>';
	}
	if ($nb == 0) {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table></div>';

	// Search in the catalogue of the Ministry of Health: authorised products only
	print '<br>'.load_fiche_titre($langs->trans('InputsCatalogSearch'), '', '');
	print '<form method="GET" action="'.$self.'">';
	print '<input type="hidden" name="tab" value="ppp">';
	print '<input type="text" name="search" class="minwidth300" value="'.dol_escape_htmltag($search).'" placeholder="'.dol_escape_htmltag($langs->trans('InputsCatalogSearchHelp')).'"> ';
	print '<label><input type="checkbox" name="onlyannex" value="1"'.($onlyannex ? ' checked' : '').'> '.$langs->trans('InputsOnlyAnnex').'</label> ';
	print '<input type="submit" class="button small" value="'.$langs->trans('Search').'">';
	print '</form>';
	if (dol_strlen($search) >= 3) {
		$rules = dolifarmOrganicRules($db);
		$existing = array();
		$resql = $db->query("SELECT rowid, reg_number FROM ".$db->prefix()."dolifarm_agrodrug WHERE reg_number IS NOT NULL AND entity IN (".getEntity('dolifarmagrodrug').")");
		while ($resql && ($obj = $db->fetch_object($resql))) {
			$existing[DoliFarmPppSync::regKey($obj->reg_number)] = (int) $obj->rowid;
		}
		$sql = "SELECT reg_key, reg_number, label, holder, activity, substances, admin_status, date_expiry FROM ".$db->prefix()."dolifarm_ppp_catalog";
		$sql .= " WHERE (label LIKE '%".$db->escape($db->escapeforlike($search))."%' OR substances LIKE '%".$db->escape($db->escapeforlike($search))."%' OR reg_key = '".$db->escape(DoliFarmPppSync::regKey($search))."')";
		$sql .= " ORDER BY label";
		$resql = $db->query($sql);
		print '<div class="div-table-responsive"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>'.$langs->trans('Regnumber').'</td><td>'.$langs->trans('Label').'</td><td>'.$langs->trans('Manufacturer').'</td><td>'.$langs->trans('InputsActivity').'</td>';
		print '<td>'.$langs->trans('OrganicSubstances').'</td><td class="center">'.$langs->trans('Authexpirationdate').'</td><td></td></tr>';
		$nb = 0;
		while ($resql && ($obj = $db->fetch_object($resql)) && $nb < 100) {
			if (DoliFarmPppSync::statusClass($obj->admin_status) != 'authorized') {
				continue;
			}
			$all = true;
			$cells = array();
			foreach (explode('|', (string) $obj->substances) as $name) {
				$rule = dolifarmOrganicMatch($name, $rules);
				$all = ($all && $rule !== null && (int) $rule->annex_section > 0);
				$cells[] = dol_escape_htmltag(trim($name)).' '.($rule ? img_picto($rule->label.((string) $rule->conditions !== '' ? ': '.$rule->conditions : ''), (string) $rule->check_type === '' || $rule->check_type == 'COPPER' ? 'tick' : 'warning') : img_picto($langs->trans('OrganicNotInAnnex'), 'error'));
			}
			if ($onlyannex && !$all) {
				continue;
			}
			$nb++;
			print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->reg_number).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td class="tdoverflowmax150">'.dol_escape_htmltag($obj->holder).'</td>';
			print '<td>'.dol_escape_htmltag($obj->activity).'</td><td>'.implode('<br>', $cells).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_expiry), 'day').'</td><td class="right nowraponall">';
			if (isset($existing[$obj->reg_key])) {
				print '<a href="'.dol_buildpath('/dolifarm/dolifarmagrodrug_card.php', 1).'?id='.$existing[$obj->reg_key].'">'.$langs->trans('InputsAlreadyThere').'</a>';
			} elseif ($user->hasRight('dolifarm', 'dolifarmagrodrug', 'write')) {
				print '<a class="button small" href="'.dol_buildpath('/dolifarm/dolifarmagrodrug_card.php', 1).'?action=create&reg_number='.urlencode($obj->reg_number).'&label='.urlencode($obj->label).'">'.$langs->trans('InputsCreateAgroDrug').'</a>';
			}
			print '</td></tr>';
		}
		if ($nb == 0) {
			print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		}
		print '</table></div>';
		if ($nb >= 100) {
			print '<span class="opacitymedium">'.$langs->trans('InputsTooMany').'</span>';
		}
	}
} elseif ($tab == 'fert' || $tab == 'corr') {
	// Fertilisers and soil conditioners, or corroborants
	print '<span class="opacitymedium">'.$langs->trans($tab == 'fert' ? 'InputsFertHelp' : 'InputsCorrHelp').'</span><br><br>';
	$sql = "SELECT f.rowid FROM ".$db->prefix()."dolifarm_fertilizer as f WHERE f.entity IN (".getEntity('dolifarmfertilizer').")";
	$sql .= ($tab == 'corr' ? " AND f.product_kind = 'CORROBORANT'" : " AND (f.product_kind IS NULL OR f.product_kind <> 'CORROBORANT')");
	$sql .= " ORDER BY f.label";
	$resql = $db->query($sql);
	$fert = new DoliFarmFertilizer($db);
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Label').'</td><td>'.$langs->trans('FertilizerKind').'</td><td>'.$langs->trans('FertilizerManufacturer').'</td><td>'.$langs->trans('FertilizerRegistration').'</td>';
	if ($tab == 'fert') {
		print '<td>'.$langs->trans('FertilizerAnnex2').'</td>';
	}
	print '<td class="center">'.$langs->trans('FertilizerOrganicCheckDate').'</td><td>'.$langs->trans('OrganicResult').'</td></tr>';
	$nb = 0;
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$fert->fetch((int) $obj->rowid);
		$check = dolifarmOrganicFertilizerCheck($db, (int) $obj->rowid);
		$nb++;
		print '<tr class="oddeven"><td class="tdoverflowmax200">'.$fert->getNomUrl(1).'</td>';
		print '<td>'.($fert->product_kind ? $langs->trans($fert->fields['product_kind']['arrayofkeyval'][$fert->product_kind]) : '').'</td>';
		print '<td class="tdoverflowmax150">'.dol_escape_htmltag((string) $fert->manufacturer).'</td>';
		print '<td>'.dol_escape_htmltag((string) $fert->registration_number).'</td>';
		if ($tab == 'fert') {
			print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag(implode("\n", $check['conditions'])).'">'.dol_escape_htmltag($check['annex2']).'</td>';
		}
		print '<td class="center">'.dol_print_date($fert->organic_check_date, 'day').'</td>';
		print '<td>'.dolifarmOrganicBadge($check['result']);
		if (count($check['reasons'])) {
			print '<br><span class="opacitymedium small">'.dol_escape_htmltag(implode('; ', $check['reasons'])).'</span>';
		}
		print '</td></tr>';
	}
	if ($nb == 0) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table></div>';
	print '<br><a href="'.($tab == 'corr' ? 'https://www.sian.it/mimfFitoPub/listaCorroboranti.get' : 'https://www.masaf.gov.it/flex/cm/pages/ServeBLOB.php/L/IT/IDPagina/17462').'" target="_blank" rel="noopener noreferrer">'.$langs->trans($tab == 'corr' ? 'InputsLinkCorroborants' : 'InputsLinkMasaf').'</a>';
} else {
	// Rules and sources
	$links = array(
		'Reg. (UE) 2018/848' => 'https://eur-lex.europa.eu/legal-content/IT/TXT/?uri=CELEX:32018R0848',
		'Reg. di esecuzione (UE) 2021/1165, testo consolidato al 01/01/2026' => 'https://eur-lex.europa.eu/legal-content/IT/TXT/?uri=CELEX:02021R1165-20260101',
		'Reg. di esecuzione (UE) n. 540/2011 (sostanze attive approvate)' => 'https://eur-lex.europa.eu/legal-content/IT/TXT/?uri=CELEX:32011R0540',
		'D.Lgs. 75/2010 e registro dei fertilizzanti (MASAF)' => 'https://www.masaf.gov.it/flex/cm/pages/ServeBLOB.php/L/IT/IDPagina/17462',
		'Banca dati dei prodotti fitosanitari e corroboranti (SIAN)' => 'https://www.sian.it/mimfFitoPub/home.get',
		'Open data prodotti fitosanitari (Ministero della Salute)' => 'https://www.dati.salute.gov.it/it/dataset/fitosanitari/',
	);
	print '<span class="opacitymedium">'.$langs->trans('InputsNormHelp').'</span><br><br>';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('InputsSources').'</td></tr>';
	foreach ($links as $label => $url) {
		print '<tr class="oddeven"><td><a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag($label).'</a></td></tr>';
	}
	$syncdate = getDolGlobalInt('DOLIFARM_PPP_SYNC_DATE');
	print '<tr class="oddeven"><td>'.$langs->trans('PppSyncDate').': '.($syncdate ? dol_print_date($syncdate, 'dayhour').' ('.dol_escape_htmltag(getDolGlobalString('DOLIFARM_PPP_FILE')).')' : $langs->trans('PppNeverSynced')).'</td></tr>';
	print '</table></div>';

	$sections = array(1 => 'InputsAnnexSection1', 2 => 'InputsAnnexSection2', 3 => 'InputsAnnexSection3', 4 => 'InputsAnnexSection4', 0 => 'InputsAnnexSection0');
	$resql = $db->query("SELECT annex_section, annex_ref, cas, label, conditions, annex_version FROM ".$db->prefix()."dolifarm_organic_substance WHERE active = 1 ORDER BY annex_section = 0, annex_section, position");
	print '<br>'.load_fiche_titre($langs->trans('InputsAnnex1'), '', '');
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('InputsAnnexRef').'</td><td>'.$langs->trans('Label').'</td><td>CAS</td><td>'.$langs->trans('OrganicConditions').'</td></tr>';
	$current = -1;
	while ($resql && ($obj = $db->fetch_object($resql))) {
		if ((int) $obj->annex_section != $current) {
			$current = (int) $obj->annex_section;
			print '<tr class="liste_titre_sel"><td colspan="4">'.$langs->trans($sections[$current]).'</td></tr>';
		}
		print '<tr class="oddeven"><td>'.dol_escape_htmltag((string) $obj->annex_ref).'</td><td>'.dol_escape_htmltag($obj->label).'</td><td class="small">'.dol_escape_htmltag((string) $obj->cas).'</td><td class="small">'.dol_escape_htmltag((string) $obj->conditions).'</td></tr>';
	}
	print '</table></div>';

	$resql = $db->query("SELECT label, conditions FROM ".$db->prefix()."dolifarm_organic_fertilizer WHERE active = 1 ORDER BY position");
	print '<br>'.load_fiche_titre($langs->trans('InputsAnnex2'), '', '');
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Label').'</td><td>'.$langs->trans('OrganicConditions').'</td></tr>';
	while ($resql && ($obj = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->label).'</td><td class="small">'.dol_escape_htmltag((string) $obj->conditions).'</td></tr>';
	}
	print '</table></div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
