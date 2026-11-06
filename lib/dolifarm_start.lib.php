<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    lib/dolifarm_start.lib.php
 * \ingroup dolifarm
 * \brief   Getting started: steps of the first setup with their status, and the guided tours of the pages
 */

/**
 * Number of rows of a table, in the entities of an element
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$table		Table without prefix
 * @param	string	$element	Element for getEntity(), '' when the table has no entity
 * @param	string	$where		More conditions (SQL, trusted)
 * @return	int
 */
function dolifarmStartCount($db, $table, $element = '', $where = '', $socfield = '')
{
	$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix().$table." as t WHERE 1 = 1";
	if ($element !== '') {
		$sql .= " AND t.entity IN (".getEntity($element).")";
	}
	if ($where !== '') {
		$sql .= " AND ".$where;
	}
	if ($socfield !== '') {
		// Only the objects of the managed farms
		dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
		$sql .= dolifarmSqlManagedFarmFilter($socfield);
	}
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_DEBUG);
		return 0;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (int) $obj->nb : 0;
}

/**
 * Steps of the first setup of a farm, in order, with their status. Steps the user cannot see are left out.
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	User
 * @return	array<int,array{key:string,label:string,help:string,count:int,done:bool,optional:bool,url:string,tour:string,group:string}>
 */
function dolifarmStartSteps($db, $user)
{
	global $mysoc;

	$steps = array();
	$add = function ($group, $key, $count, $done, $optional, $url, $tour = '') use (&$steps) {
		$steps[] = array(
			'group' => $group, 'key' => $key, 'label' => 'StartStep'.$key, 'help' => 'StartStep'.$key.'Help',
			'count' => (int) $count, 'done' => (bool) $done, 'optional' => (bool) $optional, 'url' => $url, 'tour' => $tour,
		);
	};
	$admin = !empty($user->admin);

	// Setup
	if ($admin) {
		$add('Setup', 'Company', 0, !empty($mysoc->name) && !empty($mysoc->country_code), false, DOL_URL_ROOT.'/admin/company.php');
		dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
		$add('Setup', 'Mode', 0, count(dolifarmManagedFarmIds()) > 0, false, dol_buildpath('/dolifarm/admin/setup.php', 1));
		$add('Setup', 'Stock', 0, isModEnabled('stock') && isModEnabled('productbatch'), false, DOL_URL_ROOT.'/admin/modules.php?search_keyword=stock');
		$add('Setup', 'Eppo', dolifarmStartCount($db, 'dolifarm_eppo_code'), dolifarmStartCount($db, 'dolifarm_eppo_code') > 0, true, dol_buildpath('/dolifarm/admin/eppo.php', 1));
		$add('Setup', 'Catalog', dolifarmStartCount($db, 'dolifarm_ppp_catalog'), dolifarmStartCount($db, 'dolifarm_ppp_catalog') > 0, true, dol_buildpath('/dolifarm/admin/ppp.php', 1));
	}

	// Farm
	if ($user->hasRight('societe', 'lire')) {
		// Managed farms: the farm of the setup, or the affiliated farms
		dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
		$nb = count(dolifarmManagedFarmIds());
		$add('Farm', 'Farm', $nb, $nb > 0, false, DOL_URL_ROOT.'/societe/card.php?action=create');
	}
	if ($user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read')) {
		$nb = dolifarmStartCount($db, 'dolifarm_farmdossier', 'dolifarmfarmdossier', '', 't.fk_soc');
		$add('Farm', 'Dossier', $nb, $nb > 0, false, dol_buildpath('/dolifarm/dolifarmfarmdossier_card.php', 1).'?action=create', 'dossier_create');
		if (isModEnabled('stock')) {
			$nb = dolifarmStartCount($db, 'dolifarm_farmdossier', 'dolifarmfarmdossier', 't.fk_warehouse_inputs > 0 AND t.fk_warehouse_products > 0', 't.fk_soc');
			$add('Farm', 'Warehouses', $nb, $nb > 0, false, dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1), 'dossier_create');
		}
	}
	if ($user->hasRight('dolifarm', 'dolifarmplot', 'read')) {
		$nb = dolifarmStartCount($db, 'dolifarm_plot', 'dolifarmplot', '', 't.fk_soc');
		$add('Farm', 'Plots', $nb, $nb > 0, false, dol_buildpath('/dolifarm/dolifarmplot_card.php', 1).'?action=create', 'plot_create');
		$nb = dolifarmStartCount($db, 'dolifarm_plot', 'dolifarmplot', "(t.fk_status_bio IS NOT NULL AND t.fk_status_bio <> '' AND t.fk_status_bio <> 'CONV')", 't.fk_soc');
		$add('Farm', 'Organic', $nb, $nb > 0, true, dol_buildpath('/dolifarm/dolifarmplot_list.php', 1));
	}
	if ($user->hasRight('dolifarm', 'dolifarmmachine', 'read')) {
		$nb = dolifarmStartCount($db, 'dolifarm_machine', 'dolifarmmachine', '', 't.fk_soc');
		$add('Farm', 'Machines', $nb, $nb > 0, true, dol_buildpath('/dolifarm/dolifarmmachine_card.php', 1).'?action=create', 'machine_create');
	}
	if ($user->hasRight('dolifarm', 'dolifarmoperator', 'read')) {
		$nb = dolifarmStartCount($db, 'dolifarm_operator', 'dolifarmoperator', '', 't.fk_soc');
		$add('Farm', 'Operators', $nb, $nb > 0, false, dol_buildpath('/dolifarm/dolifarmoperator_card.php', 1).'?action=create', 'operator_create');
	}

	// Technical inputs
	if ($user->hasRight('dolifarm', 'dolifarmagrodrug', 'read')) {
		$nb = dolifarmStartCount($db, 'dolifarm_agrodrug', 'dolifarmagrodrug');
		$add('Inputs', 'Agrodrugs', $nb, $nb > 0, false, dol_buildpath('/dolifarm/dolifarmagrodrug_card.php', 1).'?action=create', 'agrodrug_create');
	}
	if ($user->hasRight('dolifarm', 'dolifarmfertilizer', 'read')) {
		$nb = dolifarmStartCount($db, 'dolifarm_fertilizer', 'dolifarmfertilizer');
		$add('Inputs', 'Fertilizers', $nb, $nb > 0, true, dol_buildpath('/dolifarm/dolifarmfertilizer_card.php', 1).'?action=create', 'fert_create');
	}

	// Field notebook (doliTrace)
	if (isModEnabled('dolitrace')) {
		if ($user->hasRight('dolitrace', 'dolitracecropplan', 'read')) {
			$nb = dolifarmStartCount($db, 'dolitrace_cropplan', 'dolitracecropplan', 't.status IN (1, 2)', 't.fk_soc');
			$add('Notebook', 'CropPlans', $nb, $nb > 0, false, dol_buildpath('/dolitrace/dolitracecropplan_card.php', 1).'?action=create', 'cropplan_create');
		}
		if ($user->hasRight('dolitrace', 'dolitracetreatment', 'read')) {
			$nb = dolifarmStartCount($db, 'dolitrace_treatment', 'dolitracetreatment', 't.status = 1', 't.fk_soc');
			$add('Notebook', 'Treatments', $nb, $nb > 0, false, dol_buildpath('/dolitrace/dolitracetreatment_card.php', 1).'?action=create', 'treatment_create');
		}
		if ($user->hasRight('dolitrace', 'dolitraceharvest', 'read')) {
			$nb = dolifarmStartCount($db, 'dolitrace_harvest', '', 't.status = 1 AND t.fk_cropplan IN (SELECT cp.rowid FROM '.$db->prefix().'dolitrace_cropplan as cp WHERE cp.entity IN ('.getEntity('dolitracecropplan').')'.dolifarmSqlManagedFarmFilter('cp.fk_soc').')');
			$add('Notebook', 'Harvests', $nb, $nb > 0, true, dol_buildpath('/dolitrace/dolitraceharvest_card.php', 1).'?action=create', 'harvest_create');
			// The registers have something to show once there is a validated harvest
			$add('Notebook', 'BioRegisters', 0, $nb > 0, true, dol_buildpath('/dolitrace/dolitrace_bioregister.php', 1), 'bioregister');
		}
	}
	return $steps;
}

/**
 * Guided tours of the pages: where they apply, their steps and the page that follows.
 * A step is a CSS selector and a translation key; without its own title the step takes the label of the row of the
 * form it points to. Steps whose element is not on the page are skipped.
 *
 * @param	Translate	$langs		Language of the texts
 * @return	array<string,array{path:string,action:string,title:string,steps:array<int,array{el:string,title:string,text:string}>,next:string}>
 */
function dolifarmTourDefinitions(Translate $langs)
{
	$langs->loadLangs(array('dolifarm@dolifarm'));
	$tours = array(
		'start' => array('/dolifarm/dolifarm_start.php', '', array(
			array('#dolifarm-start-progress', 'TourStart1', 'TourStart1Title'),
			array('#dolifarm-start-table', 'TourStart2', 'TourStart2Title'),
			array('#dolifarm-start-table .dolifarm-start-todo', 'TourStart3', 'TourStart3Title'),
		), 'dossier_create'),
		'dossier_create' => array('/dolifarm/dolifarmfarmdossier_card.php', 'create', array(
			array('tr.field_fk_soc', 'TourDossier1', ''),
			array('tr.field_date_signature', 'TourDossier2', ''),
			array('tr.field_fk_soc_caa', 'TourDossier3', ''),
			array('tr.field_fk_soc_bio_body', 'TourDossier4', ''),
			array('tr.field_fk_warehouse_inputs', 'TourDossier5', ''),
			array('tr.field_fk_warehouse_products', 'TourDossier6', ''),
		), 'plot_create'),
		'plot_create' => array('/dolifarm/dolifarmplot_card.php', 'create', array(
			array('tr.field_fk_dossier', 'TourPlot1', ''),
			array('tr.field_cadastral_comune', 'TourPlot2', ''),
			array('tr.field_iacs_parcel', 'TourPlot3', ''),
			array('tr.field_size_sau', 'TourPlot4', ''),
			array('tr.field_fk_status_bio', 'TourPlot5', ''),
			array('tr.field_zone_zvn', 'TourPlot6', ''),
		), 'machine_create'),
		'machine_create' => array('/dolifarm/dolifarmmachine_card.php', 'create', array(
			array('tr.field_fk_type', 'TourMachine1', ''),
			array('tr.field_date_revision_next', 'TourMachine2', ''),
			array('tr.field_sprayer_cert_ref', 'TourMachine3', ''),
			array('tr.field_date_sprayer_check_end', 'TourMachine4', ''),
		), 'operator_create'),
		'operator_create' => array('/dolifarm/dolifarmoperator_card.php', 'create', array(
			array('tr.field_fk_user', 'TourOperator1', ''),
			array('tr.field_license_number', 'TourOperator2', ''),
			array('tr.field_license_date', 'TourOperator3', ''),
		), 'agrodrug_create'),
		'agrodrug_create' => array('/dolifarm/dolifarmagrodrug_card.php', 'create', array(
			array('tr.field_reg_number', 'TourAgrodrug1', ''),
			array('tr.field_fk_product', 'TourAgrodrug2', ''),
			array('tr.field_safety_days_default', 'TourAgrodrug3', ''),
			array('tr.field_moa_codes', 'TourAgrodrug4', ''),
			array('tr.field_organic_allowed', 'TourAgrodrug5', ''),
		), 'fert_create'),
		'fert_create' => array('/dolifarm/dolifarmfertilizer_card.php', 'create', array(
			array('tr.field_product_kind', 'TourFert1', ''),
			array('tr.field_fk_product', 'TourFert2', ''),
			array('tr.field_organic', 'TourFert3', ''),
			array('tr.field_n_content', 'TourFert4', ''),
			array('tr.field_density', 'TourFert5', ''),
		), isModEnabled('dolitrace') ? 'cropplan_create' : 'start'),
	);
	if (isModEnabled('dolitrace')) {
		$langs->loadLangs(array('dolitrace@dolitrace'));
		$tours['cropplan_create'] = array('/dolitrace/dolitracecropplan_card.php', 'create', array(
			array('tr.field_fk_plot', 'TourCropplan1', ''),
			array('tr.field_fk_crop', 'TourCropplan2', ''),
			array('tr.field_date_start', 'TourCropplan3', ''),
			array('tr.field_estimated_yield', 'TourCropplan4', ''),
		), 'treatment_create');
		$tours['treatment_create'] = array('/dolitrace/dolitracetreatment_card.php', 'create', array(
			array('tr.field_kind', 'TourTreatment1', ''),
			array('tr.field_fk_soc', 'TourTreatment2', ''),
			array('tr.field_date_start', 'TourTreatment3', ''),
			array('tr.field_fk_operator', 'TourTreatment4', ''),
			array('tr.field_fk_machine', 'TourTreatment5', ''),
			array('tr.field_justification_type', 'TourTreatment6', ''),
		), 'harvest_create');
		$tours['harvest_create'] = array('/dolitrace/dolitraceharvest_card.php', 'create', array(
			array('tr.field_fk_cropplan', 'TourHarvest1', ''),
			array('tr.field_fk_product_out', 'TourHarvest2', ''),
			array('tr.field_qty_harvested', 'TourHarvest3', ''),
			array('tr.field_batch_number', 'TourHarvest4', ''),
		), 'bioregister');
		$tours['bioregister'] = array('/dolitrace/dolitrace_bioregister.php', '', array(
			array('div.tabs', 'TourBio1', 'TourBio1Title'),
			array('table.tableforfield', 'TourBio2', 'TourBio2Title'),
			array('div.tabsAction', 'TourBio3', 'TourBio3Title'),
		), 'start');
	}
	$result = array();
	foreach ($tours as $key => $tour) {
		$steps = array();
		foreach ($tour[2] as $step) {
			$steps[] = array('el' => $step[0], 'title' => $step[2] !== '' ? $langs->transnoentities($step[2]) : '', 'text' => $langs->transnoentities($step[1]));
		}
		$next = isset($tours[$tour[3]]) ? $tour[3] : '';
		$result[$key] = array(
			'path' => dol_buildpath($tour[0], 1),
			'action' => $tour[1],
			'title' => $langs->transnoentities('TourTitle_'.$key),
			'steps' => $steps,
			'next' => $next,
		);
	}
	return $result;
}

/**
 * Tell if the page "Getting started" opens after the login of a user
 *
 * It opens for the internal users who can read the farm dossiers, unless they turned it off on the page itself
 * (user parameter DOLIFARM_START_NOT_AT_LOGIN = 1).
 *
 * @param	User	$user		User
 * @param	bool	$checkright	False when the rights of the user are not loaded yet (hook afterLogin)
 * @return	bool				True if the page opens after the login
 */
function dolifarmStartAtLogin(User $user, bool $checkright = true): bool
{
	if (!isModEnabled('dolifarm') || $user->socid > 0 || ($checkright && !$user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read'))) {
		return false;
	}
	return !getDolUserInt('DOLIFARM_START_NOT_AT_LOGIN', 0, $user);
}
