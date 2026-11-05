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
 * \file    lib/dolifarm_plothistory.lib.php
 * \ingroup dolifarm
 * \brief   History of the production plans of a plot (doliTrace): crops, inputs and yields season by season
 *
 * One line per production plan of the plot, with the values needed by the later analyses (rotations, nitrogen and
 * copper balance, emissions): crop and botanical family, period, organic status at the start, validated operations,
 * treatments and fertilizations, nitrogen and copper applied (kg/ha), validated harvests and yield per hectare.
 * The production plans belong to doliTrace: the functions return nothing when doliTrace is not enabled.
 */

dol_include_once('/dolifarm/lib/dolifarm_bio.lib.php');

/**
 * True when the history of the production plans is available for the user
 *
 * @param	User	$user	User
 * @return	bool
 */
function dolifarmPlotHistoryAvailable($user)
{
	return isModEnabled('dolitrace') && $user->hasRight('dolitrace', 'dolitracecropplan', 'read');
}

/**
 * Number of production plans of a plot (badge of the tab)
 *
 * @param	DoliDB	$db			Database handler
 * @param	int		$plotid		Plot
 * @return	int
 */
function dolifarmPlotCountCropPlans($db, $plotid)
{
	if (!isModEnabled('dolitrace')) {
		return 0;
	}
	$nb = 0;
	$sql = "SELECT COUNT(rowid) as nb FROM ".$db->prefix()."dolitrace_cropplan";
	$sql .= " WHERE fk_plot = ".((int) $plotid)." AND entity IN (".getEntity('dolitracecropplan').")";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$nb = ($obj ? (int) $obj->nb : 0);
		$db->free($resql);
	}
	return $nb;
}

/**
 * Surface of the plot used for the values per hectare: conducted surface, else UAA, else total surface
 *
 * @param	object	$plot	Plot (DoliFarmPlot or row with the size_* fields)
 * @return	float			Hectares, 0 when unknown
 */
function dolifarmPlotHistoryArea($plot)
{
	foreach (array('size_conducted', 'size_sau', 'size_total') as $field) {
		if (isset($plot->$field) && (float) $plot->$field > 0) {
			return (float) $plot->$field;
		}
	}
	return 0.0;
}

/**
 * History of the production plans of a plot, from the oldest to the newest
 *
 * @param	DoliDB		$db			Database handler
 * @param	Translate	$langs		Languages (crop labels)
 * @param	object		$plot		Plot (DoliFarmPlot)
 * @return	array<int,array<string,mixed>>	One entry per production plan
 */
function dolifarmPlotCropPlanHistory($db, $langs, $plot)
{
	$plans = array();
	if (!isModEnabled('dolitrace')) {
		return $plans;
	}
	$prefix = $db->prefix();
	$area = dolifarmPlotHistoryArea($plot);

	$sql = "SELECT cp.rowid, cp.ref, cp.label, cp.season, cp.date_start, cp.date_end, cp.status, cp.estimated_yield, cp.actual_cost,";
	$sql .= " c.label as crop_label, c.eppo_code, c.family, c.variety, c.crop_type";
	$sql .= " FROM ".$prefix."dolitrace_cropplan as cp";
	$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
	$sql .= " WHERE cp.fk_plot = ".((int) $plot->id)." AND cp.entity IN (".getEntity('dolitracecropplan').")";
	$sql .= " ORDER BY cp.season, cp.date_start, cp.rowid";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $plans;
	}
	while ($obj = $db->fetch_object($resql)) {
		$start = $db->jdate($obj->date_start);
		$plans[(int) $obj->rowid] = array(
			'id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'label' => (string) $obj->label, 'season' => (int) $obj->season,
			'date_start' => $start, 'date_end' => $db->jdate($obj->date_end), 'status' => (int) $obj->status,
			'crop' => $langs->trans((string) $obj->crop_label), 'eppo' => strtoupper((string) $obj->eppo_code),
			'family' => trim((string) $obj->family), 'variety' => (string) $obj->variety, 'crop_type' => (string) $obj->crop_type,
			'estimated_yield' => $obj->estimated_yield !== null ? (float) $obj->estimated_yield : null,
			'actual_cost' => (float) $obj->actual_cost,
			'bio' => ($start ? dolifarmBioStatusAt($db, (int) $plot->id, (int) $start, (string) $plot->fk_status_bio) : ''),
			'operations' => 0, 'protections' => 0, 'fertilizations' => 0, 'n_applied' => 0.0, 'cu_applied' => 0.0,
			'harvests' => 0, 'harvested' => array(), 'area' => $area, 'yield_ha' => array(), 'same_family' => '',
		);
	}
	$db->free($resql);
	if (!count($plans)) {
		return $plans;
	}
	$ids = implode(',', array_keys($plans));

	// Operations not canceled
	$sql = "SELECT fk_cropplan, COUNT(rowid) as nb FROM ".$prefix."dolitrace_operation";
	$sql .= " WHERE fk_cropplan IN (".$db->sanitize($ids).") AND (status IS NULL OR status <> 9) AND entity IN (".getEntity('dolitraceoperation').")";
	$sql .= " GROUP BY fk_cropplan";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$plans[(int) $obj->fk_cropplan]['operations'] = (int) $obj->nb;
		}
		$db->free($resql);
	}

	// Validated treatments and fertilizations: the production plan of the record, or of its area for the older records
	$records = array();
	$sql = "SELECT DISTINCT t.rowid, t.kind, t.fk_cropplan, g.fk_cropplan as target_cropplan";
	$sql .= " FROM ".$prefix."dolitrace_treatment as t";
	$sql .= " LEFT JOIN ".$prefix."dolitrace_treatment_target as g ON g.fk_treatment = t.rowid AND g.fk_cropplan IN (".$db->sanitize($ids).")";
	$sql .= " WHERE t.status = 1 AND t.entity IN (".getEntity('dolitracetreatment').")";
	$sql .= " AND (t.fk_cropplan IN (".$db->sanitize($ids).") OR g.rowid IS NOT NULL)";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$cp = (isset($plans[(int) $obj->fk_cropplan]) ? (int) $obj->fk_cropplan : (int) $obj->target_cropplan);
			if (!isset($records[(int) $obj->rowid]) && isset($plans[$cp])) {
				$records[(int) $obj->rowid] = $cp;
				$plans[$cp][(int) $obj->kind == 2 ? 'fertilizations' : 'protections']++;
			}
		}
		$db->free($resql);
	}
	if (count($records)) {
		$sql = "SELECT fk_treatment, SUM(n_applied) as n, SUM(cu_applied) as cu FROM ".$prefix."dolitrace_treatment_product";
		$sql .= " WHERE fk_treatment IN (".$db->sanitize(implode(',', array_keys($records))).") GROUP BY fk_treatment";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$cp = $records[(int) $obj->fk_treatment];
				$plans[$cp]['n_applied'] += (float) $obj->n;
				$plans[$cp]['cu_applied'] += (float) $obj->cu;
			}
			$db->free($resql);
		}
	}

	// Validated harvests, by unit
	$sql = "SELECT fk_cropplan, qty_unit, COUNT(rowid) as nb, SUM(qty_harvested) as qty FROM ".$prefix."dolitrace_harvest";
	$sql .= " WHERE fk_cropplan IN (".$db->sanitize($ids).") AND status = 1 AND entity IN (".getEntity('dolitraceharvest').")";
	$sql .= " GROUP BY fk_cropplan, qty_unit";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$cp = (int) $obj->fk_cropplan;
			$unit = (string) $obj->qty_unit;
			$plans[$cp]['harvests'] += (int) $obj->nb;
			$plans[$cp]['harvested'][$unit] = (float) $obj->qty;
			if ($area > 0) {
				$plans[$cp]['yield_ha'][$unit] = (float) $obj->qty / $area;
			}
		}
		$db->free($resql);
	}

	// Rotation: same botanical family as the production plan before, in an earlier season
	$previous = null;
	foreach ($plans as $cp => $plan) {
		if ($previous !== null && $plan['family'] !== '' && $previous['season'] < $plan['season'] && strcasecmp($previous['family'], $plan['family']) == 0) {
			$plans[$cp]['same_family'] = $previous['ref'];
		}
		if ($plan['status'] != 9) {
			$previous = $plan;
		}
	}

	return array_values($plans);
}

/**
 * Label of a code of the dictionary of the organic statuses (cached)
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Languages
 * @param	string		$code	Code
 * @return	string
 */
function dolifarmPlotHistoryBioLabel($db, $langs, $code)
{
	static $labels = null;
	if ($labels === null) {
		$labels = array();
		$resql = $db->query("SELECT code, label FROM ".$db->prefix()."dolifarm_c_biostatus");
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$labels[(string) $obj->code] = (string) $obj->label;
			}
			$db->free($resql);
		}
	}
	if ((string) $code === '') {
		return '';
	}
	return isset($labels[$code]) ? $langs->trans($labels[$code]) : (string) $code;
}

/**
 * CSV of the history of the production plans of a plot (separator ;, UTF-8 with BOM), for the analyses outside Dolibarr
 *
 * @param	DoliDB		$db			Database handler
 * @param	Translate	$langs		Languages
 * @param	object		$plot		Plot
 * @param	array<int,array<string,mixed>>	$plans	History (dolifarmPlotCropPlanHistory)
 * @return	string
 */
function dolifarmPlotCropPlanHistoryCsv($db, $langs, $plot, $plans)
{
	$num = function ($value) {
		return ($value === null ? '' : str_replace('.', ',', (string) round((float) $value, 4)));
	};
	$cell = function ($value) {
		$value = (string) $value;
		if (preg_match('/^[=+\-@]/', $value)) {
			$value = "'".$value;
		}
		return '"'.str_replace('"', '""', $value).'"';
	};
	$out = array();
	$out[] = array('plot_ref', 'area_ha', 'season', 'production_plan', 'status', 'date_start', 'date_end', 'crop', 'crop_eppo', 'family', 'variety',
		'organic_status', 'operations', 'plant_protection', 'fertilizations', 'n_kg_ha', 'cu_kg_ha', 'harvests', 'harvested_qty', 'unit',
		'yield_ha', 'estimated_yield', 'actual_cost', 'same_family_as');
	foreach ($plans as $plan) {
		$units = (count($plan['harvested']) ? array_keys($plan['harvested']) : array(''));
		foreach ($units as $unit) {
			$out[] = array(
				$plot->ref, $num($plan['area'] > 0 ? $plan['area'] : null), $plan['season'], $plan['ref'], $plan['status'],
				$plan['date_start'] ? dol_print_date($plan['date_start'], '%Y-%m-%d') : '', $plan['date_end'] ? dol_print_date($plan['date_end'], '%Y-%m-%d') : '',
				$plan['crop'], $plan['eppo'], $plan['family'], $plan['variety'], $plan['bio'], $plan['operations'], $plan['protections'], $plan['fertilizations'],
				$num($plan['n_applied']), $num($plan['cu_applied']), $plan['harvests'],
				isset($plan['harvested'][$unit]) ? $num($plan['harvested'][$unit]) : '', $unit,
				$num($plan['yield_ha'][$unit] ?? null), $num($plan['estimated_yield']), $num($plan['actual_cost']), $plan['same_family'],
			);
		}
	}
	$csv = "\xEF\xBB\xBF";
	foreach ($out as $row) {
		$csv .= implode(';', array_map($cell, $row))."\r\n";
	}
	return $csv;
}
