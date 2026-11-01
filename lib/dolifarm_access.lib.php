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
 * Third parties the user can see, or null when the user is not restricted
 *
 * External users are left to Dolibarr, which already limits the lists to their own third party.
 *
 * @param	User		$user	User
 * @return	int[]|null			Ids of the third parties, null if the user sees all the third parties
 */
function dolifarmSocVisibleIds($user)
{
	global $db;
	static $cache = array();

	if (!empty($user->socid)) {
		return null;
	}
	$users = dolifarmSocRestrictedUsers($user);
	if ($users === null) {
		return null;
	}
	if (!isset($cache[$user->id])) {
		$cache[$user->id] = array();
		$sql = "SELECT DISTINCT sc.fk_soc FROM ".$db->prefix()."societe_commerciaux as sc";
		$sql .= " WHERE sc.fk_user IN (".$db->sanitize(implode(',', $users)).")";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$cache[$user->id][] = (int) $obj->fk_soc;
			}
			$db->free($resql);
		} else {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		}
	}
	return $cache[$user->id];
}

/**
 * Limit the drop-down lists of the link fields of an object (fascicolo, appezzamento, ...) to the farms the user can see
 *
 * The filter goes in the type of the field ('integer:Class:path:addcreate:filter'), so it also works when the list is
 * loaded by ajax/selectobject.php, which builds the object again. The linked object must have a field fk_soc; records
 * without third party stay in the list, as in the lists of the module.
 *
 * @param	CommonObject	$object			Object whose fields are changed (call it from the constructor)
 * @param	string[]		$fieldnames		Link fields to limit
 * @return	void
 */
function dolifarmRestrictLinkFields($object, $fieldnames)
{
	global $user;

	if (!is_object($user) || empty($user->id) || !is_array($object->fields)) {
		return;
	}
	$socids = dolifarmSocVisibleIds($user);
	if ($socids === null) {
		return;
	}
	$filter = "((t.fk_soc:IN:".(count($socids) ? implode(',', $socids) : '0').") OR (t.fk_soc:IS:NULL))";
	foreach ($fieldnames as $fieldname) {
		if (empty($object->fields[$fieldname]['type']) || strpos($object->fields[$fieldname]['type'], 'integer:') !== 0) {
			continue;
		}
		// integer, class, path, add create button, filter
		$parts = explode(':', $object->fields[$fieldname]['type'], 5);
		if (count($parts) < 3) {
			continue;
		}
		if (!isset($parts[3]) || $parts[3] === '') {
			$parts[3] = '0';
		}
		$parts[4] = (isset($parts[4]) && $parts[4] !== '') ? '('.$parts[4].') AND '.$filter : $filter;
		$object->fields[$fieldname]['type'] = implode(':', $parts);
		// Checked again on save by dolifarmCheckObjectAccess()
		$object->fields[$fieldname]['dolifarmrestricted'] = 1;
	}
}

/**
 * Check on save that the user can see the farm of the object and the farms of the objects it links to
 *
 * The drop-down lists only show the farms of the user (dolifarmRestrictLinkFields()): this check refuses a value
 * sent by hand. Called by the trigger of doliFarm on the creation and the modification of the objects of the suite.
 *
 * @param	CommonObject	$object		Object being saved
 * @param	User			$user		User
 * @return	string						Error message, '' if allowed
 */
function dolifarmCheckObjectAccess($object, $user)
{
	global $db, $langs;

	$external = (is_object($user) && !empty($user->socid));
	if (!is_object($user) || empty($user->id) || (!$external && dolifarmSocVisibleIds($user) === null)) {
		return '';
	}
	$langs->load('dolifarm@dolifarm');
	if (!empty($object->module) && preg_match('/^[a-z]+$/', $object->module)) {
		$langs->load($object->module.'@'.$object->module);
	}
	if (property_exists($object, 'fk_soc') && (int) $object->fk_soc > 0 && !dolifarmUserCanSeeSoc($user, (int) $object->fk_soc)) {
		return $langs->trans('ErrorDolifarmFarmNotAllowed', $langs->transnoentitiesnoconv('ThirdParty'));
	}
	if (!is_array($object->fields)) {
		return '';
	}
	foreach ($object->fields as $fieldname => $field) {
		// External users: Dolibarr limits their lists, the fields are not marked; all the links to objects of the suite are checked
		$check = !empty($field['dolifarmrestricted']) || ($external && isset($field['type']) && preg_match('/^integer:Doli(Farm|Trace)/', $field['type']));
		if (!$check || !property_exists($object, $fieldname) || (int) $object->$fieldname <= 0) {
			continue;
		}
		$parts = explode(':', $field['type'], 5);
		if (count($parts) < 3 || !dol_include_once('/'.$parts[2])) {
			continue;
		}
		$classname = $parts[1];
		if (!class_exists($classname)) {
			continue;
		}
		$linked = new $classname($db);
		if (empty($linked->table_element)) {
			continue;
		}
		$sql = "SELECT fk_soc FROM ".$db->prefix().$linked->table_element." WHERE rowid = ".((int) $object->$fieldname);
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			continue;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if ($obj && (int) $obj->fk_soc > 0 && !dolifarmUserCanSeeSoc($user, (int) $obj->fk_soc)) {
			return $langs->trans('ErrorDolifarmFarmNotAllowed', $langs->transnoentitiesnoconv($field['label']));
		}
	}
	return '';
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
