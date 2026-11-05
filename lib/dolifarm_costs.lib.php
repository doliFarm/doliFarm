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
 * \file    lib/dolifarm_costs.lib.php
 * \ingroup dolifarm
 * \brief   Standard costs of the operations per hectare and unit cost of the products
 *
 * The standard costs (object DoliFarmEstimateCosts) give, for a type of operation, the cost per hectare of labour,
 * machines and fuel, services of third parties. A cost of a farm takes precedence over a cost for all farms; among
 * them the one with the latest date of validity not after the date of the operation is used. The products are valued
 * at the weighted average price (PMP) of Dolibarr, or at the cost price of the product when there is no PMP.
 */

/**
 * Standard cost per hectare of a type of operation for a farm at a date
 *
 * @param	DoliDB		$db			Database handler
 * @param	string		$optype		Code of the type of operation
 * @param	int			$socid		Farm (third party), 0 for the costs of all farms only
 * @param	?int		$date		Date of the operation, null for today
 * @return	?array{id:int,label:string,manpower:float,machine:float,service:float,total:float,duration:int}	Null when there is no cost
 */
function dolifarmStandardCost($db, $optype, $socid = 0, ?int $date = null)
{
	static $cache = array();

	$optype = trim((string) $optype);
	if ($optype === '') {
		return null;
	}
	$day = dol_print_date($date ?? dol_now(), '%Y-%m-%d', 'tzserver');
	$key = $optype.'|'.((int) $socid).'|'.$day;
	if (array_key_exists($key, $cache)) {
		return $cache[$key];
	}
	$sql = "SELECT rowid, label, cost_manpower_ha, cost_machine_ha, cost_service_ha, cost_std_ha, duration_min_ha";
	$sql .= " FROM ".$db->prefix()."dolifarm_dolifarmestimatecosts";
	$sql .= " WHERE entity IN (".getEntity('dolifarmestimatecosts').")";
	$sql .= " AND fk_operation_type = '".$db->escape($optype)."'";
	$sql .= " AND (fk_soc IS NULL OR fk_soc = 0".($socid > 0 ? " OR fk_soc = ".((int) $socid) : "").")";
	$sql .= " AND (date_start IS NULL OR date_start <= '".$db->escape($day)."')";
	// The farm first, then the most recent date (no date = oldest)
	$sql .= " ORDER BY CASE WHEN fk_soc > 0 THEN 0 ELSE 1 END, CASE WHEN date_start IS NULL THEN 1 ELSE 0 END, date_start DESC, rowid DESC";
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return null;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	$cost = null;
	if ($obj) {
		$manpower = (float) $obj->cost_manpower_ha;
		$machine = (float) $obj->cost_machine_ha;
		$service = (float) $obj->cost_service_ha;
		// A cost written before the items has only the total
		if ($manpower == 0 && $machine == 0 && $service == 0) {
			$service = (float) $obj->cost_std_ha;
		}
		$cost = array('id' => (int) $obj->rowid, 'label' => (string) $obj->label, 'manpower' => $manpower, 'machine' => $machine, 'service' => $service,
			'total' => $manpower + $machine + $service, 'duration' => (int) $obj->duration_min_ha);
	}
	$cache[$key] = $cost;
	return $cost;
}

/**
 * Unit cost of a product: weighted average price (PMP), or cost price when there is no PMP
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$productid	Product
 * @return	?float				Cost of one unit of the product (unit of the product), null when unknown
 */
function dolifarmProductUnitCost($db, $productid)
{
	static $cache = array();

	$productid = (int) $productid;
	if ($productid <= 0) {
		return null;
	}
	if (array_key_exists($productid, $cache)) {
		return $cache[$productid];
	}
	$cost = null;
	$resql = $db->query("SELECT pmp, cost_price FROM ".$db->prefix()."product WHERE rowid = ".$productid);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		if ((float) $obj->pmp > 0) {
			$cost = (float) $obj->pmp;
		} elseif ((float) $obj->cost_price > 0) {
			$cost = (float) $obj->cost_price;
		}
	}
	$cache[$productid] = $cost;
	return $cost;
}
