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
 * \file    lib/dolifarm_expiry.lib.php
 * \ingroup dolifarm
 * \brief   Expiry dates of the farm (dossier, conductions, authorisations, machines): list, badges, agenda events
 *
 * Each expiry is a date field of an object. A date is "overdue" when it is past, "soon" when it is within
 * DOLIFARM_EXPIRY_DAYS days. Milestones (end of the organic conversion) are not problems: they are shown
 * only until they are reached. When DOLIFARM_EXPIRY_AGENDA is on, each date has an event in the agenda,
 * kept in step by the triggers of the module (event code DOLIFARM_EXP_<KEY>).
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Definitions of the expiry dates
 *
 * @return array<string,array<string,mixed>>	Key => element, class, classfile, table, field, label (lang key), right,
 *												labelfield ('' if none), socfield ('' if none), milestone
 */
function dolifarmExpiryDefinitions()
{
	return array(
		'dossier_validity' => array('element' => 'dolifarmfarmdossier', 'class' => 'DoliFarmFarmDossier', 'table' => 'dolifarm_farmdossier', 'field' => 'date_validity_end', 'label' => 'ExpiryDossierValidity', 'labelfield' => '', 'socfield' => 'fk_soc', 'milestone' => 0),
		'plot_conduction' => array('element' => 'dolifarmplot', 'class' => 'DoliFarmPlot', 'table' => 'dolifarm_plot', 'field' => 'date_conduction_end', 'label' => 'ExpiryPlotConduction', 'labelfield' => 'label', 'socfield' => 'fk_soc', 'milestone' => 0),
		'plot_conversion' => array('element' => 'dolifarmplot', 'class' => 'DoliFarmPlot', 'table' => 'dolifarm_plot', 'field' => 'date_conversion_end', 'label' => 'ExpiryPlotConversion', 'labelfield' => 'label', 'socfield' => 'fk_soc', 'milestone' => 1),
		'building_conduction' => array('element' => 'dolifarmbuilding', 'class' => 'DoliFarmBuilding', 'table' => 'dolifarm_building', 'field' => 'date_conduction_end', 'label' => 'ExpiryBuildingConduction', 'labelfield' => 'label', 'socfield' => 'fk_soc', 'milestone' => 0),
		'agrodrug_authorization' => array('element' => 'dolifarmagrodrug', 'class' => 'DoliFarmAgroDrug', 'table' => 'dolifarm_agrodrug', 'field' => 'auth_expiration_date', 'label' => 'ExpiryAgrodrugAuthorization', 'labelfield' => 'label', 'socfield' => '', 'milestone' => 0),
		'machine_revision' => array('element' => 'dolifarmmachine', 'class' => 'DoliFarmMachine', 'table' => 'dolifarm_machine', 'field' => 'date_revision_next', 'label' => 'ExpiryMachineRevision', 'labelfield' => 'label', 'socfield' => 'fk_soc', 'milestone' => 0),
		'machine_insurance' => array('element' => 'dolifarmmachine', 'class' => 'DoliFarmMachine', 'table' => 'dolifarm_machine', 'field' => 'date_insurance_end', 'label' => 'ExpiryMachineInsurance', 'labelfield' => 'label', 'socfield' => 'fk_soc', 'milestone' => 0),
		'machine_sprayer' => array('element' => 'dolifarmmachine', 'class' => 'DoliFarmMachine', 'table' => 'dolifarm_machine', 'field' => 'date_sprayer_check_end', 'label' => 'ExpiryMachineSprayerCheck', 'labelfield' => 'label', 'socfield' => 'fk_soc', 'milestone' => 0),
	);
}

/**
 * Number of days before the date when an expiry is shown as near
 *
 * @return	int
 */
function dolifarmExpiryDays()
{
	return max(0, getDolGlobalInt('DOLIFARM_EXPIRY_DAYS', 60));
}

/**
 * Expiry definitions of an element, by date field
 *
 * @param	string	$element	Element of the object (dolifarmplot, ...)
 * @return	array<string,array<string,mixed>>	Field => definition (with its key in 'key')
 */
function dolifarmExpiryFieldsOf($element)
{
	$fields = array();
	foreach (dolifarmExpiryDefinitions() as $key => $def) {
		if ($def['element'] == $element) {
			$def['key'] = $key;
			$fields[$def['field']] = $def;
		}
	}
	return $fields;
}

/**
 * Date as a timestamp (the objects may hold a timestamp or a database date string)
 *
 * @param	mixed	$date	Date
 * @return	int|null		Timestamp, null if empty
 */
function dolifarmExpiryTimestamp($date)
{
	global $db;

	if ($date === null || $date === '' || $date === 0 || $date === '0') {
		return null;
	}
	if (is_numeric($date)) {
		return (int) $date;
	}
	$ts = $db->jdate($date);
	return $ts ? (int) $ts : null;
}

/**
 * State of an expiry date
 *
 * @param	mixed	$date		Date
 * @param	int		$milestone	1 for a milestone (reached is not a problem)
 * @return	string				'overdue', 'soon', 'reached' (milestone past), or '' (far or empty)
 */
function dolifarmExpiryState($date, $milestone = 0)
{
	$ts = dolifarmExpiryTimestamp($date);
	if ($ts === null) {
		return '';
	}
	$today = dol_get_first_hour(dol_now());
	if ($ts < $today) {
		return $milestone ? 'reached' : 'overdue';
	}
	if ($ts <= dol_time_plus_duree($today, dolifarmExpiryDays(), 'd')) {
		return 'soon';
	}
	return '';
}

/**
 * Badge for an expiry date (nothing when the date is far or empty)
 *
 * @param	mixed	$date		Date
 * @param	int		$milestone	1 for a milestone
 * @return	string				Html
 */
function dolifarmExpiryBadge($date, $milestone = 0)
{
	global $langs;

	$state = dolifarmExpiryState($date, $milestone);
	if ($state == 'overdue') {
		return ' '.dolGetBadge($langs->trans('ExpiryOverdue'), '', 'danger');
	}
	if ($state == 'soon') {
		$days = (int) floor((dolifarmExpiryTimestamp($date) - dol_get_first_hour(dol_now())) / 86400);
		$text = $days == 0 ? $langs->trans('ExpiryToday') : $langs->trans('ExpiryInDays', $days);
		return ' '.dolGetBadge($text, '', $milestone ? 'info' : 'warning');
	}
	return '';
}

/**
 * Overdue and near expiries, sorted by date (overdue first)
 *
 * @param	DoliDB					$db			Database handler
 * @param	User					$user		User (only the objects he can read)
 * @param	int						$limit		Max number of rows, 0 for all
 * @param	array<string,int>		$filters	'fk_soc' and/or 'fk_dossier' to restrict the list
 * @return	array<int,array<string,mixed>>		Rows: key, def, id, ref, label, fk_soc, date (timestamp), state
 */
function dolifarmExpiryList($db, $user, $limit = 0, $filters = array())
{
	$rows = array();
	$limitdate = dol_time_plus_duree(dol_get_first_hour(dol_now()), dolifarmExpiryDays(), 'd');
	$today = dol_get_first_hour(dol_now());

	foreach (dolifarmExpiryDefinitions() as $key => $def) {
		if (!$user->hasRight('dolifarm', $def['element'], 'read')) {
			continue;
		}
		if ((!empty($filters['fk_soc']) && !$def['socfield']) || (!empty($filters['fk_dossier']) && $def['element'] == 'dolifarmagrodrug')) {
			continue;
		}
		$sql = "SELECT t.rowid, t.ref, t.".$def['field']." as dateexp";
		$sql .= ($def['labelfield'] ? ", t.".$def['labelfield']." as label" : ", '' as label");
		$sql .= ($def['socfield'] ? ", t.".$def['socfield']." as fk_soc" : ", 0 as fk_soc");
		$sql .= " FROM ".$db->prefix().$def['table']." as t";
		$sql .= " WHERE t.entity IN (".getEntity($def['element']).")";
		$sql .= " AND (t.status IS NULL OR t.status <> 9)";
		$sql .= " AND t.".$def['field']." IS NOT NULL";
		$sql .= " AND t.".$def['field']." <= '".$db->idate($limitdate)."'";
		if ($def['milestone']) {
			$sql .= " AND t.".$def['field']." >= '".$db->idate($today)."'";
		}
		if (!empty($filters['fk_soc'])) {
			$sql .= " AND t.".$def['socfield']." = ".((int) $filters['fk_soc']);
		}
		if (!empty($filters['fk_dossier'])) {
			$sql .= ($def['element'] == 'dolifarmfarmdossier') ? " AND t.rowid = ".((int) $filters['fk_dossier']) : " AND t.fk_dossier = ".((int) $filters['fk_dossier']);
		}
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			continue;
		}
		while ($obj = $db->fetch_object($resql)) {
			$date = $db->jdate($obj->dateexp);
			$rows[] = array('key' => $key, 'def' => $def, 'id' => (int) $obj->rowid, 'ref' => $obj->ref, 'label' => (string) $obj->label,
				'fk_soc' => (int) $obj->fk_soc, 'date' => $date, 'state' => dolifarmExpiryState($date, $def['milestone']));
		}
		$db->free($resql);
	}

	usort($rows, function ($a, $b) {
		return $a['date'] - $b['date'];
	});
	if ($limit > 0) {
		$rows = array_slice($rows, 0, $limit);
	}
	return $rows;
}

/**
 * Html with the overdue and near expiries of an object, for the banner of its card
 *
 * @param	CommonObject	$object		Object
 * @return	string						Html, '' if nothing to show
 */
function dolifarmExpiryObjectWarnings($object)
{
	global $langs;

	$langs->load('dolifarm@dolifarm');
	if (!empty($object->status) && (int) $object->status == 9) {
		return '';
	}
	$out = array();
	foreach (dolifarmExpiryFieldsOf($object->element) as $field => $def) {
		$value = isset($object->$field) ? $object->$field : null;
		if (dolifarmExpiryState($value, $def['milestone']) && dolifarmExpiryState($value, $def['milestone']) != 'reached') {
			$out[] = $langs->trans($def['label']).' '.dol_print_date(dolifarmExpiryTimestamp($value), 'day').dolifarmExpiryBadge($value, $def['milestone']);
		}
	}
	return $out ? '<div class="dolifarm-expiry-warnings">'.img_picto('', 'fa-calendar-times', 'class="pictofixedwidth"').implode(' &nbsp; ', $out).'</div>' : '';
}

/**
 * Create, update or delete the agenda events of the expiry dates of an object
 *
 * @param	DoliDB			$db			Database handler
 * @param	CommonObject	$object		Object (with its dates loaded)
 * @param	User			$user		User making the change
 * @param	bool			$delete		True to delete all the events (object deleted)
 * @return	int							Number of events changed, <0 if KO
 */
function dolifarmExpirySyncEvents($db, $object, $user, $delete = false)
{
	global $conf, $langs;

	$fields = dolifarmExpiryFieldsOf($object->element);
	if (empty($fields) || empty($object->id) || !isModEnabled('agenda')) {
		return 0;
	}
	if (!$delete && !getDolGlobalInt('DOLIFARM_EXPIRY_AGENDA', 1)) {
		return 0;
	}
	require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
	$langs->load('dolifarm@dolifarm');

	$elementtype = $object->element.'@dolifarm';
	$disabled = (isset($object->status) && (int) $object->status == 9);
	$nb = 0;
	foreach ($fields as $field => $def) {
		$code = 'DOLIFARM_EXP_'.strtoupper($def['key']);
		$ts = isset($object->$field) ? dolifarmExpiryTimestamp($object->$field) : null;

		$eventid = 0;
		$sql = "SELECT id FROM ".$db->prefix()."actioncomm";
		$sql .= " WHERE code = '".$db->escape($code)."' AND fk_element = ".((int) $object->id)." AND elementtype = '".$db->escape($elementtype)."'";
		$sql .= " AND entity IN (".getEntity('agenda').")";
		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$eventid = (int) $obj->id;
		}

		$event = new ActionComm($db);
		if ($eventid > 0 && $event->fetch($eventid) <= 0) {
			$eventid = 0;
		}

		// No date, object disabled or deleted: no event
		if ($delete || $disabled || $ts === null) {
			if ($eventid > 0) {
				$res = $event->delete($user, 1);
				if ($res < 0) {
					dol_syslog(__FUNCTION__.' delete event '.$eventid.' '.$event->error, LOG_ERR);
					return -1;
				}
				$nb++;
			}
			continue;
		}

		$datep = dol_get_first_hour($ts);
		$datef = dol_get_last_hour($ts);
		$label = $langs->transnoentitiesnoconv($def['label']).': '.implode(' - ', array_filter(array((string) $object->ref, empty($object->label) ? '' : (string) $object->label), 'strlen'));
		$socid = ($def['socfield'] && !empty($object->{$def['socfield']})) ? (int) $object->{$def['socfield']} : 0;

		if ($eventid > 0) {
			if ((int) $event->datep == $datep && $event->label == $label && (int) $event->socid == $socid) {
				continue;	// Nothing changed
			}
			if ((int) $event->datep != $datep) {
				$event->percentage = 0;	// New date: to do again
			}
			$event->datep = $datep;
			$event->datef = $datef;
			$event->label = $label;
			$event->socid = $socid;
			$res = $event->update($user, 1);
		} else {
			$event->type_code = 'AC_OTH';
			$event->code = $code;
			$event->label = $label;
			$event->datep = $datep;
			$event->datef = $datef;
			$event->fulldayevent = 1;
			$event->percentage = 0;
			$event->socid = $socid;
			$event->fk_element = $object->id;
			$event->elementtype = $elementtype;
			$event->userownerid = $user->id;
			$event->note_private = $langs->transnoentitiesnoconv('ExpiryEventNote');
			$res = $event->create($user, 1);
		}
		if ($res < 0) {
			dol_syslog(__FUNCTION__.' '.$code.' '.$event->error, LOG_ERR);
			return -1;
		}
		$nb++;
	}
	return $nb;
}

/**
 * Bring the agenda events of all the expiry dates in step (for the data saved before, or after an import)
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User making the change
 * @return	int				Number of events changed, <0 if KO
 */
function dolifarmExpirySyncAll($db, $user)
{
	$elements = array();
	foreach (dolifarmExpiryDefinitions() as $def) {
		$elements[$def['element']] = $def;
	}

	$total = 0;
	foreach ($elements as $element => $def) {
		dol_include_once('/dolifarm/class/'.$element.'.class.php');
		$fieldlist = array();
		foreach (dolifarmExpiryFieldsOf($element) as $field => $tmp) {
			$fieldlist[] = "t.".$field." IS NOT NULL";
		}
		$sql = "SELECT t.rowid FROM ".$db->prefix().$def['table']." as t";
		$sql .= " WHERE t.entity IN (".getEntity($element).")";
		// Objects with a date, and those that may still have an event of a date now removed
		$sql .= " AND (".implode(" OR ", $fieldlist)." OR EXISTS (SELECT a.id FROM ".$db->prefix()."actioncomm as a WHERE a.fk_element = t.rowid AND a.elementtype = '".$db->escape($element.'@dolifarm')."' AND a.code LIKE 'DOLIFARM\_EXP\_%'))";
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			return -1;
		}
		$ids = array();
		while ($obj = $db->fetch_object($resql)) {
			$ids[] = (int) $obj->rowid;
		}
		foreach ($ids as $id) {
			$object = new $def['class']($db);
			if ($object->fetch($id) > 0) {
				$res = dolifarmExpirySyncEvents($db, $object, $user);
				if ($res < 0) {
					return -1;
				}
				$total += $res;
			}
		}
	}
	return $total;
}
