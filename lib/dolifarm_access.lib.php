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
 * \file    lib/dolifarm_access.lib.php
 * \ingroup dolifarm
 * \brief   Visibility of the objects of a farm for the users restricted to their third parties
 *
 * Same rule as Dolibarr: an internal user without the right "see all third parties" sees only the third parties
 * he is sales representative of (also those of his subordinates with MAIN_SEE_SUBORDINATES), an external user
 * only his own third party. Unlike orders or invoices, an object without third party (an operator who is an
 * employee, a machine of the contractor) stays visible to the internal users.
 */

/**
 * Users whose third parties the user can see, or null when the user is not restricted
 *
 * @param	User		$user	User
 * @return	int[]|null			Ids of the sales representatives to check, null if the user sees all the third parties
 */
function dolifarmSocRestrictedUsers($user)
{
	if (!isModEnabled('societe') || !empty($user->socid) || $user->hasRight('societe', 'client', 'voir')) {
		return null;
	}
	$users = array((int) $user->id);
	if (getDolGlobalInt('MAIN_SEE_SUBORDINATES')) {
		foreach ($user->getAllChildIds() as $childid) {
			$users[] = (int) $childid;
		}
	}
	return array_unique($users);
}

/**
 * SQL filter of a list on the third parties the user can see
 *
 * @param	User		$user		User
 * @param	string		$field		Field of the third party, with the alias of the table (t.fk_soc)
 * @return	string					SQL to add to the WHERE (" AND ..."), empty if the user sees all the third parties
 */
function dolifarmSqlSocFilter($user, $field = 't.fk_soc')
{
	global $db;

	if (!empty($user->socid)) {
		return " AND ".$field." = ".((int) $user->socid);
	}
	$users = dolifarmSocRestrictedUsers($user);
	if ($users === null) {
		return '';
	}
	return " AND (".$field." IS NULL OR ".$field." = 0 OR EXISTS (SELECT sc.fk_soc FROM ".$db->prefix()."societe_commerciaux as sc WHERE sc.fk_soc = ".$field." AND sc.fk_user IN (".$db->sanitize(implode(',', $users)).")))";
}

/**
 * Check if the user can see a third party
 *
 * @param	User		$user		User
 * @param	int			$socid		Id of the third party (0 = none)
 * @return	bool
 */
function dolifarmUserCanSeeSoc($user, $socid)
{
	global $db;

	$socid = (int) $socid;
	if (!empty($user->socid)) {
		return ($socid == (int) $user->socid);
	}
	$users = dolifarmSocRestrictedUsers($user);
	if ($users === null || $socid <= 0) {
		return true;
	}
	$sql = "SELECT COUNT(sc.fk_soc) as nb FROM ".$db->prefix()."societe_commerciaux as sc";
	$sql .= " WHERE sc.fk_soc = ".$socid." AND sc.fk_user IN (".$db->sanitize(implode(',', $users)).")";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return false;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return ($obj && $obj->nb > 0);
}

/**
 * Stop the page when the user cannot see the object: entity of the object, third party of the farm
 *
 * @param	User			$user		User
 * @param	CommonObject	$object		Object loaded by the page (nothing is checked when it has no id)
 * @param	string			$field		Field of the third party
 * @return	void
 */
function dolifarmCheckSocAccess($user, $object, $field = 'fk_soc')
{
	if (!is_object($object) || empty($object->id)) {
		return;
	}
	if (!empty($object->ismultientitymanaged) && isset($object->entity) && !in_array((int) $object->entity, array_map('intval', explode(',', getEntity($object->element, 1))))) {
		accessforbidden();
	}
	if (isset($object->fields[$field]) && !dolifarmUserCanSeeSoc($user, (int) $object->$field)) {
		accessforbidden();
	}
}
