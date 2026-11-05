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
 * \file    lib/dolifarm_ppp.lib.php
 * \ingroup dolifarm
 * \brief   Plant protection products of the farm and catalogue of the Ministry of Health: search, selector, creation of
 *          the agrodrug of a product of the catalogue
 *
 * The agrodrugs are the products the farm uses (with uses, doses, copper, stock product). The catalogue has all the
 * products of the Ministry: a product of the catalogue chosen in a treatment or in the creation form becomes an agrodrug
 * of the farm, with the data of the catalogue (status, expiry, holder, substances, formulation).
 */

dol_include_once('/dolifarm/class/dolifarmpppsync.class.php');

/**
 * Agrodrug of the farm with a registration number (any format)
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$regnumber	Registration number
 * @return	int					Id, 0 if none
 */
function dolifarmAgroDrugFindByReg($db, $regnumber)
{
	$key = DoliFarmPppSync::regKey($regnumber);
	if ($key === '') {
		return 0;
	}
	$sql = "SELECT rowid, reg_number, status FROM ".$db->prefix()."dolifarm_agrodrug WHERE reg_number IS NOT NULL AND reg_number <> ''";
	$sql .= " AND entity IN (".getEntity('dolifarmagrodrug').") ORDER BY status DESC, rowid";
	$resql = $db->query($sql);
	$found = 0;
	while ($resql && ($obj = $db->fetch_object($resql))) {
		if (DoliFarmPppSync::regKey($obj->reg_number) === $key) {
			$found = (int) $obj->rowid;
			break;
		}
	}
	return $found;
}

/**
 * Agrodrug of a product of the catalogue: the existing one with the same registration number, or a new one with the
 * data of the catalogue. Only authorised products are created.
 *
 * @param	DoliDB	$db			Database handler
 * @param	User	$user		User
 * @param	string	$regnumber	Registration number
 * @param	string	$error		Error message (translated)
 * @param	bool	$created	True when the agrodrug was created
 * @return	int					Id of the agrodrug, <0 if KO
 */
function dolifarmAgroDrugFromCatalog($db, $user, $regnumber, &$error, &$created = false)
{
	global $langs;

	$langs->load('dolifarm@dolifarm');
	$created = false;
	$id = dolifarmAgroDrugFindByReg($db, $regnumber);
	if ($id > 0) {
		return $id;
	}
	$row = DoliFarmPppSync::fetchCatalog($db, $regnumber);
	if (!$row) {
		$error = $langs->trans('ErrorPppNotInCatalog', $regnumber);
		return -1;
	}
	if (DoliFarmPppSync::statusClass($row->admin_status) != 'authorized') {
		$error = $langs->trans('ErrorPppNotAuthorized', $row->label.' ('.$row->reg_number.')', (string) $row->admin_status);
		return -1;
	}
	dol_include_once('/dolifarm/class/dolifarmagrodrug.class.php');
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	$agrodrug = new DoliFarmAgroDrug($db);
	$agrodrug->ref = $agrodrug->getNextNumRef();
	$agrodrug->label = dol_trunc((string) $row->label, 255, 'right', 'UTF-8', 1);
	$agrodrug->reg_number = (string) $row->reg_number;
	$agrodrug->status = 1;

	$db->begin();
	// Product of the stock of the agrodrug, with the same reference: bought, not sold
	$product = new Product($db);
	if ($product->fetch(0, $agrodrug->ref) <= 0) {
		$product = new Product($db);
		$product->ref = $agrodrug->ref;
		$product->label = $agrodrug->label;
		$product->type = Product::TYPE_PRODUCT;
		$product->status = 0;
		$product->status_buy = 1;
		$product->status_batch = 0;
		$product->description = $langs->transnoentities('PppProductDescription', $row->reg_number);
		if ($product->create($user) <= 0) {
			$error = $product->error ?: implode(', ', $product->errors);
			$db->rollback();
			return -1;
		}
	}
	$agrodrug->fk_product = (int) $product->id;
	$result = $agrodrug->create($user);
	if ($result <= 0) {
		$error = $agrodrug->error ?: implode(', ', $agrodrug->errors);
		$db->rollback();
		return -1;
	}
	$db->commit();
	$created = true;
	dol_syslog(__FUNCTION__.' agrodrug '.$agrodrug->id.' created from the catalogue, registration '.$row->reg_number, LOG_INFO);
	return (int) $agrodrug->id;
}

/**
 * Value of the selector (dolifarmAgroDrugSelect) to an agrodrug: an id, or "cat:" and the registration of a product of
 * the catalogue, which becomes an agrodrug of the farm
 *
 * @param	DoliDB	$db			Database handler
 * @param	User	$user		User
 * @param	string	$value		Value posted
 * @param	string	$error		Error message (translated)
 * @param	bool	$created	True when the agrodrug was created from the catalogue
 * @return	int					Id of the agrodrug, 0 if empty, <0 if KO
 */
function dolifarmAgroDrugResolve($db, $user, $value, &$error, &$created = false)
{
	$created = false;
	$value = trim((string) $value);
	if (str_starts_with($value, 'cat:')) {
		return dolifarmAgroDrugFromCatalog($db, $user, substr($value, 4), $error, $created);
	}
	return max(0, (int) $value);
}

/**
 * Search for the selector: agrodrugs of the farm in use, then authorised products of the catalogue that the farm does
 * not have yet (select2 format)
 *
 * @param	DoliDB		$db			Database handler
 * @param	Translate	$langs		Languages
 * @param	string		$term		Text: name, active substance or registration number
 * @param	bool		$catalog	Include the catalogue
 * @return	array<int,array<string,mixed>>	Groups with their children
 */
function dolifarmAgroDrugSearch($db, $langs, $term, $catalog)
{
	$term = trim((string) $term);
	$like = $db->escape($db->escapeforlike($term));
	$key = DoliFarmPppSync::regKey($term);
	$farm = array();
	$keys = array();
	$sql = "SELECT rowid, label, reg_number, admin_status FROM ".$db->prefix()."dolifarm_agrodrug";
	$sql .= " WHERE entity IN (".getEntity('dolifarmagrodrug').") AND status = 1";
	if ($term !== '') {
		$sql .= " AND (label LIKE '%".$like."%' OR active_substance LIKE '%".$like."%' OR reg_number LIKE '%".$like."%')";
	}
	$sql .= " ORDER BY label";
	$resql = $db->query($sql);
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$farm[] = array('id' => (string) $obj->rowid, 'text' => $obj->label.($obj->reg_number ? ' ('.$obj->reg_number.')' : ''));
	}
	$resql = $db->query("SELECT reg_number FROM ".$db->prefix()."dolifarm_agrodrug WHERE reg_number IS NOT NULL AND entity IN (".getEntity('dolifarmagrodrug').")");
	while ($resql && ($obj = $db->fetch_object($resql))) {
		$keys[DoliFarmPppSync::regKey($obj->reg_number)] = 1;
	}
	$groups = array();
	if (count($farm)) {
		$groups[] = array('text' => $langs->transnoentities('PppGroupFarm'), 'children' => $farm);
	}
	if ($catalog && dol_strlen($term) >= 2) {
		$cat = array();
		$sql = "SELECT reg_key, reg_number, label, holder, substances, admin_status FROM ".$db->prefix()."dolifarm_ppp_catalog";
		$sql .= " WHERE (label LIKE '%".$like."%' OR substances LIKE '%".$like."%'".($key !== '' ? " OR reg_key = '".$db->escape($key)."'" : '').")";
		$sql .= " ORDER BY label";
		$resql = $db->query($sql);
		while ($resql && ($obj = $db->fetch_object($resql)) && count($cat) < 30) {
			if (isset($keys[(string) $obj->reg_key]) || DoliFarmPppSync::statusClass($obj->admin_status) != 'authorized') {
				continue;
			}
			$cat[] = array('id' => 'cat:'.$obj->reg_number, 'text' => $obj->label.' ('.$obj->reg_number.') - '.str_replace('|', ' + ', (string) $obj->substances).' - '.$obj->holder);
		}
		if (count($cat)) {
			$groups[] = array('text' => $langs->transnoentities('PppGroupCatalog'), 'children' => $cat);
		}
	}
	return $groups;
}

/**
 * Selector of a plant protection product: agrodrugs of the farm and, by typing, the authorised products of the
 * catalogue of the Ministry (value "cat:" and the registration number, see dolifarmAgroDrugResolve())
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$htmlname	Name of the field
 * @param	string	$value		Selected value
 * @param	bool	$catalog	Include the catalogue
 * @param	string	$morecss	CSS
 * @return	string				HTML
 */
function dolifarmAgroDrugSelect($db, $htmlname, $value, $catalog = true, $morecss = 'minwidth300')
{
	global $conf, $langs;

	$langs->load('dolifarm@dolifarm');
	$out = '<select class="flat '.dol_escape_htmltag($morecss).'" name="'.dol_escape_htmltag($htmlname).'" id="'.dol_escape_htmltag($htmlname).'">';
	$out .= '<option value=""></option>';
	foreach (dolifarmAgroDrugSearch($db, $langs, '', false) as $group) {
		foreach ($group['children'] as $option) {
			$out .= '<option value="'.dol_escape_htmltag($option['id']).'"'.((string) $value === $option['id'] ? ' selected' : '').'>'.dol_escape_htmltag($option['text']).'</option>';
		}
	}
	$out .= '</select>';
	if (empty($conf->use_javascript_ajax)) {
		return $out;
	}
	$url = dol_buildpath('/dolifarm/ajax/ppp_search.php', 1);
	$out .= '<script>
	$(document).ready(function() {
		$('.json_encode('#'.$htmlname).').select2({
			width: "resolve",
			allowClear: true,
			placeholder: '.json_encode($langs->transnoentities($catalog ? 'PppSearchPlaceholder' : 'PppSearchPlaceholderFarm')).',
			minimumInputLength: 0,
			language: (typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage,
			ajax: {
				url: '.json_encode($url).',
				dataType: "json",
				delay: 300,
				data: function(params) {
					return { term: params.term || "", catalog: '.($catalog ? 1 : 0).' };
				},
				processResults: function(data) {
					return { results: data.results || [] };
				}
			}
		});
	});
	</script>';
	return $out;
}
