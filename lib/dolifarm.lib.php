<?php
/* Copyright (C) 2026		SuperAdmin
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
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("dolifarm@dolifarm");

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/setup.php", 1));
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/myobject_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = (isset($extrafields->attributes['myobject']['label']) && is_countable($extrafields->attributes['myobject']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/admin/myobjectline_extrafields.php", 1));
	$head[$h][1] = $langs->trans("ExtraFieldsLines");
	$nbExtrafields = (isset($extrafields->attributes['myobjectline']['label']) && is_countable($extrafields->attributes['myobjectline']['label'])) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafieldsline';
	$h++;
	*/

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
 * Recupera l'ID del tipo terzo "TE_FARM" in modo performante (cached)
 *
 * @param   DoliDB  $db     Database handler
 * @return  int             ID del tipo terzo o 0 se non trovato
 */
function getFarmTypeId($db)
{
    static $farm_type_id_cache = -1;

    // Se abbiamo già cercato l'ID in questa esecuzione, lo restituiamo subito
    if ($farm_type_id_cache !== -1) {
        return $farm_type_id_cache;
    }

    $sql = "SELECT id FROM ".MAIN_DB_PREFIX."c_typent WHERE code = 'TE_FARM'";
    $resql = $db->query($sql);
    
    if ($resql && $db->num_rows($resql)) {
        $obj = $db->fetch_object($resql);
        $farm_type_id_cache = (int)$obj->id;
    } else {
        // Fallback robusto: se non esiste, impostiamo 0
        $farm_type_id_cache = 0;
    }

    return $farm_type_id_cache;
}