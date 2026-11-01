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
 * \file    lib/dolifarm_bio.lib.php
 * \ingroup dolifarm
 * \brief   Organic status of the plots: history of the periods and status at a date
 *
 * The history (table dolifarm_plot_biostatus) has periods CONV, CONVERSION and BIO (Reg. EU 2018/848, annex II
 * part I point 1.7; DM 229771/2022 art. 3: the conversion starts at the date of the notification). A period without
 * end date is still open; adding a period closes the open one on the day before.
 * The status at a date is a code of the dictionary dolifarm_c_biostatus (CONV, BIO_CONV_1..3, BIO_FULL, BIODYNAMIC),
 * the one the register (doliTrace) copies on the areas of the records. The fields fk_status_bio, date_conversion_start
 * and date_conversion_end of the plot are derived from the history when there is one.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Statuses of the periods of the history: code => key of translation
 *
 * @return	array<string,string>
 */
function dolifarmBioPeriodStatuses()
{
	return array('CONV' => 'BioPeriodConv', 'CONVERSION' => 'BioPeriodConversion', 'BIO' => 'BioPeriodBio');
}

/**
 * Reasons of a period: code => key of translation
 *
 * @return	array<string,string>
 */
function dolifarmBioPeriodReasons()
{
	return array(
		'NOTIFICATION' => 'BioReasonNotification',		// notification of organic activity
		'RETROACTIVE' => 'BioReasonRetroactive',		// retroactive recognition (Reg. EU 2018/848 art. 10.3)
		'RESTART' => 'BioReasonRestart',				// new conversion after a product not allowed (annex II 1.7.6)
		'END_CONVERSION' => 'BioReasonEndConversion',	// end of the conversion period
		'WITHDRAWAL' => 'BioReasonWithdrawal',			// exit from the organic control system
		'OTHER' => 'BioReasonOther',
	);
}

/**
 * Periods of the history of a plot, oldest first
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$plotid		Plot
 * @return	array<int,stdClass>	Rows with dates as timestamps (date_start, date_end)
 */
function dolifarmBioHistory($db, $plotid)
{
	$rows = array();
	$sql = "SELECT rowid, fk_plot, status, date_start, date_end, conversion_years, reason, reference, note";
	$sql .= " FROM ".$db->prefix()."dolifarm_plot_biostatus";
	$sql .= " WHERE fk_plot = ".((int) $plotid);
	$sql .= " ORDER BY date_start ASC, rowid ASC";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$obj->date_start = $db->jdate($obj->date_start);
		$obj->date_end = ($obj->date_end === null ? null : $db->jdate($obj->date_end));
		$rows[] = $obj;
	}
	$db->free($resql);
	return $rows;
}

/**
 * Planned end of a conversion period: start + years, minus one day
 *
 * @param	stdClass	$period		Period CONVERSION
 * @return	int						Timestamp
 */
function dolifarmBioConversionEnd($period)
{
	$years = ((int) $period->conversion_years > 0 ? (int) $period->conversion_years : 2);
	return dol_time_plus_duree(dol_time_plus_duree((int) $period->date_start, $years, 'y'), -1, 'd');
}

/**
 * Code of the dictionary for a period at a date
 *
 * @param	stdClass	$period		Period
 * @param	int			$date		Date (timestamp)
 * @param	string		$current	Current status of the plot (fk_status_bio), to keep BIODYNAMIC
 * @return	string
 */
function dolifarmBioPeriodCode($period, $date, $current = '')
{
	if ($period->status == 'BIO') {
		return ($current == 'BIODYNAMIC' ? 'BIODYNAMIC' : 'BIO_FULL');
	}
	if ($period->status == 'CONVERSION') {
		$years = ((int) $period->conversion_years > 0 ? (int) $period->conversion_years : 2);
		// Year of conversion: 1 in the first twelve months, then 2, then 3 (no more than the planned years)
		$n = 1;
		while ($n < $years && $n < 3 && dol_time_plus_duree((int) $period->date_start, $n, 'y') <= $date) {
			$n++;
		}
		return 'BIO_CONV_'.$n;
	}
	return 'CONV';
}

/**
 * Period of the history covering a date
 *
 * @param	array<int,stdClass>	$history	History (dolifarmBioHistory)
 * @param	int					$date		Date (timestamp)
 * @return	stdClass|null
 */
function dolifarmBioPeriodAt($history, $date)
{
	// Dates of the history are days in the timezone of the server (jdate)
	$day = dol_print_date($date, '%Y-%m-%d', 'tzserver');
	$found = null;
	foreach ($history as $period) {
		if (dol_print_date($period->date_start, '%Y-%m-%d', 'tzserver') <= $day && ($period->date_end === null || dol_print_date($period->date_end, '%Y-%m-%d', 'tzserver') >= $day)) {
			$found = $period;
		}
	}
	return $found;
}

/**
 * Organic status of a plot at a date (code of the dictionary dolifarm_c_biostatus)
 *
 * Without history the status of the plot (fk_status_bio) is used; with a history, a date outside the periods is CONV.
 *
 * @param	DoliDB		$db			Database handler
 * @param	int			$plotid		Plot
 * @param	int			$date		Date (timestamp)
 * @param	?string		$current	Status of the plot (fk_status_bio), read from the database when null
 * @return	string					Code, '' when unknown
 */
function dolifarmBioStatusAt($db, $plotid, $date, $current = null)
{
	if ($current === null) {
		$current = '';
		$resql = $db->query("SELECT fk_status_bio FROM ".$db->prefix()."dolifarm_plot WHERE rowid = ".((int) $plotid));
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj && $obj->fk_status_bio !== null) {
				$current = (string) $obj->fk_status_bio;
			}
			$db->free($resql);
		}
	}
	$history = dolifarmBioHistory($db, $plotid);
	if (empty($history)) {
		return (string) $current;
	}
	$period = dolifarmBioPeriodAt($history, $date);
	return ($period ? dolifarmBioPeriodCode($period, $date, (string) $current) : 'CONV');
}

/**
 * True when a code of the dictionary is organic or in conversion
 *
 * @param	string	$code	Code
 * @return	bool
 */
function dolifarmBioIsOrganic($code)
{
	return in_array((string) $code, array('BIO_CONV_1', 'BIO_CONV_2', 'BIO_CONV_3', 'BIO_FULL', 'BIODYNAMIC'));
}

/**
 * Copy the history on the derived fields of the plot: status today and dates of the last conversion
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$plotid		Plot
 * @return	int					>0 if OK, <0 if KO
 */
function dolifarmBioSync($db, $plotid)
{
	$history = dolifarmBioHistory($db, $plotid);
	if (empty($history)) {
		return 1;
	}
	$current = '';
	$resql = $db->query("SELECT fk_status_bio FROM ".$db->prefix()."dolifarm_plot WHERE rowid = ".((int) $plotid));
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj && $obj->fk_status_bio !== null) {
			$current = (string) $obj->fk_status_bio;
		}
		$db->free($resql);
	}
	$now = dol_now();
	$period = dolifarmBioPeriodAt($history, $now);
	$status = ($period ? dolifarmBioPeriodCode($period, $now, $current) : 'CONV');

	$convstart = null;
	$convend = null;
	foreach ($history as $p) {
		if ($p->status == 'CONVERSION') {
			$convstart = (int) $p->date_start;
			$convend = ($p->date_end !== null ? (int) $p->date_end : dolifarmBioConversionEnd($p));
		}
	}

	$sql = "UPDATE ".$db->prefix()."dolifarm_plot SET";
	$sql .= " fk_status_bio = '".$db->escape($status)."'";
	$sql .= ", date_conversion_start = ".($convstart ? "'".$db->idate($convstart)."'" : "NULL");
	$sql .= ", date_conversion_end = ".($convend ? "'".$db->idate($convend)."'" : "NULL");
	$sql .= " WHERE rowid = ".((int) $plotid);
	if (!$db->query($sql)) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	return 1;
}

/**
 * Add a period to the history of a plot. The open period before it is closed on the day before.
 *
 * @param	DoliDB		$db			Database handler
 * @param	User		$user		User
 * @param	int			$plotid		Plot
 * @param	string		$status		CONV, CONVERSION or BIO
 * @param	int			$datestart	Start (timestamp of the day)
 * @param	?int		$years		Years of conversion (CONVERSION only: 2, or 3 for perennial crops)
 * @param	string		$reason		Reason (dolifarmBioPeriodReasons)
 * @param	string		$reference	Reference of the notification or of the document of the control body
 * @param	string		$note		Note
 * @param	string		$error		Error message (translated)
 * @return	int						Id of the period, <0 if KO
 */
function dolifarmBioAddPeriod($db, $user, $plotid, $status, $datestart, $years, $reason, $reference, $note, &$error)
{
	global $langs;

	$langs->load('dolifarm@dolifarm');
	$error = '';
	if (!array_key_exists($status, dolifarmBioPeriodStatuses())) {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('BioPeriodStatus'));
		return -1;
	}
	if (empty($datestart)) {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('DateStart'));
		return -1;
	}
	if ($reason !== '' && !array_key_exists($reason, dolifarmBioPeriodReasons())) {
		$reason = 'OTHER';
	}
	if ($status == 'CONVERSION') {
		$years = ((int) $years >= 1 && (int) $years <= 3 ? (int) $years : 2);
	} else {
		$years = null;
	}

	$history = dolifarmBioHistory($db, $plotid);
	$last = (count($history) ? $history[count($history) - 1] : null);
	if ($last && (int) $datestart <= (int) $last->date_start) {
		$error = $langs->trans('ErrorBioPeriodBeforeLast', dol_print_date($last->date_start, 'day'));
		return -1;
	}
	if ($last && $last->date_end !== null && (int) $datestart <= (int) $last->date_end) {
		$error = $langs->trans('ErrorBioPeriodOverlap', dol_print_date($last->date_end, 'day'));
		return -1;
	}

	$db->begin();
	if ($last && $last->date_end === null) {
		$sql = "UPDATE ".$db->prefix()."dolifarm_plot_biostatus SET date_end = '".$db->idate(dol_time_plus_duree((int) $datestart, -1, 'd'))."'";
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $last->rowid);
		if (!$db->query($sql)) {
			$error = $db->lasterror();
			$db->rollback();
			return -1;
		}
	}
	$sql = "INSERT INTO ".$db->prefix()."dolifarm_plot_biostatus (fk_plot, status, date_start, date_end, conversion_years, reason, reference, note, date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $plotid).", '".$db->escape($status)."', '".$db->idate($datestart)."', NULL";
	$sql .= ", ".($years === null ? "NULL" : ((int) $years));
	$sql .= ", ".($reason !== '' ? "'".$db->escape($reason)."'" : "NULL");
	$sql .= ", ".($reference !== '' ? "'".$db->escape(dol_trunc($reference, 128, 'right', 'UTF-8', 1))."'" : "NULL");
	$sql .= ", ".($note !== '' ? "'".$db->escape(dol_trunc($note, 255, 'right', 'UTF-8', 1))."'" : "NULL");
	$sql .= ", '".$db->idate(dol_now())."', ".((int) $user->id).")";
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	$id = (int) $db->last_insert_id($db->prefix()."dolifarm_plot_biostatus");
	if (dolifarmBioSync($db, $plotid) < 0) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	$db->commit();
	return $id;
}

/**
 * Delete the last period of the history of a plot, and open again the one before it
 *
 * @param	DoliDB	$db			Database handler
 * @param	User	$user		User
 * @param	int		$plotid		Plot
 * @param	int		$periodid	Period (must be the last one)
 * @param	string	$error		Error message (translated)
 * @return	int					>0 if OK, <0 if KO
 */
function dolifarmBioDeleteLastPeriod($db, $user, $plotid, $periodid, &$error)
{
	global $langs;

	$error = '';
	$history = dolifarmBioHistory($db, $plotid);
	$n = count($history);
	if (!$n || (int) $history[$n - 1]->rowid != (int) $periodid) {
		$error = $langs->trans('ErrorBioPeriodNotLast');
		return -1;
	}
	$db->begin();
	if (!$db->query("DELETE FROM ".$db->prefix()."dolifarm_plot_biostatus WHERE rowid = ".((int) $periodid)." AND fk_plot = ".((int) $plotid))) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	// The period before was closed by the deleted one: open it again
	if ($n > 1) {
		$prev = $history[$n - 2];
		if ($prev->date_end !== null && (int) $prev->date_end == (int) dol_time_plus_duree((int) $history[$n - 1]->date_start, -1, 'd')) {
			$sql = "UPDATE ".$db->prefix()."dolifarm_plot_biostatus SET date_end = NULL, fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $prev->rowid);
			if (!$db->query($sql)) {
				$error = $db->lasterror();
				$db->rollback();
				return -1;
			}
		}
		if (dolifarmBioSync($db, $plotid) < 0) {
			$error = $db->lasterror();
			$db->rollback();
			return -1;
		}
	}
	$db->commit();
	return 1;
}

/**
 * Close the open period of a plot on a date (for example the end of the conversion, before the period BIO)
 *
 * @param	DoliDB	$db			Database handler
 * @param	User	$user		User
 * @param	int		$plotid		Plot
 * @param	int		$dateend	End (timestamp of the day)
 * @param	string	$error		Error message (translated)
 * @return	int					>0 if OK, <0 if KO
 */
function dolifarmBioClosePeriod($db, $user, $plotid, $dateend, &$error)
{
	global $langs;

	$error = '';
	$history = dolifarmBioHistory($db, $plotid);
	$n = count($history);
	if (!$n || $history[$n - 1]->date_end !== null) {
		$error = $langs->trans('ErrorBioNoOpenPeriod');
		return -1;
	}
	if ((int) $dateend < (int) $history[$n - 1]->date_start) {
		$error = $langs->trans('ErrorBioPeriodEndBeforeStart');
		return -1;
	}
	$db->begin();
	$sql = "UPDATE ".$db->prefix()."dolifarm_plot_biostatus SET date_end = '".$db->idate($dateend)."', fk_user_modif = ".((int) $user->id);
	$sql .= " WHERE rowid = ".((int) $history[$n - 1]->rowid);
	if (!$db->query($sql) || dolifarmBioSync($db, $plotid) < 0) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	$db->commit();
	return 1;
}
