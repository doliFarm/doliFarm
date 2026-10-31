<?php
/* Copyright (C) 2026      SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolifarm_warehouse.lib.php
 * \ingroup dolifarm
 * \brief   Warehouses of a farm, for the stock movements of the suite (doliTrace)
 */

/**
 * Return the warehouse of a farm: the one set on its dossier, otherwise the one of the module setup
 * (single-farm mode). The dossier used is the enabled one with the latest validation date, otherwise
 * the latest draft.
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$socid		Id of the third party of the farm (0 = farm of the single-farm mode, then the module setup)
 * @param	string	$kind		'inputs' (agrodrugs, fertilizers) or 'products' (harvested products)
 * @return	int					Id of the warehouse, 0 if none
 */
function dolifarmGetFarmWarehouse($db, $socid, $kind)
{
	if (!isModEnabled('stock') || !in_array($kind, array('inputs', 'products'))) {
		return 0;
	}
	$field = 'fk_warehouse_'.$kind;
	if ($socid <= 0) {
		dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
		$socid = dolifarmSingleFarmSocid();
	}

	if ($socid > 0) {
		$sql = "SELECT d.".$field." as fk_warehouse";
		$sql .= " FROM ".$db->prefix()."dolifarm_farmdossier as d";
		$sql .= " INNER JOIN ".$db->prefix()."entrepot as e ON e.rowid = d.".$field." AND e.statut > 0";
		$sql .= " WHERE d.fk_soc = ".((int) $socid);
		$sql .= " AND d.entity IN (".getEntity('dolifarmfarmdossier').")";
		$sql .= " AND d.status IN (0, 1)";
		$sql .= " ORDER BY d.status DESC, d.date_signature DESC, d.rowid DESC";
		$sql .= $db->plimit(1);
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			return 0;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if ($obj && (int) $obj->fk_warehouse > 0) {
			return (int) $obj->fk_warehouse;
		}
	}

	return getDolGlobalInt($kind == 'inputs' ? 'DOLIFARM_WAREHOUSE_INPUTS' : 'DOLIFARM_WAREHOUSE_PRODUCTS');
}

/**
 * Open warehouses of the current entity, for the select of the setup
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<int,string>	Id => label
 */
function dolifarmWarehouseOptions($db)
{
	$options = array();
	if (!isModEnabled('stock')) {
		return $options;
	}
	$sql = "SELECT rowid, ref, lieu FROM ".$db->prefix()."entrepot";
	$sql .= " WHERE entity IN (".getEntity('stock').") AND statut > 0";
	$sql .= " ORDER BY ref";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$options[(int) $obj->rowid] = $obj->ref.($obj->lieu ? ' - '.$obj->lieu : '');
		}
		$db->free($resql);
	}
	return $options;
}
