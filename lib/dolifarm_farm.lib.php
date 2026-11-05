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
 * \file    lib/dolifarm_farm.lib.php
 * \ingroup dolifarm
 * \brief   Managed farms, farm of the installation in single-farm mode, current dossier of a farm
 *
 * Managed farms: in single-farm mode (DOLIFARM_MULTIFARM off) the farm chosen in the setup (DOLIFARM_FARM_SOCID),
 * in multi-farm mode the third parties of type farm (TE_FARM) flagged as affiliated (extrafield dolifarm_affiliated).
 * The farm drop-downs of the suite offer only the managed farms, lists and registers hide the objects of the
 * other third parties and the save of an object on another farm is refused.
 * In single-farm mode the new objects get the farm, not editable, with its current dossier.
 *
 * Managed processors (multi-farm mode): third parties of type processor (TE_PROCESSOR) flagged as affiliated. They have
 * no dossier, plots or production plans: they are managed only by the objects listed in dolifarmProcessorElements()
 * (organic inspections), by the tab "Farm" and by their warehouses. The functions below take them into account only
 * with $withprocessors = true.
 */

/**
 * Codes of the types of third party that can be affiliated: farm and processor
 *
 * @return	string[]
 */
function dolifarmAffiliableTypes()
{
	return array('TE_FARM', 'TE_PROCESSOR');
}

/**
 * Elements of the suite whose third party can also be a managed processor (not only a managed farm)
 *
 * @return	string[]
 */
function dolifarmProcessorElements()
{
	return array('dolitracebioinspection');
}

/**
 * Whether the third party of an object can also be a managed processor
 *
 * @param	object	$object		Object of the suite
 * @return	bool
 */
function dolifarmAcceptsProcessors($object)
{
	return (is_object($object) && !empty($object->element) && in_array($object->element, dolifarmProcessorElements(), true));
}

/**
 * Ids of the affiliated third parties of a type (multi-farm mode)
 *
 * @param	string	$typecode	Code of the type of third party (TE_FARM, TE_PROCESSOR)
 * @return	int[]
 */
function dolifarmAffiliatedIds($typecode)
{
	global $db;

	$ids = array();
	$sql = "SELECT s.rowid FROM ".$db->prefix()."societe as s";
	$sql .= " INNER JOIN ".$db->prefix()."societe_extrafields as x ON x.fk_object = s.rowid";
	$sql .= " INNER JOIN ".$db->prefix()."c_typent as ty ON ty.id = s.fk_typent";
	$sql .= " WHERE ty.code = '".$db->escape($typecode)."' AND x.dolifarm_affiliated = 1";
	$sql .= " AND s.entity IN (".getEntity('societe').")";
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
 * Ids of the managed processors (multi-farm mode only)
 *
 * @param	bool	$reset	True to read them again (after a change of a third party)
 * @return	int[]
 */
function dolifarmManagedProcessorIds($reset = false)
{
	static $cache = null;

	if (!getDolGlobalInt('DOLIFARM_MULTIFARM')) {
		return array();
	}
	if ($cache === null || $reset) {
		$cache = dolifarmAffiliatedIds('TE_PROCESSOR');
	}
	return $cache;
}

/**
 * Ids of the managed farms
 *
 * @param	bool		$reset			True to read again the affiliated farms (after a change of a third party)
 * @param	bool		$withprocessors	True to add the managed processors
 * @return	int[]|null					Ids of the managed farms, null when there is no restriction (single-farm mode with no farm set)
 */
function dolifarmManagedFarmIds($reset = false, $withprocessors = false)
{
	static $cache = null;

	if ($reset) {
		dolifarmManagedProcessorIds(true);
	}
	if (!getDolGlobalInt('DOLIFARM_MULTIFARM')) {
		$socid = getDolGlobalInt('DOLIFARM_FARM_SOCID');
		return ($socid > 0 ? array($socid) : null);
	}
	if ($cache === null || $reset) {
		$cache = dolifarmAffiliatedIds('TE_FARM');
	}
	if ($withprocessors) {
		return array_values(array_unique(array_merge($cache, dolifarmManagedProcessorIds())));
	}
	return $cache;
}

/**
 * Whether a third party is a managed farm
 *
 * @param	int		$socid			Id of the third party
 * @param	bool	$withprocessors	True to accept also a managed processor
 * @return	bool
 */
function dolifarmIsManagedFarm($socid, $withprocessors = false)
{
	$ids = dolifarmManagedFarmIds(false, $withprocessors);
	return ($ids === null || in_array((int) $socid, $ids, true));
}

/**
 * Whether a third party is a managed processor
 *
 * @param	int		$socid	Id of the third party
 * @return	bool
 */
function dolifarmIsManagedProcessor($socid)
{
	return in_array((int) $socid, dolifarmManagedProcessorIds(), true);
}

/**
 * SQL filter on the farm of an object: only the managed farms (objects with no farm are kept)
 *
 * @param	string	$field			Field of the third party in the query
 * @param	bool	$withprocessors	True to accept also the managed processors
 * @return	string					SQL to append to the WHERE, '' when there is no restriction
 */
function dolifarmSqlManagedFarmFilter($field = 't.fk_soc', $withprocessors = false)
{
	$ids = dolifarmManagedFarmIds(false, $withprocessors);
	if ($ids === null) {
		return '';
	}
	return " AND (".$field." IS NULL OR ".$field." = 0 OR ".$field." IN (".(empty($ids) ? '0' : implode(',', $ids))."))";
}

/**
 * Objects of the suite on third parties that are not managed farms, hidden in lists and registers
 *
 * @param	DoliDB		$db		Database handler
 * @return	int					Number of objects, 0 when there is no restriction
 */
function dolifarmCountHiddenObjects($db)
{
	$ids = dolifarmManagedFarmIds();
	if ($ids === null) {
		return 0;
	}
	$tables = array('dolifarm_farmdossier' => 'dolifarmfarmdossier', 'dolifarm_plot' => 'dolifarmplot', 'dolifarm_machine' => 'dolifarmmachine',
		'dolifarm_building' => 'dolifarmbuilding', 'dolifarm_livestock' => 'dolifarmlivestock', 'dolifarm_operator' => 'dolifarmoperator',
		'dolifarm_estimatecosts' => 'dolifarmestimatecosts');
	if (isModEnabled('dolitrace')) {
		$tables['dolitrace_cropplan'] = 'dolitracecropplan';
		$tables['dolitrace_treatment'] = 'dolitracetreatment';
	}
	$nb = 0;
	foreach ($tables as $table => $element) {
		$sql = "SELECT COUNT(rowid) as nb FROM ".$db->prefix().$table;
		$sql .= " WHERE entity IN (".getEntity($element).") AND fk_soc > 0";
		if (!empty($ids)) {
			$sql .= " AND fk_soc NOT IN (".implode(',', $ids).")";
		}
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			continue;
		}
		$obj = $db->fetch_object($resql);
		$nb += ($obj ? (int) $obj->nb : 0);
		$db->free($resql);
	}
	return $nb;
}

/**
 * Farm field of an object:the drop-down offers only the managed farms; in single-farm mode the farm is fixed
 *
 * @param	CommonObject	$object		Object with a field linked to a third party
 * @param	string			$field			Name of the field
 * @param	bool			$withprocessors	True to offer also the managed processors
 * @return	void
 */
function dolifarmRestrictFarmField($object, $field = 'fk_soc', $withprocessors = false)
{
	if (empty($object->fields[$field]['type']) || strpos($object->fields[$field]['type'], 'integer:Societe:') !== 0) {
		return;
	}
	$ids = dolifarmManagedFarmIds(false, $withprocessors);
	if ($ids === null) {
		return;
	}
	// Type integer:Class:path:addcreate:filter, the filter uses t for the table of the third parties
	$parts = explode(':', $object->fields[$field]['type'], 5);
	$parts = array_pad($parts, 4, '');
	if ($parts[3] === '') {
		$parts[3] = '0';
	}
	$restriction = '(t.rowid:IN:'.(empty($ids) ? '0' : implode(',', $ids)).')';
	$parts[4] = (empty($parts[4]) ? $restriction : '('.$parts[4].' AND '.$restriction.')');
	$object->fields[$field]['type'] = implode(':', $parts);

	$socid = dolifarmSingleFarmSocid();
	if ($socid > 0) {
		$object->fields[$field]['default'] = (string) $socid;
		$object->fields[$field]['noteditable'] = 1;
	}
}

/**
 * Third party of the farm in single-farm mode
 *
 * @return	int		Id of the third party, 0 in multi-farm mode or when not set
 */
function dolifarmSingleFarmSocid()
{
	if (getDolGlobalInt('DOLIFARM_MULTIFARM')) {
		return 0;
	}
	return getDolGlobalInt('DOLIFARM_FARM_SOCID');
}

/**
 * Current dossier of a farm: the enabled one with the latest validation date, otherwise the latest draft
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$socid	Id of the third party of the farm
 * @return	int				Id of the dossier, 0 if none
 */
function dolifarmGetFarmDossier($db, $socid)
{
	if ($socid <= 0) {
		return 0;
	}
	$sql = "SELECT d.rowid FROM ".$db->prefix()."dolifarm_farmdossier as d";
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
	return $obj ? (int) $obj->rowid : 0;
}

/**
 * Creation in single-farm mode: the farm is fixed, its current dossier is proposed when not already chosen
 *
 * @param	DoliDB			$db			Database handler
 * @param	CommonObject	$object		Object of the page
 * @param	string			$action		Action of the page
 * @return	void
 */
function dolifarmPrefillFarm($db, $object, $action)
{
	if ($action != 'create' && $action != 'add') {
		return;
	}
	$socid = dolifarmSingleFarmSocid();
	if ($socid <= 0) {
		return;
	}
	if (isset($object->fields['fk_soc'])) {
		$_GET['fk_soc'] = $socid;
		if ($action == 'add') {
			$_POST['fk_soc'] = $socid;
		}
	}
	if ($action != 'create') {
		return;
	}
	if (isset($object->fields['fk_dossier']) && !GETPOSTISSET('fk_dossier') && GETPOSTINT('fk_soc') == $socid) {
		$dossierid = dolifarmGetFarmDossier($db, $socid);
		if ($dossierid > 0) {
			$_GET['fk_dossier'] = $dossierid;
		}
	}
}
