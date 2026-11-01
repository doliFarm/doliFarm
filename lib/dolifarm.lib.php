<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
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
 * \file    dolifarm/lib/dolifarm.lib.php
 * \ingroup dolifarm
 * \brief   Library files with common functions for Dolifarm
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function dolifarmAdminPrepareHead()
{
	global $db, $langs, $conf;

	$langs->load("dolifarm@dolifarm");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/setup.php", 1));
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	// Extrafields of each object (page admin/<element>_extrafields.php, table of the object as element type)
	$extrafields = new ExtraFields($db);
	foreach (array(
		'dolifarmfarmdossier' => array('DoliFarmFarmDossier', 'dolifarm_farmdossier'),
		'dolifarmplot' => array('DoliFarmPlot', 'dolifarm_plot'),
		'dolifarmlivestock' => array('DoliFarmLivestock', 'dolifarm_livestock'),
		'dolifarmbuilding' => array('DoliFarmBuilding', 'dolifarm_building'),
		'dolifarmmachine' => array('DoliFarmMachine', 'dolifarm_machine'),
		'dolifarmoperator' => array('DoliFarmOperator', 'dolifarm_operator'),
		'dolifarmagrodrug' => array('DoliFarmAgroDrug', 'dolifarm_agrodrug'),
		'dolifarmfertilizer' => array('DoliFarmFertilizer', 'dolifarm_fertilizer'),
		'dolifarmcrops' => array('DoliFarmCrops', 'dolifarm_crops'),
		'dolifarmestimatecosts' => array('DoliFarmEstimateCosts', 'dolifarm_dolifarmestimatecosts'),
	) as $element => $info) {
		$extrafields->fetch_name_optionals_label($info[1]);
		$nbExtrafields = (isset($extrafields->attributes[$info[1]]['label']) && is_countable($extrafields->attributes[$info[1]]['label'])) ? count($extrafields->attributes[$info[1]]['label']) : 0;
		$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/".$element."_extrafields.php", 1));
		$head[$h][1] = $langs->trans("ExtraFields").' '.$langs->trans($info[0]);
		if ($nbExtrafields > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbExtrafields.'</span>';
		}
		$head[$h][2] = $element.'_extrafields';
		$h++;
	}

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/eppo.php", 1));
	$head[$h][1] = $langs->trans("EppoCodes");
	$head[$h][2] = 'eppo';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/about.php", 1));
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@dolifarm:/dolifarm/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@dolifarm:/dolifarm/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolifarm@dolifarm');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolifarm@dolifarm', 'remove');

	return $head;
}

/**
 * Recupera l'ID di un tipo di terzo (dizionario llx_c_typent) dal suo codice (cached).
 * Gli ID dei tipi aggiunti da doliFarm cambiano da un'installazione all'altra.
 *
 * @param   DoliDB  $db     Database handler
 * @param   string  $code   Codice del tipo di terzo (es. 'TE_FARM')
 * @return  int             ID del tipo terzo o 0 se non trovato
 */
function dolifarmGetTypentId($db, $code)
{
    static $cache = array();

    if (isset($cache[$code])) {
        return $cache[$code];
    }

    $cache[$code] = 0;
    $sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_typent WHERE code = '".$db->escape($code)."'";
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        $cache[$code] = (int) $obj->id;
    }

    return $cache[$code];
}

/**
 * Recupera l'ID del tipo terzo "TE_FARM"
 *
 * @param   DoliDB  $db     Database handler
 * @return  int             ID del tipo terzo o 0 se non trovato
 */
function getFarmTypeId($db)
{
    return dolifarmGetTypentId($db, 'TE_FARM');
}