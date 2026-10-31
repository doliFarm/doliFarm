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
 * \file    lib/dolifarm_affiliated.lib.php
 * \ingroup dolifarm
 * \brief   Affiliated farms (multi-farm mode): dossier and warehouses kept in step with the flag of the third party
 *
 * In multi-farm mode (DOLIFARM_MULTIFARM) a third party with the extrafield dolifarm_affiliated gets a draft
 * dossier and the warehouses "AZ-<code>" (parent), "AZ-<code>-IN" (inputs) and "AZ-<code>-PROD" (products),
 * linked on the dossier. When the flag is removed nothing is deleted: the warehouses are closed and the
 * dossier is disabled, so that the registers stay readable.
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Code used in the refs of the warehouses of a farm: customer or supplier code of the third party, else its id
 *
 * @param	Societe	$soc	Third party
 * @return	string			Prefix of the warehouse refs (AZ-<code>)
 */
function dolifarmAffiliatedWarehousePrefix($soc)
{
	$code = (string) $soc->code_client;
	if ($code === '') {
		$code = (string) $soc->code_fournisseur;
	}
	$code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));
	if ($code === '') {
		$code = (string) ((int) $soc->id);
	}
	return 'AZ-'.$code;
}

/**
 * Keep the dossier and the warehouses of a third party in step with its "affiliated farm" flag
 *
 * @param	DoliDB		$db			Database handler
 * @param	Societe		$soc		Third party (extrafields loaded or loadable)
 * @param	User		$user		User making the change
 * @param	string[]	$messages	Messages for the user (filled)
 * @return	int						<0 if KO, 0 if nothing done, >0 if something changed
 */
function dolifarmSyncAffiliatedFarm($db, $soc, $user, &$messages)
{
	global $langs;

	if (!getDolGlobalInt('DOLIFARM_MULTIFARM') || empty($soc->id)) {
		return 0;
	}
	if (!isset($soc->array_options['options_dolifarm_affiliated'])) {
		$soc->fetch_optionals();
	}
	$affiliated = !empty($soc->array_options['options_dolifarm_affiliated']);
	$wasaffiliated = (is_object($soc->oldcopy) && !empty($soc->oldcopy->array_options['options_dolifarm_affiliated']));

	$langs->load('dolifarm@dolifarm');
	dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
	if (isModEnabled('stock')) {
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
	}

	// Dossier of the farm: the enabled one with the latest validation, else the latest draft, else a disabled one
	$dossier = null;
	$sql = "SELECT rowid FROM ".$db->prefix()."dolifarm_farmdossier";
	$sql .= " WHERE fk_soc = ".((int) $soc->id)." AND entity IN (".getEntity('dolifarmfarmdossier').")";
	$sql .= " ORDER BY CASE status WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 2 END, date_signature DESC, rowid DESC";
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if (!$resql) {
		$messages[] = $db->lasterror();
		return -1;
	}
	if ($obj = $db->fetch_object($resql)) {
		$dossier = new DoliFarmFarmDossier($db);
		if ($dossier->fetch($obj->rowid) <= 0) {
			$messages[] = $dossier->error;
			return -1;
		}
	}
	$db->free($resql);

	$changed = 0;
	$db->begin();

	if ($affiliated) {
		// Dossier: a draft is created when missing, a disabled one goes back to draft
		if (!$dossier) {
			$dossier = new DoliFarmFarmDossier($db);
			$dossier->fk_soc = $soc->id;
			$dossier->status = DoliFarmFarmDossier::STATUS_DRAFT;
			// The validation date is required in the form but unknown for a new affiliated farm
			$dossier->fields['date_signature']['notnull'] = 0;
			if ($dossier->create($user) <= 0) {
				$messages[] = $langs->trans('ErrorDolifarmAffiliatedDossier', $soc->name).' '.$dossier->error.' '.implode(' ', $dossier->errors);
				$db->rollback();
				return -1;
			}
			$messages[] = $langs->trans('DolifarmAffiliatedDossierCreated', $dossier->ref);
			$changed++;
		} elseif ($dossier->status == DoliFarmFarmDossier::STATUS_CANCELED) {
			if ($dossier->setStatusCommon($user, DoliFarmFarmDossier::STATUS_DRAFT, 0, $dossier->TRIGGER_PREFIX.'_REOPEN') < 0) {
				$messages[] = $dossier->error;
				$db->rollback();
				return -1;
			}
			$dossier->status = DoliFarmFarmDossier::STATUS_DRAFT;
			$messages[] = $langs->trans('DolifarmAffiliatedDossierReopened', $dossier->ref);
			$changed++;
		}

		// Warehouses: created when the dossier has none, opened again when closed
		if (isModEnabled('stock')) {
			$prefix = dolifarmAffiliatedWarehousePrefix($soc);
			$parentid = 0;
			$update = false;
			foreach (array('fk_warehouse_inputs' => '-IN', 'fk_warehouse_products' => '-PROD') as $field => $suffix) {
				$warehouse = new Entrepot($db);
				if (!empty($dossier->$field) && $warehouse->fetch($dossier->$field) > 0) {
					$res = dolifarmAffiliatedOpenWarehouse($db, $warehouse, $user);
				} else {
					if (empty($parentid)) {
						$parentid = dolifarmAffiliatedWarehouse($db, $prefix, 0, $soc, 'DolifarmAffiliatedWarehouse', $user, $messages);
						if ($parentid < 0) {
							$db->rollback();
							return -1;
						}
					}
					$res = dolifarmAffiliatedWarehouse($db, $prefix.$suffix, $parentid, $soc, $field == 'fk_warehouse_inputs' ? 'WarehouseInputs' : 'WarehouseProducts', $user, $messages);
					if ($res > 0) {
						$dossier->$field = $res;
						$update = true;
					}
				}
				if ($res < 0) {
					$messages[] = $warehouse->error;
					$db->rollback();
					return -1;
				}
				$changed += ($res > 0 ? 1 : 0);
			}
			if ($update && $dossier->update($user) < 0) {
				$messages[] = $dossier->error.' '.implode(' ', $dossier->errors);
				$db->rollback();
				return -1;
			}
		}
	} elseif ($dossier && $dossier->status != DoliFarmFarmDossier::STATUS_CANCELED) {
		// Flag removed: close the warehouses created for the farm and disable the dossier, without deleting anything.
		// The open "AZ-" warehouses on the dossier mean that the farm was affiliated (also without the old values).
		$closed = 0;
		if (isModEnabled('stock')) {
			foreach (array('fk_warehouse_inputs', 'fk_warehouse_products') as $field) {
				$warehouse = new Entrepot($db);
				if (!empty($dossier->$field) && $warehouse->fetch($dossier->$field) > 0 && strpos($warehouse->label, 'AZ-') === 0) {
					$tocloseids = array($warehouse->id);
					if ($warehouse->fk_parent > 0) {
						$tocloseids[] = (int) $warehouse->fk_parent;
					}
					foreach ($tocloseids as $tocloseid) {
						$toclose = new Entrepot($db);
						if ($toclose->fetch($tocloseid) > 0 && strpos($toclose->label, 'AZ-') === 0 && $toclose->statut != Entrepot::STATUS_CLOSED) {
							$toclose->statut = Entrepot::STATUS_CLOSED;
							if ($toclose->update($toclose->id, $user) <= 0) {
								$messages[] = $toclose->error;
								$db->rollback();
								return -1;
							}
							$closed++;
						}
					}
				}
			}
		}
		if ($closed || $wasaffiliated) {
			if ($dossier->setStatusCommon($user, DoliFarmFarmDossier::STATUS_CANCELED, 0, $dossier->TRIGGER_PREFIX.'_CANCEL') < 0) {
				$messages[] = $dossier->error;
				$db->rollback();
				return -1;
			}
			$messages[] = $langs->trans('DolifarmAffiliatedClosed', $dossier->ref);
			$changed++;
		}
	}

	$db->commit();
	return $changed;
}

/**
 * Find a warehouse by ref, or create it; open it when closed
 *
 * @param	DoliDB		$db			Database handler
 * @param	string		$ref		Ref of the warehouse
 * @param	int			$parentid	Id of the parent warehouse (0 = none)
 * @param	Societe		$soc		Third party of the farm
 * @param	string		$labelkey	Lang key of the description
 * @param	User		$user		User making the change
 * @param	string[]	$messages	Messages for the user (filled)
 * @return	int						Id of the warehouse, <0 if KO
 */
function dolifarmAffiliatedWarehouse($db, $ref, $parentid, $soc, $labelkey, $user, &$messages)
{
	global $langs;

	$warehouse = new Entrepot($db);
	if ($warehouse->fetch(0, $ref) > 0) {
		return (dolifarmAffiliatedOpenWarehouse($db, $warehouse, $user) < 0) ? -1 : $warehouse->id;
	}

	$warehouse->label = $ref;
	$warehouse->description = $langs->transnoentities($labelkey).' - '.$soc->name;
	$warehouse->lieu = (string) $soc->town;
	$warehouse->statut = Entrepot::STATUS_OPEN_ALL;
	$warehouse->fk_parent = $parentid;
	$id = $warehouse->create($user);
	if ($id <= 0) {
		$messages[] = $langs->trans('ErrorDolifarmAffiliatedWarehouse', $ref).' '.$warehouse->error;
		return -1;
	}
	$messages[] = $langs->trans('DolifarmAffiliatedWarehouseCreated', $ref);
	return $id;
}

/**
 * Open a warehouse when it is closed, with its "AZ-" parent
 *
 * @param	DoliDB		$db			Database handler
 * @param	Entrepot	$warehouse	Warehouse (fetched)
 * @param	User		$user		User making the change
 * @return	int						<0 if KO, 0 if already open, >0 if opened
 */
function dolifarmAffiliatedOpenWarehouse($db, $warehouse, $user)
{
	$opened = 0;
	if ($warehouse->fk_parent > 0) {
		$parent = new Entrepot($db);
		if ($parent->fetch($warehouse->fk_parent) > 0 && strpos($parent->label, 'AZ-') === 0) {
			$opened = dolifarmAffiliatedOpenWarehouse($db, $parent, $user);
			if ($opened < 0) {
				$warehouse->error = $parent->error;
				return -1;
			}
		}
	}
	if ($warehouse->statut != Entrepot::STATUS_CLOSED) {
		return $opened;
	}
	$warehouse->statut = Entrepot::STATUS_OPEN_ALL;
	return ($warehouse->update($warehouse->id, $user) > 0) ? 1 : -1;
}
