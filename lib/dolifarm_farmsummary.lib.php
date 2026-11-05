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
 * \file    lib/dolifarm_farmsummary.lib.php
 * \ingroup dolifarm
 * \brief   Data of the tab "Farm" of a managed farm (third party): dossier, land, means, expiries and, with doliTrace, plans and records
 */

/**
 * Ids of the objects of a table for a farm, newest first
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$sql		Query returning a column rowid
 * @return	int[]
 */
function dolifarmFarmSummaryIds($db, $sql)
{
	$ids = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $ids;
	}
	while ($obj = $db->fetch_object($resql)) {
		$ids[] = (int) $obj->rowid;
	}
	$db->free($resql);
	return $ids;
}

/**
 * Number of objects of a table for a farm
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$table		Table without prefix
 * @param	string	$element	Element for getEntity()
 * @param	int		$socid		Id of the farm
 * @param	string	$more		More SQL conditions on t
 * @return	int
 */
function dolifarmFarmSummaryCount($db, $table, $element, $socid, $more = '')
{
	$sql = "SELECT COUNT(t.rowid) as nb FROM ".$db->prefix().$table." as t";
	$sql .= " WHERE t.fk_soc = ".((int) $socid)." AND t.entity IN (".getEntity($element).")".$more;
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return 0;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (int) $obj->nb : 0;
}

/**
 * Land of a farm: totals and summaries by title of conduction and by organic status (plots not disabled)
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Languages
 * @param	int			$socid	Id of the farm
 * @return	array{nb:int,cadastral:float,conducted:float,sau:float,bytitle:array<string,array{nb:int,sau:float}>,bybio:array<string,array{nb:int,sau:float,code:string}>}
 */
function dolifarmFarmSummaryLand($db, $langs, $socid)
{
	$res = array('nb' => 0, 'cadastral' => 0.0, 'conducted' => 0.0, 'sau' => 0.0, 'bytitle' => array(), 'bybio' => array());
	$sql = "SELECT p.size_cadastral, p.size_conducted, p.size_sau, p.fk_status_bio, o.label as ownership, b.label as bio";
	$sql .= " FROM ".$db->prefix()."dolifarm_plot as p";
	$sql .= " LEFT JOIN ".$db->prefix()."dolifarm_c_ownership as o ON o.code = p.fk_ownership";
	$sql .= " LEFT JOIN ".$db->prefix()."dolifarm_c_biostatus as b ON b.code = p.fk_status_bio";
	$sql .= " WHERE p.fk_soc = ".((int) $socid)." AND p.entity IN (".getEntity('dolifarmplot').")";
	$sql .= " AND (p.status IS NULL OR p.status <> 9)";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $res;
	}
	$none = $langs->trans('NotDefined');
	while ($obj = $db->fetch_object($resql)) {
		$sau = (float) $obj->size_sau;
		$res['nb']++;
		$res['cadastral'] += (float) $obj->size_cadastral;
		$res['conducted'] += (float) $obj->size_conducted;
		$res['sau'] += $sau;
		$title = ((string) $obj->ownership !== '' ? $langs->trans((string) $obj->ownership) : $none);
		$bio = ((string) $obj->bio !== '' ? $langs->trans((string) $obj->bio) : $none);
		foreach (array('bytitle' => $title, 'bybio' => $bio) as $group => $label) {
			if (!isset($res[$group][$label])) {
				$res[$group][$label] = array('nb' => 0, 'sau' => 0.0, 'code' => (string) $obj->fk_status_bio);
			}
			$res[$group][$label]['nb']++;
			$res[$group][$label]['sau'] += $sau;
		}
	}
	$db->free($resql);
	ksort($res['bytitle']);
	ksort($res['bybio']);
	return $res;
}

/**
 * Livestock heads of a farm (units not disabled)
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$socid	Id of the farm
 * @return	int
 */
function dolifarmFarmSummaryHeads($db, $socid)
{
	$sql = "SELECT SUM(t.nb_heads) as nb FROM ".$db->prefix()."dolifarm_livestock as t";
	$sql .= " WHERE t.fk_soc = ".((int) $socid)." AND t.entity IN (".getEntity('dolifarmlivestock').") AND (t.status IS NULL OR t.status <> 9)";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return 0;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (int) $obj->nb : 0;
}

/**
 * Last season of the production plans of a farm
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$socid	Id of the farm
 * @return	string			Season, '' if none
 */
function dolifarmFarmSummaryLastSeason($db, $socid)
{
	$sql = "SELECT MAX(t.season) as season FROM ".$db->prefix()."dolitrace_cropplan as t";
	$sql .= " WHERE t.fk_soc = ".((int) $socid)." AND t.entity IN (".getEntity('dolitracecropplan').")";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return '';
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (string) $obj->season : '';
}
