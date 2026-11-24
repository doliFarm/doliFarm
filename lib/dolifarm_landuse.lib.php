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
 * \file    dolifarm/lib/dolifarm_landuse.lib.php
 * \ingroup dolifarm
 * \brief   Dictionary of the AGEA land uses ("occupazione del suolo" of the dossier) and their crop.
 *          AGEA does not publish its catalogue: the dictionary fills itself with the codes read in the imported
 *          dossiers; the administrator links a code to a crop of the catalogue (column crop_ref = ref of the crop).
 */

/**
 * Land uses that are not agricultural area (wood, buildings, tares, trees, hedges): never a production plan
 *
 * @return	array<int,string>
 */
function dolifarmLandUseNonSau()
{
	return array('650', '660', '780', '785', '788');
}

/**
 * Create the dictionary if missing (called by init() of the module)
 *
 * @param	DoliDB	$db		Database handler
 * @return	int				<0 if KO, 0 if OK
 */
function dolifarmLandUseInitDictionary($db)
{
	$sql = "CREATE TABLE IF NOT EXISTS ".$db->prefix()."dolifarm_c_landuse (";
	$sql .= " rowid integer AUTO_INCREMENT PRIMARY KEY, code varchar(16) NOT NULL, label varchar(255) NOT NULL,";
	$sql .= " crop_ref varchar(128), active tinyint DEFAULT 1 NOT NULL, position integer DEFAULT 0, UNIQUE (code)) ENGINE=innodb";
	if (!$db->query($sql)) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	return 0;
}

/**
 * Crop linked to an AGEA land use; the code is added to the dictionary when missing
 *
 * When a code is added, it is linked to a crop only if one crop has exactly the same name (translated label or
 * local name): otherwise the administrator links it.
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Languages (labels of the crops)
 * @param	string		$code	AGEA code, e.g. 420
 * @param	string		$label	AGEA label, e.g. OLIVO
 * @return	int					Id of the crop, 0 if none (or code disabled), <0 if KO
 */
function dolifarmLandUseCrop($db, $langs, $code, $label)
{
	$code = trim($code);
	if ($code === '' || $code === '000') {
		return 0;
	}
	$table = $db->prefix().'dolifarm_c_landuse';
	$resql = $db->query("SELECT rowid, crop_ref, active FROM ".$table." WHERE code = '".$db->escape($code)."'");
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return -1;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (!$obj) {
		$cropref = dolifarmLandUseGuessCrop($db, $langs, $label);
		$sql = "INSERT INTO ".$table." (code, label, crop_ref, active, position) VALUES ('".$db->escape($code)."', '".$db->escape(dol_trunc($label !== '' ? $label : $code, 255, 'right', 'UTF-8', 1))."', ";
		$sql .= ($cropref !== '' ? "'".$db->escape($cropref)."'" : "NULL").", 1, ".((int) $code).")";
		if (!$db->query($sql)) {
			dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
			return -1;
		}
		$obj = (object) array('crop_ref' => $cropref, 'active' => 1);
	}
	if (empty($obj->active) || (string) $obj->crop_ref === '') {
		return 0;
	}
	$resql = $db->query("SELECT rowid FROM ".$db->prefix()."dolifarm_crops WHERE ref = '".$db->escape($obj->crop_ref)."' AND status = 1");
	$crop = $resql ? $db->fetch_object($resql) : null;
	return $crop ? (int) $crop->rowid : 0;
}

/**
 * Ref of the only crop whose name is the AGEA label
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Languages
 * @param	string		$label	AGEA label
 * @return	string				Ref, '' if none or more than one
 */
function dolifarmLandUseGuessCrop($db, $langs, $label)
{
	$wanted = dol_strtoupper(trim($label));
	if ($wanted === '') {
		return '';
	}
	$langs->load('dolifarm@dolifarm');
	$found = array();
	$resql = $db->query("SELECT ref, label, local_name FROM ".$db->prefix()."dolifarm_crops WHERE status = 1");
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$names = array(dol_strtoupper($langs->transnoentitiesnoconv((string) $obj->label)), dol_strtoupper((string) $obj->local_name));
			if (in_array($wanted, $names, true)) {
				$found[] = (string) $obj->ref;
			}
		}
		$db->free($resql);
	}
	return count($found) == 1 ? $found[0] : '';
}
