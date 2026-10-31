<?php
/* Copyright (C) 2026		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modDolifarm_DolifarmTriggers.class.php
 * \ingroup dolifarm
 * \brief   Triggers of doliFarm: keep the agenda events of the expiry dates in step with the objects
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Triggers of doliFarm
 */
class InterfaceDolifarmTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "dolifarm";
		$this->description = "doliFarm: agenda events of the expiry dates (dossier, conductions, authorisations, machines), dossier and warehouses of the affiliated farms.";
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'fa-leaf';
	}

	/**
	 * Function called when a Dolibarr business event is done
	 *
	 * @param	string			$action		Event action code
	 * @param	CommonObject	$object		Object
	 * @param	User			$user		Object user
	 * @param	Translate		$langs		Object langs
	 * @param	Conf			$conf		Object conf
	 * @return	int							Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// Affiliated farms (multi-farm mode): dossier and warehouses follow the flag of the third party
		if (($action == 'COMPANY_CREATE' || $action == 'COMPANY_MODIFY') && isModEnabled('dolifarm') && getDolGlobalInt('DOLIFARM_MULTIFARM')) {
			dol_include_once('/dolifarm/lib/dolifarm_affiliated.lib.php');
			$messages = array();
			$res = dolifarmSyncAffiliatedFarm($this->db, $object, $user, $messages);
			if ($res < 0) {
				// The third party is saved anyway: the user is warned and can save it again
				dol_syslog("Trigger '".$this->name."': affiliated farm not updated for third party ".$object->id.": ".implode(' ', $messages), LOG_ERR);
				setEventMessages($langs->trans('ErrorDolifarmAffiliatedSync'), $messages, 'warnings');
				return 0;
			}
			if ($res > 0) {
				setEventMessages(null, $messages, 'mesgs');
			}
			return $res > 0 ? 1 : 0;
		}

		// Codes of the common CRUD (DOLIFARMBUILDING_CREATE: class name) and of the status changes (DOLIFARM_BUILDING_CANCEL: TRIGGER_PREFIX)
		if (!isModEnabled('dolifarm') || !preg_match('/^DOLIFARM_?[A-Z]+_(CREATE|MODIFY|DELETE|CANCEL|REOPEN|VALIDATE|UNVALIDATE)$/', $action, $reg)) {
			return 0;
		}
		dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');
		if (empty($object->element) || !dolifarmExpiryFieldsOf($object->element)) {
			return 0;
		}

		dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". id=".$object->id);

		$event = $reg[1];
		if (in_array($event, array('REOPEN', 'VALIDATE', 'UNVALIDATE'))) {
			// The status is saved after the trigger: the object is enabled again
			$tmpobject = clone $object;
			$tmpobject->status = 1;
			$res = dolifarmExpirySyncEvents($this->db, $tmpobject, $user);
		} else {
			$res = dolifarmExpirySyncEvents($this->db, $object, $user, in_array($event, array('DELETE', 'CANCEL')));
		}
		if ($res < 0) {
			// The object is saved anyway: the events can be put in step again from the setup of the module
			dol_syslog("Trigger '".$this->name."': agenda events of the expiry dates not updated for ".$object->element." ".$object->id, LOG_ERR);
			return 0;
		}
		return $res > 0 ? 1 : 0;
	}
}
