<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
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
 * \file    dolifarm/lib/dolifarm_area.lib.php
 * \ingroup dolifarm
 * \brief   Dashboard of the area of doliFarm (dolifarmindex.php, docs/dashboard_progetto.md): situation of the three
 *          areas of the work (Farm dossier, Field notebook, Field data), setup to complete, alerts, activity of the
 *          campaign, farms, state of the external databases. Every box is computed on a scope: one farm or all the
 *          farms the user can see, with one query for each data.
 */

dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_landuse.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_start.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');

/**
 * Days after which a draft treatment is late: the record must be written within 30 days (Reg. (EU) 2023/564)
 */
const DOLIFARM_AREA_DRAFT_TREATMENT_DAYS = 30;

/**
 * Managed farms the user can see, by name
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User
 * @param	int		$sale	Filter on the sales representative (dolifarmSaleFilterValue()), 0 for none
 * @return	array<int,string>	Id of the third party => name
 */
function dolifarmAreaFarms($db, $user, $sale = 0)
{
	$farms = array();
	$ids = dolifarmManagedFarmIds();
	$visible = dolifarmSocVisibleIds($user);
	if ($visible !== null) {
		$ids = array_intersect($ids, $visible);
	}
	if (!empty($user->socid)) {
		$ids = array_intersect($ids, array((int) $user->socid));
	}
	if (empty($ids)) {
		return $farms;
	}
	$sql = "SELECT s.rowid, s.nom FROM ".$db->prefix()."societe as s";
	$sql .= " WHERE s.rowid IN (".$db->sanitize(implode(',', array_map('intval', $ids))).") AND s.entity IN (".getEntity('societe').")";
	$sql .= dolifarmSqlSaleFilter($sale, 's.rowid');
	$sql .= " ORDER BY s.nom ASC";
	foreach (dolifarmAreaRows($db, $sql) as $obj) {
		$farms[(int) $obj->rowid] = (string) $obj->nom;
	}
	return $farms;
}

/**
 * Rows of a query, or an empty array (error logged)
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	Query
 * @return	array<int,object>
 */
function dolifarmAreaRows($db, $sql)
{
	$rows = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('dolifarmAreaRows '.$db->lasterror(), LOG_ERR);
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
	$db->free($resql);
	return $rows;
}

/**
 * First row of a query, or null
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	Query
 * @return	?object
 */
function dolifarmAreaRow($db, $sql)
{
	$rows = dolifarmAreaRows($db, $sql);
	return $rows[0] ?? null;
}

/**
 * List of farms for SQL
 *
 * @param	DoliDB	$db		Database handler
 * @param	int[]	$socids	Farms
 * @return	string			"1,2,3", "0" when empty
 */
function dolifarmAreaIn($db, array $socids)
{
	$socids = array_filter(array_map('intval', $socids));
	return empty($socids) ? '0' : $db->sanitize(implode(',', $socids));
}

/**
 * Situation of the three areas of the work
 *
 * Each area has 'title', 'icon', 'url', 'state' (done, warn, todo, na), 'facts' (texts), 'config' (setup to complete)
 * and 'work' (things to do), as lists of array(text, url, label of the link).
 *
 * @param	DoliDB		$db			Database handler
 * @param	User		$user		User
 * @param	Translate	$langs		Languages
 * @param	int[]		$socids		Farms of the scope
 * @param	bool		$withsetup	True to add the required steps of "Help me" still to do (not for one row of the farms)
 * @return	array<string,array<string,mixed>>	Keys dossier, notebook, field
 */
function dolifarmAreaSituation($db, $user, $langs, array $socids, $withsetup = true)
{
	$langs->loadLangs(array('dolifarm@dolifarm'));
	$p = $db->prefix();
	$year = (int) dol_print_date(dol_now(), '%Y');
	$in = dolifarmAreaIn($db, $socids);
	$admin = !empty($user->admin);

	// Required steps of "Help me" still to do (they already follow the role of the user)
	$setup = array('dossier' => array(), 'notebook' => array());
	if ($withsetup) {
		foreach (dolifarmStartSteps($db, $user) as $step) {
			if (!$step['optional'] && !$step['done']) {
				$setup[$step['group'] == 'Notebook' ? 'notebook' : 'dossier'][] = array($langs->trans($step['label']), $step['url'], $langs->trans('StartDo'));
			}
		}
	}
	$areas = array();

	// Farm dossier
	$area = array(
		'title' => $langs->trans(getDolGlobalInt('DOLIFARM_MULTIFARM') ? 'DoliFarmFarmDossiers' : 'DoliFarmFarmDossierMenu'),
		'icon' => dolifarm_get_fa_icon('farmdossier'), 'url' => dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1),
		'facts' => array(), 'config' => $setup['dossier'], 'work' => array(),
	);
	$obj = dolifarmAreaRow($db, "SELECT COUNT(DISTINCT d.fk_soc) as farms FROM ".$p."dolifarm_farmdossier as d WHERE d.fk_soc IN (".$in.") AND d.entity IN (".getEntity('dolifarmfarmdossier').") AND d.status IN (0, 1)");
	$withdossier = $obj ? (int) $obj->farms : 0;
	$obj = dolifarmAreaRow($db, "SELECT COUNT(d.rowid) as nb FROM ".$p."dolifarm_farmdossier as d WHERE d.fk_soc IN (".$in.") AND d.entity IN (".getEntity('dolifarmfarmdossier').") AND d.status IN (0, 1) AND (d.fk_warehouse_inputs IS NULL OR d.fk_warehouse_inputs <= 0 OR d.fk_warehouse_products IS NULL OR d.fk_warehouse_products <= 0)");
	$nowarehouse = (isModEnabled('stock') && $obj) ? (int) $obj->nb : 0;
	$obj = dolifarmAreaRow($db, "SELECT COUNT(t.rowid) as nb, SUM(t.size_total) as size FROM ".$p."dolifarm_plot as t WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolifarmplot').") AND t.status IN (0, 1)");
	$nbplots = $obj ? (int) $obj->nb : 0;
	if (count($socids) > 1) {
		$area['facts'][] = $langs->trans('AreaFarmsDossiers', count($socids), $withdossier);
	}
	$area['facts'][] = $langs->trans('AreaPlots', $nbplots, price((float) ($obj->size ?? 0), 0, $langs, 0, 0, 2));
	if (count($socids) > $withdossier) {
		$area['config'][] = array($langs->trans('AreaFarmsWithoutDossier', count($socids) - $withdossier), dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1), $langs->trans('AreaImportDossier'));
	}
	if ($nowarehouse > 0) {
		$area['config'][] = array($langs->trans('AreaDossiersWithoutWarehouse', $nowarehouse), dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1), $langs->trans('AreaSee'));
	}
	// Land uses of the graphic plans without a crop: the import creates no production plan for them
	if (isModEnabled('dolitrace')) {
		$codes = array();
		$sql = "SELECT DISTINCT t.land_use_code FROM ".$p."dolifarm_dossierpcg as t";
		$sql .= " LEFT JOIN ".$p."dolifarm_c_landuse as c ON c.code = t.land_use_code";
		$sql .= " WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolifarmfarmdossier').")";
		$sql .= " AND (c.rowid IS NULL OR (c.active = 1 AND (c.crop_ref IS NULL OR c.crop_ref = '')))";
		foreach (dolifarmAreaRows($db, $sql) as $row) {
			$code = trim((string) $row->land_use_code);
			if ($code !== '' && $code !== '000' && !in_array($code, dolifarmLandUseNonSau(), true)) {
				$codes[] = $code;
			}
		}
		if (!empty($codes)) {
			sort($codes);
			if ($admin) {
				$area['config'][] = array($langs->trans('AreaLandUseUnlinked', implode(', ', $codes)), DOL_URL_ROOT.'/admin/dict.php?mainmenu=home', $langs->trans('AreaLinkLandUses'));
			} else {
				$area['config'][] = array($langs->trans('AreaLandUseUnlinked', implode(', ', $codes)).' '.$langs->trans('AreaAskAdmin'), '', '');
			}
		}
	}
	$area['state'] = (empty($socids) || $withdossier == 0 ? 'todo' : (empty($area['config']) ? 'done' : 'warn'));
	$areas['dossier'] = $area;

	// Field notebook
	$area = array(
		'title' => $langs->trans('AreaNotebook'), 'icon' => dolifarm_get_fa_icon('dolitrace'),
		'url' => dol_buildpath('/dolitrace/dolitraceindex.php', 1), 'facts' => array(), 'config' => array(), 'work' => array(), 'state' => 'na',
	);
	if (!isModEnabled('dolitrace')) {
		$area['facts'][] = $langs->trans('AreaModuleOff', 'doliTrace');
		$area['url'] = '';
	} elseif (!$user->hasRight('dolitrace', 'dolitracecropplan', 'read')) {
		$area['facts'][] = $langs->trans('AreaNoPermission');
	} else {
		$area['config'] = $setup['notebook'];
		$plans = array(0 => 0, 1 => 0, 2 => 0);
		$sql = "SELECT t.status, COUNT(t.rowid) as nb FROM ".$p."dolitrace_cropplan as t";
		$sql .= " WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolitracecropplan').") AND t.season = ".$year." GROUP BY t.status";
		foreach (dolifarmAreaRows($db, $sql) as $row) {
			$plans[(int) $row->status] = (int) $row->nb;
		}
		$obj = dolifarmAreaRow($db, "SELECT COUNT(DISTINCT t.fk_soc) as farms FROM ".$p."dolitrace_cropplan as t WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolitracecropplan').") AND t.season = ".$year." AND t.status IN (0, 1, 2)");
		$farmswithplans = $obj ? (int) $obj->farms : 0;
		$area['facts'][] = $langs->trans('AreaPlans', $year, $plans[0], $plans[1], $plans[2]);
		$listurl = dol_buildpath('/dolitrace/dolitracecropplan_list.php', 1).'?mainmenu=dolifarm'.(count($socids) == 1 ? '&search_fk_soc='.((int) reset($socids)) : '');
		if ($withdossier > $farmswithplans) {
			$area['work'][] = array(count($socids) == 1 ? $langs->trans('AreaNoPlans', $year) : $langs->trans('AreaFarmsWithoutPlans', $year, $withdossier - $farmswithplans), $listurl, $langs->trans(count($socids) == 1 ? 'AreaCreatePlans' : 'AreaSee'));
		}
		if ($plans[0] > 0) {
			$area['work'][] = array($langs->trans('AreaDraftPlans', $plans[0]), $listurl.'&search_status=0', $langs->trans('AreaStart'));
		}
		if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
			$obj = dolifarmAreaRow($db, "SELECT COUNT(t.rowid) as nb FROM ".$p."dolitrace_treatment as t WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolitracetreatment').") AND t.status = 0");
			if ($obj && (int) $obj->nb > 0) {
				$area['work'][] = array($langs->trans('AreaDraftTreatments', (int) $obj->nb), dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?mainmenu=dolifarm&search_status=0'.(count($socids) == 1 ? '&search_fk_soc='.((int) reset($socids)) : ''), $langs->trans('AreaValidate'));
			}
		}
		$area['state'] = ($plans[0] + $plans[1] + $plans[2] == 0 ? 'todo' : (empty($area['config']) && empty($area['work']) ? 'done' : 'warn'));
	}
	$areas['notebook'] = $area;

	// Field data
	$area = array(
		'title' => $langs->trans('AreaField'), 'icon' => 'fa-satellite-dish',
		'url' => dol_buildpath('/dolifield/dolifieldindex.php', 1), 'facts' => array(), 'config' => array(), 'work' => array(), 'state' => 'na',
	);
	if (!isModEnabled('dolifield')) {
		$area['facts'][] = $langs->trans('AreaModuleOff', 'doliField');
		$area['url'] = '';
	} elseif (!$user->hasRight('dolifield', 'read')) {
		$area['facts'][] = $langs->trans('AreaNoPermission');
	} else {
		$sql = "SELECT COUNT(DISTINCT w.fk_plot) as nb, MAX(d.day) as lastday FROM ".$p."dolifield_weather_plot as w";
		$sql .= " INNER JOIN ".$p."dolifarm_plot as pl ON pl.rowid = w.fk_plot";
		$sql .= " LEFT JOIN ".$p."dolifield_weather_daily as d ON d.fk_station = w.fk_station AND d.day <= '".$db->idate(dol_now(), 'gmt')."'";
		$sql .= " WHERE pl.fk_soc IN (".$in.") AND pl.status IN (0, 1) AND w.entity IN (".getEntity('dolifarmplot').")";
		$obj = dolifarmAreaRow($db, $sql);
		$linked = $obj ? (int) $obj->nb : 0;
		$area['facts'][] = $langs->trans('AreaWeatherPlots', $linked, $nbplots);
		if ($obj && $obj->lastday) {
			$area['facts'][] = $langs->trans('AreaWeatherLastDay', dol_print_date($db->jdate($obj->lastday), 'day'));
		}
		if ($linked < $nbplots) {
			$area['config'][] = array($langs->trans('AreaPlotsWithoutWeather', $nbplots - $linked), dol_buildpath('/dolifield/dolifield_weather.php', 1), $langs->trans('AreaLinkWeather'));
		}
		$area['state'] = ($linked == 0 ? 'todo' : (empty($area['config']) ? 'done' : 'warn'));
	}
	$areas['field'] = $area;

	return $areas;
}

/**
 * Alerts of the scope, the most serious first
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Languages
 * @param	int[]		$socids	Farms of the scope
 * @return	array<int,array{0:string,1:string,2:string,3:string}>	Rows: level (error, warning), text, url, label of the link
 */
function dolifarmAreaAlerts($db, $user, $langs, array $socids)
{
	$langs->loadLangs(array('dolifarm@dolifarm'));
	$alerts = array();
	$p = $db->prefix();
	$in = dolifarmAreaIn($db, $socids);
	$now = dol_now();
	$one = (count($socids) == 1 ? (int) reset($socids) : 0);

	// Revoked plant protection products still in the warehouses of the technical inputs of the farms
	if (isModEnabled('stock') && $user->hasRight('dolifarm', 'dolifarmagrodrug', 'read')) {
		$sql = "SELECT a.rowid, a.ref, a.label, a.date_revocation, SUM(ps.reel) as qty FROM ".$p."dolifarm_agrodrug as a";
		$sql .= " INNER JOIN ".$p."product_stock as ps ON ps.fk_product = a.fk_product";
		$sql .= " INNER JOIN ".$p."dolifarm_farmdossier as d ON d.fk_warehouse_inputs = ps.fk_entrepot AND d.status IN (0, 1)";
		$sql .= " WHERE d.fk_soc IN (".$in.") AND a.entity IN (".getEntity('dolifarmagrodrug').") AND a.date_revocation IS NOT NULL";
		$sql .= " GROUP BY a.rowid, a.ref, a.label, a.date_revocation HAVING SUM(ps.reel) > 0 ORDER BY a.date_revocation";
		$revoked = $future = array();
		foreach (dolifarmAreaRows($db, $sql) as $row) {
			if ($db->jdate($row->date_revocation) <= $now) {
				$revoked[] = (string) ($row->label ?: $row->ref);
			} else {
				$future[] = (string) ($row->label ?: $row->ref).' ('.dol_print_date($db->jdate($row->date_revocation), 'day').')';
			}
		}
		$url = dol_buildpath('/dolifarm/dolifarmagrodrug_list.php', 1);
		if ($revoked) {
			$alerts[] = array('error', $langs->trans('AreaAlertRevoked', count($revoked), dol_trunc(implode(', ', $revoked), 120)), $url, $langs->trans('AreaSee'));
		}
		if ($future) {
			$alerts[] = array('warning', $langs->trans('AreaAlertRevoking', count($future), dol_trunc(implode(', ', $future), 120)), $url, $langs->trans('AreaSee'));
		}
	}

	if (isModEnabled('dolitrace')) {
		// Stock movements to regularize (unit, warehouse or quantity that could not be moved)
		$nb = 0;
		if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
			$obj = dolifarmAreaRow($db, "SELECT COUNT(DISTINCT tp.fk_treatment) as nb FROM ".$p."dolitrace_treatment_product as tp INNER JOIN ".$p."dolitrace_treatment as t ON t.rowid = tp.fk_treatment WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolitracetreatment').") AND tp.stock_status = 2");
			if ($obj && (int) $obj->nb > 0) {
				$alerts[] = array('warning', $langs->trans('AreaAlertStockTreatments', (int) $obj->nb), dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?mainmenu=dolifarm'.($one ? '&search_fk_soc='.$one : ''), $langs->trans('AreaSee'));
			}
			// Draft treatments older than the delay of the record
			$limit = dol_time_plus_duree($now, -DOLIFARM_AREA_DRAFT_TREATMENT_DAYS, 'd');
			$obj = dolifarmAreaRow($db, "SELECT COUNT(t.rowid) as nb FROM ".$p."dolitrace_treatment as t WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolitracetreatment').") AND t.status = 0 AND t.date_creation < '".$db->idate($limit)."'");
			if ($obj && (int) $obj->nb > 0) {
				$alerts[] = array('error', $langs->trans('AreaAlertOldDrafts', (int) $obj->nb, DOLIFARM_AREA_DRAFT_TREATMENT_DAYS), dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?mainmenu=dolifarm&search_status=0'.($one ? '&search_fk_soc='.$one : ''), $langs->trans('AreaValidate'));
			}
		}
		if ($user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
			$obj = dolifarmAreaRow($db, "SELECT COUNT(o.rowid) as nb FROM ".$p."dolitrace_operation as o INNER JOIN ".$p."dolitrace_cropplan as c ON c.rowid = o.fk_cropplan WHERE c.fk_soc IN (".$in.") AND o.entity IN (".getEntity('dolitraceoperation').") AND o.stock_status = 2");
			$nb = $obj ? (int) $obj->nb : 0;
			if ($nb > 0) {
				$alerts[] = array('warning', $langs->trans('AreaAlertStockOperations', $nb), dol_buildpath('/dolitrace/dolitraceoperation_list.php', 1).'?mainmenu=dolifarm', $langs->trans('AreaSee'));
			}
		}
	}

	// Weather stations of the plots with an error or without recent data
	if (isModEnabled('dolifield') && $user->hasRight('dolifield', 'read')) {
		$sql = "SELECT s.rowid, s.label, s.last_error, s.forecast_error, s.date_last_load FROM ".$p."dolifield_weather_station as s";
		$sql .= " WHERE s.rowid IN (SELECT w.fk_station FROM ".$p."dolifield_weather_plot as w INNER JOIN ".$p."dolifarm_plot as pl ON pl.rowid = w.fk_plot WHERE pl.fk_soc IN (".$in."))";
		$errors = $old = array();
		$limit = dol_time_plus_duree($now, -3, 'd');
		foreach (dolifarmAreaRows($db, $sql) as $row) {
			if ((string) $row->last_error !== '' || (string) $row->forecast_error !== '') {
				$errors[] = (string) $row->label;
			} elseif ($row->date_last_load && $db->jdate($row->date_last_load) < $limit) {
				$old[] = (string) $row->label;
			}
		}
		$url = dol_buildpath('/dolifield/dolifield_weather.php', 1);
		if ($errors) {
			$alerts[] = array('warning', $langs->trans('AreaAlertStationErrors', count($errors), dol_trunc(implode(', ', $errors), 100)), $url, $langs->trans('AreaSee'));
		}
		if ($old) {
			$alerts[] = array('warning', $langs->trans('AreaAlertStationOld', count($old), dol_trunc(implode(', ', $old), 100)), $url, $langs->trans('AreaSee'));
		}
	}

	usort($alerts, function ($a, $b) {
		return ($a[0] == $b[0] ? 0 : ($a[0] == 'error' ? -1 : 1));
	});
	return $alerts;
}

/**
 * Activity of the campaign: counts with their list, and the last records when the scope is one farm
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Languages
 * @param	int[]		$socids	Farms of the scope
 * @return	array{counts:array<int,array{0:string,1:string,2:string}>,last:array<int,array<string,mixed>>}	Counts: label, value, url
 */
function dolifarmAreaActivity($db, $user, $langs, array $socids)
{
	$langs->loadLangs(array('dolifarm@dolifarm'));
	$res = array('counts' => array(), 'last' => array());
	if (!isModEnabled('dolitrace')) {
		return $res;
	}
	$p = $db->prefix();
	$in = dolifarmAreaIn($db, $socids);
	$year = (int) dol_print_date(dol_now(), '%Y');
	$yearstart = "'".$db->idate(dol_mktime(0, 0, 0, 1, 1, $year))."'";
	$monthstart = "'".$db->idate(dol_get_first_day($year, (int) dol_print_date(dol_now(), '%m')))."'";
	$one = (count($socids) == 1 ? (int) reset($socids) : 0);
	$soc = ($one ? '&search_fk_soc='.$one : '');

	if ($user->hasRight('dolitrace', 'dolitraceoperation', 'read')) {
		$obj = dolifarmAreaRow($db, "SELECT SUM(CASE WHEN o.date_op >= ".$monthstart." THEN 1 ELSE 0 END) as month, COUNT(o.rowid) as nb FROM ".$p."dolitrace_operation as o INNER JOIN ".$p."dolitrace_cropplan as c ON c.rowid = o.fk_cropplan WHERE c.fk_soc IN (".$in.") AND o.entity IN (".getEntity('dolitraceoperation').") AND o.date_op >= ".$yearstart);
		$res['counts'][] = array($langs->trans('AreaActOperations', $year), $langs->trans('AreaActMonthYear', (int) ($obj->month ?? 0), (int) ($obj->nb ?? 0)), dol_buildpath('/dolitrace/dolitraceoperation_list.php', 1).'?mainmenu=dolifarm');
		if ($one) {
			$sql = "SELECT o.rowid, o.ref, o.label, o.date_op as date FROM ".$p."dolitrace_operation as o INNER JOIN ".$p."dolitrace_cropplan as c ON c.rowid = o.fk_cropplan";
			$sql .= " WHERE c.fk_soc = ".$one." AND o.entity IN (".getEntity('dolitraceoperation').") ORDER BY o.date_op DESC, o.rowid DESC".$db->plimit(5);
			foreach (dolifarmAreaRows($db, $sql) as $row) {
				$res['last'][] = array('date' => $db->jdate($row->date), 'type' => $langs->trans('AreaActOperation'), 'ref' => $row->ref, 'label' => (string) $row->label, 'url' => dol_buildpath('/dolitrace/dolitraceoperation_card.php', 1).'?id='.((int) $row->rowid), 'icon' => dolifarm_get_fa_icon('operation'));
			}
		}
	}
	if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
		$obj = dolifarmAreaRow($db, "SELECT SUM(CASE WHEN t.status = 0 THEN 1 ELSE 0 END) as draft, SUM(CASE WHEN t.status = 1 THEN 1 ELSE 0 END) as valid FROM ".$p."dolitrace_treatment as t WHERE t.fk_soc IN (".$in.") AND t.entity IN (".getEntity('dolitracetreatment').") AND t.date_start >= ".$yearstart);
		$res['counts'][] = array($langs->trans('AreaActTreatments', $year), $langs->trans('AreaActDraftValid', (int) ($obj->draft ?? 0), (int) ($obj->valid ?? 0)), dol_buildpath('/dolitrace/dolitracetreatment_list.php', 1).'?mainmenu=dolifarm'.$soc);
		if ($one) {
			$sql = "SELECT t.rowid, t.ref, t.reason as label, t.date_start as date FROM ".$p."dolitrace_treatment as t";
			$sql .= " WHERE t.fk_soc = ".$one." AND t.entity IN (".getEntity('dolitracetreatment').") AND t.status <> 9 ORDER BY t.date_start DESC, t.rowid DESC".$db->plimit(5);
			foreach (dolifarmAreaRows($db, $sql) as $row) {
				$res['last'][] = array('date' => $db->jdate($row->date), 'type' => $langs->trans('AreaActTreatment'), 'ref' => $row->ref, 'label' => (string) $row->label, 'url' => dol_buildpath('/dolitrace/dolitracetreatment_card.php', 1).'?id='.((int) $row->rowid), 'icon' => 'fa-spray-can');
			}
		}
	}
	if ($user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
		$obj = dolifarmAreaRow($db, "SELECT COUNT(h.rowid) as nb FROM ".$p."dolitrace_harvest as h INNER JOIN ".$p."dolitrace_cropplan as c ON c.rowid = h.fk_cropplan WHERE c.fk_soc IN (".$in.") AND h.entity IN (".getEntity('dolitraceharvest').") AND h.date_harvest >= ".$yearstart);
		$res['counts'][] = array($langs->trans('AreaActHarvests', $year), (string) ((int) ($obj->nb ?? 0)), dol_buildpath('/dolitrace/dolitraceharvest_list.php', 1).'?mainmenu=dolifarm');
		if ($one) {
			$sql = "SELECT h.rowid, h.ref, h.label, h.date_harvest as date FROM ".$p."dolitrace_harvest as h INNER JOIN ".$p."dolitrace_cropplan as c ON c.rowid = h.fk_cropplan";
			$sql .= " WHERE c.fk_soc = ".$one." AND h.entity IN (".getEntity('dolitraceharvest').") ORDER BY h.date_harvest DESC, h.rowid DESC".$db->plimit(5);
			foreach (dolifarmAreaRows($db, $sql) as $row) {
				$res['last'][] = array('date' => $db->jdate($row->date), 'type' => $langs->trans('AreaActHarvest'), 'ref' => $row->ref, 'label' => (string) $row->label, 'url' => dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1).'?id='.((int) $row->rowid), 'icon' => dolifarm_get_fa_icon('harvest'));
			}
		}
	}
	usort($res['last'], function ($a, $b) {
		return ((int) $b['date']) <=> ((int) $a['date']);
	});
	$res['last'] = array_slice($res['last'], 0, 6);
	return $res;
}

/**
 * One row for each farm: state of the three areas, alerts and near expiry dates, the most serious first
 *
 * @param	DoliDB				$db		Database handler
 * @param	User				$user	User
 * @param	Translate			$langs	Languages
 * @param	array<int,string>	$farms	Farms: id => name
 * @return	array<int,array<string,mixed>>
 */
function dolifarmAreaFarmRows($db, $user, $langs, array $farms)
{
	$expiries = array();
	foreach (dolifarmExpiryList($db, $user) as $row) {
		if ($row['fk_soc'] > 0) {
			$expiries[(int) $row['fk_soc']] = ($expiries[(int) $row['fk_soc']] ?? 0) + 1;
		}
	}
	$weight = array('todo' => 3, 'warn' => 2, 'done' => 0, 'na' => 0);
	$rows = array();
	foreach ($farms as $socid => $name) {
		$situation = dolifarmAreaSituation($db, $user, $langs, array($socid), false);
		$alerts = dolifarmAreaAlerts($db, $user, $langs, array($socid));
		$score = 0;
		foreach ($situation as $area) {
			$score += $weight[$area['state']] ?? 0;
		}
		foreach ($alerts as $alert) {
			$score += ($alert[0] == 'error' ? 5 : 2);
		}
		$rows[] = array('socid' => $socid, 'name' => $name, 'situation' => $situation, 'alerts' => count($alerts), 'expiries' => $expiries[$socid] ?? 0, 'score' => $score);
	}
	usort($rows, function ($a, $b) {
		return ($b['score'] <=> $a['score']) ?: strcasecmp($a['name'], $b['name']);
	});
	return $rows;
}

/**
 * State of the automatic update of the external databases, from the scheduled jobs and the data of the modules
 *
 * @param	DoliDB		$db		Database handler
 * @param	User		$user	User
 * @param	Translate	$langs	Languages
 * @return	array<int,array<string,mixed>>	Rows: source, module, data, lastrun, nextrun, state (ok, late, error, off), message, url
 */
function dolifarmAreaExternal($db, $user, $langs)
{
	$langs->loadLangs(array('dolifarm@dolifarm', 'cron'));
	$p = $db->prefix();
	$now = dol_now();
	$jobs = array();
	foreach (dolifarmAreaRows($db, "SELECT label, status, frequency, unitfrequency, datelastrun, lastresult, datenextrun FROM ".$p."cronjob WHERE label IN ('DolifarmPppSyncJob', 'DolifarmEppoSyncJob', 'DolifieldWeatherJob', 'DolifieldForecastJob') AND entity IN (0, ".((int) $GLOBALS['conf']->entity).")") as $row) {
		$jobs[(string) $row->label] = $row;
	}
	// State of a job: off (missing or disabled), late (never run or beyond two periods), error (last result), ok
	$jobstate = function ($label) use ($jobs, $db, $now) {
		$job = $jobs[$label] ?? null;
		if (!$job || empty($job->status)) {
			return array('off', null, null);
		}
		$last = $job->datelastrun ? $db->jdate($job->datelastrun) : null;
		$next = $job->datenextrun ? $db->jdate($job->datenextrun) : null;
		$period = max(1, (int) $job->frequency) * max(1, (int) $job->unitfrequency);
		if (!$last || $last < $now - 2 * $period) {
			return array('late', $last, $next);
		}
		if ((string) $job->lastresult !== '' && (string) $job->lastresult !== '0') {
			return array('error', $last, $next);
		}
		return array('ok', $last, $next);
	};
	$admin = !empty($user->admin);
	$rows = array();

	// Catalogue of the plant protection products (Ministry of Health)
	$obj = dolifarmAreaRow($db, "SELECT COUNT(*) as nb FROM ".$p."dolifarm_ppp_catalog");
	list($state, $last, $next) = $jobstate('DolifarmPppSyncJob');
	$sync = getDolGlobalInt('DOLIFARM_PPP_SYNC_DATE');
	$row = array('source' => $langs->trans('AreaExtPpp'), 'module' => 'doliFarm', 'data' => trim(getDolGlobalString('DOLIFARM_PPP_FILE').($sync ? ' - '.dol_print_date($sync, 'day') : '')), 'lastrun' => $last, 'nextrun' => $next, 'state' => $state, 'message' => '', 'url' => ($admin ? dol_buildpath('/dolifarm/admin/ppp.php', 1) : ''));
	if (!$obj || (int) $obj->nb == 0) {
		$row['state'] = 'off';
		$row['message'] = $langs->trans('AreaExtNeverLoaded');
	} else {
		$row['message'] = $langs->trans('AreaExtProducts', (int) $obj->nb);
	}
	$rows[] = $row;

	// EPPO codes
	$obj = dolifarmAreaRow($db, "SELECT COUNT(*) as nb FROM ".$p."dolifarm_eppo_code");
	list($state, $last, $next) = $jobstate('DolifarmEppoSyncJob');
	$row = array('source' => $langs->trans('AreaExtEppo'), 'module' => 'doliFarm', 'data' => trim(getDolGlobalString('DOLIFARM_EPPO_DATA_DATE').(getDolGlobalString('DOLIFARM_EPPO_SYNC_DATE') ? ' / '.getDolGlobalString('DOLIFARM_EPPO_SYNC_DATE') : '')), 'lastrun' => $last, 'nextrun' => $next, 'state' => $state, 'message' => '', 'url' => ($admin ? dol_buildpath('/dolifarm/admin/eppo.php', 1) : ''));
	if (!$obj || (int) $obj->nb == 0) {
		$row['state'] = 'off';
		$row['message'] = $langs->trans('AreaExtNeverLoaded');
	} elseif (!getDolGlobalString('DOLIFARM_EPPO_API_KEY')) {
		$row['state'] = 'off';
		$row['message'] = $langs->trans('AreaExtNoApiKey');
	} else {
		$row['message'] = $langs->trans('AreaExtCodes', (int) $obj->nb);
	}
	$rows[] = $row;

	// Weather of doliField: observed data and forecasts of the stations
	if (isModEnabled('dolifield')) {
		$obj = dolifarmAreaRow($db, "SELECT COUNT(s.rowid) as nb, MAX(s.date_last_load) as lastload, MAX(s.date_last_forecast) as lastforecast, SUM(CASE WHEN s.last_error IS NOT NULL AND s.last_error <> '' THEN 1 ELSE 0 END) as errors, SUM(CASE WHEN s.forecast_error IS NOT NULL AND s.forecast_error <> '' THEN 1 ELSE 0 END) as ferrors FROM ".$p."dolifield_weather_station as s WHERE s.entity IN (".getEntity('dolifarmplot').") AND s.status = 1");
		$nb = $obj ? (int) $obj->nb : 0;
		$url = dol_buildpath('/dolifield/dolifield_weather.php', 1);
		foreach (array('DolifieldWeatherJob' => array('AreaExtWeather', 'lastload', 'errors'), 'DolifieldForecastJob' => array('AreaExtForecast', 'lastforecast', 'ferrors')) as $label => $def) {
			list($state, $last, $next) = $jobstate($label);
			$data = ($obj && $obj->{$def[1]}) ? dol_print_date($db->jdate($obj->{$def[1]}), 'dayhour') : '';
			$row = array('source' => $langs->trans($def[0]), 'module' => 'doliField', 'data' => $data, 'lastrun' => $last, 'nextrun' => $next, 'state' => $state, 'message' => $langs->trans('AreaExtStations', $nb), 'url' => $url);
			if ($nb == 0) {
				$row['state'] = 'off';
				$row['message'] = $langs->trans('AreaExtNoStation');
			} elseif ($obj && (int) $obj->{$def[2]} > 0) {
				$row['state'] = 'error';
				$row['message'] = $langs->trans('AreaExtStationErrors', (int) $obj->{$def[2]}, $nb);
			}
			$rows[] = $row;
		}
	}
	// A job that never ran: the scheduler of the host is probably stopped (the data may have been loaded by hand)
	foreach ($rows as $key => $row) {
		if ($row['state'] == 'late' && empty($row['lastrun'])) {
			$rows[$key]['message'] = trim($row['message'].' - '.$langs->trans('AreaExtJobNeverRun'), ' -');
		}
	}
	return $rows;
}

/**
 * Badge of a state
 *
 * @param	string		$state	done, warn, todo, na (areas); ok, late, error, off (external databases)
 * @param	Translate	$langs	Languages
 * @return	string				HTML
 */
function dolifarmAreaBadge($state, $langs)
{
	$badges = array(
		'done' => array('AreaStateDone', 'status4'), 'warn' => array('AreaStateWarn', 'status1'), 'todo' => array('AreaStateTodo', 'status8'), 'na' => array('AreaStateNa', 'status9'),
		'ok' => array('AreaExtOk', 'status4'), 'late' => array('AreaExtLate', 'status1'), 'error' => array('AreaExtError', 'status8'), 'off' => array('AreaExtOff', 'status9'),
	);
	$badge = $badges[$state] ?? $badges['na'];
	return dolGetBadge($langs->trans($badge[0]), '', $badge[1]);
}

/**
 * Start of a box with the look of the widgets of Dolibarr (not movable)
 *
 * @param	string	$title		Title (HTML allowed)
 * @param	string	$picto		Picto of the title
 * @param	int		$colspan	Number of columns
 * @param	string	$url		Link of the title, '' for none
 * @return	string
 */
function dolifarmAreaBoxStart($title, $picto, $colspan, $url = '')
{
	$out = '<div class="box divboxtable dolifarm-area"><table class="noborder boxtable centpercent">';
	$out .= '<tr class="liste_titre box_titre"><th colspan="'.((int) $colspan).'">';
	$out .= '<div class="tdoverflowmax400 maxwidth250onsmartphone float">'.img_picto('', $picto, 'class="pictofixedwidth"').$title.'</div>';
	if ($url) {
		$out .= '<div class="floatright"><a href="'.dol_escape_htmltag($url).'">'.img_picto('', 'fa-external-link-alt', 'class="opacitymedium"').'</a></div>';
	}
	$out .= '</th></tr>';
	return $out;
}

/**
 * End of a box
 *
 * @return	string
 */
function dolifarmAreaBoxEnd()
{
	return '</table></div>';
}

/**
 * Line of a list: text with its link
 *
 * @param	array{0:string,1:string,2:string}	$item	Text, url, label of the link
 * @return	string
 */
function dolifarmAreaItemHtml($item)
{
	return dol_escape_htmltag($item[0]).($item[1] !== '' ? ' <a href="'.dol_escape_htmltag($item[1]).'">'.dol_escape_htmltag($item[2]).'</a>' : '');
}

/**
 * Box "Situation": the three areas side by side
 *
 * @param	array<string,array<string,mixed>>	$areas	Result of dolifarmAreaSituation()
 * @param	string								$title	Title
 * @param	Translate							$langs	Languages
 * @return	string
 */
function dolifarmAreaSituationHtml($areas, $title, $langs)
{
	$out = dolifarmAreaBoxStart(dol_escape_htmltag($title), 'fa-tasks', count($areas));
	$out .= '<tr class="nohover">';
	$n = 0;
	foreach ($areas as $area) {
		$n++;
		$out .= '<td class="tdtop dolifarm-area-cell'.($area['state'] == 'na' ? ' opacitymedium' : '').'">';
		$name = img_picto('', $area['icon'], 'class="pictofixedwidth"').$n.'. '.dol_escape_htmltag($area['title']);
		$out .= '<div class="dolifarm-area-title">'.($area['url'] ? '<a href="'.dol_escape_htmltag($area['url']).'">'.$name.'</a>' : $name).' '.dolifarmAreaBadge($area['state'], $langs).'</div>';
		foreach ($area['facts'] as $fact) {
			$out .= '<div class="opacitymedium">'.dol_escape_htmltag($fact).'</div>';
		}
		$out .= '</td>';
	}
	$out .= '</tr>'.dolifarmAreaBoxEnd();
	return $out;
}

/**
 * Box "Setup to complete", only when something is left
 *
 * @param	array<string,array<string,mixed>>	$areas	Result of dolifarmAreaSituation()
 * @param	Translate							$langs	Languages
 * @return	string								HTML, '' when nothing is left
 */
function dolifarmAreaConfigHtml($areas, $langs)
{
	$rows = '';
	$nb = 0;
	foreach ($areas as $area) {
		foreach ($area['config'] as $item) {
			$nb++;
			$rows .= '<tr class="oddeven"><td class="tdoverflowmax150 opacitymedium">'.img_picto('', $area['icon'], 'class="pictofixedwidth"').dol_escape_htmltag($area['title']).'</td><td>'.dolifarmAreaItemHtml($item).'</td></tr>';
		}
	}
	if ($nb == 0) {
		return '';
	}
	return dolifarmAreaBoxStart($langs->trans('AreaBoxConfig').' ('.$nb.')', 'fa-tools', 2).$rows.dolifarmAreaBoxEnd();
}

/**
 * Box "Alerts"
 *
 * @param	array<int,array{0:string,1:string,2:string,3:string}>	$alerts	Result of dolifarmAreaAlerts()
 * @param	Translate												$langs	Languages
 * @return	string
 */
function dolifarmAreaAlertsHtml($alerts, $langs)
{
	$nberror = 0;
	foreach ($alerts as $alert) {
		$nberror += ($alert[0] == 'error' ? 1 : 0);
	}
	$title = $langs->trans('AreaBoxAlerts').' ('.count($alerts).')'.($nberror ? ' '.dolGetBadge($langs->trans('AreaAlertNbErrors', $nberror), '', 'danger') : '');
	$out = dolifarmAreaBoxStart($title, 'fa-exclamation-triangle', 2);
	if (empty($alerts)) {
		$out .= '<tr class="oddeven"><td colspan="2" class="center"><span class="opacitymedium">'.$langs->trans('AreaAlertNone').'</span></td></tr>';
	}
	foreach ($alerts as $alert) {
		$icon = ($alert[0] == 'error' ? img_picto('', 'fa-times-circle', 'class="pictofixedwidth" style="color: #c62828"') : img_picto('', 'fa-exclamation-circle', 'class="pictofixedwidth" style="color: #ef6c00"'));
		$out .= '<tr class="oddeven"><td class="width20">'.$icon.'</td><td>'.dolifarmAreaItemHtml(array($alert[1], $alert[2], $alert[3])).'</td></tr>';
	}
	return $out.dolifarmAreaBoxEnd();
}

/**
 * Box "Activity of the campaign"
 *
 * @param	array{counts:array<int,array{0:string,1:string,2:string}>,last:array<int,array<string,mixed>>}	$activity	Result of dolifarmAreaActivity()
 * @param	array<int,array{0:string,1:string,2:string}>	$work		Things to do of the field notebook (dolifarmAreaSituation())
 * @param	Translate										$langs		Languages
 * @return	string											HTML, '' without doliTrace
 */
function dolifarmAreaActivityHtml($activity, $work, $langs)
{
	if (empty($activity['counts']) && empty($work)) {
		return '';
	}
	$out = dolifarmAreaBoxStart($langs->trans('AreaBoxActivity'), dolifarm_get_fa_icon('dolitrace'), 3);
	foreach ($work as $item) {
		$out .= '<tr class="oddeven"><td colspan="3">'.img_picto('', 'fa-hand-point-right', 'class="pictofixedwidth" style="color: #ef6c00"').dolifarmAreaItemHtml($item).'</td></tr>';
	}
	foreach ($activity['counts'] as $count) {
		$out .= '<tr class="oddeven"><td colspan="2"><a href="'.dol_escape_htmltag($count[2]).'">'.dol_escape_htmltag($count[0]).'</a></td><td class="right nowraponall">'.dol_escape_htmltag($count[1]).'</td></tr>';
	}
	if (!empty($activity['last'])) {
		$out .= '<tr class="liste_titre"><td colspan="3">'.$langs->trans('AreaActLast').'</td></tr>';
		foreach ($activity['last'] as $row) {
			$out .= '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($row['date'], 'day').'</td>';
			$out .= '<td class="tdoverflowmax150"><a href="'.dol_escape_htmltag($row['url']).'">'.img_picto('', $row['icon'], 'class="pictofixedwidth"').dol_escape_htmltag((string) $row['ref']).'</a></td>';
			$out .= '<td class="tdoverflowmax200 opacitymedium" title="'.dol_escape_htmltag($row['label']).'">'.dol_escape_htmltag($row['type'].($row['label'] !== '' ? ' - '.$row['label'] : '')).'</td></tr>';
		}
	}
	return $out.dolifarmAreaBoxEnd();
}

/**
 * Box "Farms" (scope: all the farms)
 *
 * @param	array<int,array<string,mixed>>	$rows		Result of dolifarmAreaFarmRows()
 * @param	string							$baseurl	Url of the page, to restrict the scope to a farm
 * @param	Translate						$langs		Languages
 * @return	string
 */
function dolifarmAreaFarmsHtml($rows, $baseurl, $langs)
{
	$out = dolifarmAreaBoxStart($langs->trans('AreaBoxFarms').' ('.count($rows).')', 'company', 6);
	$out .= '<tr class="liste_titre"><td>'.$langs->trans('ThirdParty').'</td><td>'.$langs->trans('DoliFarmFarmDossierMenu').'</td><td>'.$langs->trans('AreaNotebook').'</td><td>'.$langs->trans('AreaField').'</td>';
	$out .= '<td class="right">'.$langs->trans('AreaBoxAlerts').'</td><td class="right">'.$langs->trans('AreaExpiries').'</td></tr>';
	foreach ($rows as $row) {
		$out .= '<tr class="oddeven"><td class="tdoverflowmax200"><a href="'.dol_escape_htmltag($baseurl.(str_contains($baseurl, '?') ? '&' : '?').'socid='.((int) $row['socid'])).'" title="'.dol_escape_htmltag($langs->trans('AreaSeeFarm')).'">'.img_picto('', 'company', 'class="pictofixedwidth"').dol_escape_htmltag($row['name']).'</a></td>';
		foreach ($row['situation'] as $area) {
			$out .= '<td>'.dolifarmAreaBadge($area['state'], $langs).'</td>';
		}
		$out .= '<td class="right">'.($row['alerts'] ? dolGetBadge((string) $row['alerts'], '', 'warning') : '').'</td>';
		$out .= '<td class="right">'.($row['expiries'] ? dolGetBadge((string) $row['expiries'], '', 'info') : '').'</td></tr>';
	}
	return $out.dolifarmAreaBoxEnd();
}

/**
 * Box "External databases"
 *
 * @param	array<int,array<string,mixed>>	$rows	Result of dolifarmAreaExternal()
 * @param	User							$user	User
 * @param	Translate						$langs	Languages
 * @return	string
 */
function dolifarmAreaExternalHtml($rows, $user, $langs)
{
	$url = (!empty($user->admin) ? DOL_URL_ROOT.'/cron/list.php?search_module_name=dolifarm' : '');
	$out = dolifarmAreaBoxStart($langs->trans('AreaBoxExternal'), 'fa-sync-alt', 6, $url);
	$out .= '<tr class="liste_titre"><td>'.$langs->trans('AreaExtSource').'</td><td>'.$langs->trans('AreaExtData').'</td><td class="center">'.$langs->trans('AreaExtLastRun').'</td>';
	$out .= '<td class="center">'.$langs->trans('AreaExtNextRun').'</td><td></td><td class="right">'.$langs->trans('Status').'</td></tr>';
	foreach ($rows as $row) {
		$source = dol_escape_htmltag($row['source']).' <span class="opacitymedium small">'.dol_escape_htmltag($row['module']).'</span>';
		$out .= '<tr class="oddeven"><td class="tdoverflowmax250">'.($row['url'] ? '<a href="'.dol_escape_htmltag($row['url']).'">'.$source.'</a>' : $source).'</td>';
		$out .= '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($row['data']).'">'.dol_escape_htmltag($row['data']).'</td>';
		$out .= '<td class="center nowraponall">'.($row['lastrun'] ? dol_print_date($row['lastrun'], 'dayhour') : '<span class="opacitymedium">'.$langs->trans('AreaExtNever').'</span>').'</td>';
		$out .= '<td class="center nowraponall">'.($row['nextrun'] ? dol_print_date($row['nextrun'], 'dayhour') : '').'</td>';
		$out .= '<td class="tdoverflowmax200 opacitymedium small">'.dol_escape_htmltag($row['message']).'</td>';
		$out .= '<td class="right nowraponall">'.dolifarmAreaBadge($row['state'], $langs).'</td></tr>';
	}
	return $out.dolifarmAreaBoxEnd();
}

/**
 * Page to open after the login (setup DOLIFARM_LOGIN_PAGE)
 *
 * 'start' (default): Help me; 'area': the area of doliFarm; 'auto': Help me until the required steps of the setup
 * are done, then the area.
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User
 * @return	string			Url relative to the web root of Dolibarr
 */
function dolifarmLoginPageUrl($db, $user)
{
	$mode = getDolGlobalString('DOLIFARM_LOGIN_PAGE', 'start');
	$area = dol_buildpath('/dolifarm/dolifarmindex.php', 1);
	if ($mode == 'area') {
		return $area;
	}
	if ($mode == 'auto') {
		foreach (dolifarmStartSteps($db, $user) as $step) {
			if (!$step['optional'] && !$step['done']) {
				return dol_buildpath('/dolifarm/dolifarm_start.php', 1);
			}
		}
		return $area;
	}
	return dol_buildpath('/dolifarm/dolifarm_start.php', 1);
}
