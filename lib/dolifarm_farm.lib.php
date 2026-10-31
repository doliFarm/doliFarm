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
 * \file    lib/dolifarm_farm.lib.php
 * \ingroup dolifarm
 * \brief   Farm of the installation in single-farm mode, current dossier of a farm
 *
 * In single-farm mode (DOLIFARM_MULTIFARM off) the farm is chosen once in the setup (DOLIFARM_FARM_SOCID):
 * the new objects get it with its current dossier, and the suite (doliTrace) uses it as default farm.
 */

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
 * Creation form in single-farm mode: the farm and its current dossier are proposed when not already chosen
 *
 * @param	DoliDB			$db			Database handler
 * @param	CommonObject	$object		Object of the page
 * @param	string			$action		Action of the page
 * @return	void
 */
function dolifarmPrefillFarm($db, $object, $action)
{
	if ($action != 'create') {
		return;
	}
	$socid = dolifarmSingleFarmSocid();
	if ($socid <= 0) {
		return;
	}
	if (isset($object->fields['fk_soc']) && !GETPOSTISSET('fk_soc')) {
		$_GET['fk_soc'] = $socid;
	}
	if (isset($object->fields['fk_dossier']) && !GETPOSTISSET('fk_dossier') && GETPOSTINT('fk_soc') == $socid) {
		$dossierid = dolifarmGetFarmDossier($db, $socid);
		if ($dossierid > 0) {
			$_GET['fk_dossier'] = $dossierid;
		}
	}
}
