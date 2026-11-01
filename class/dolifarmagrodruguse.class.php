<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/dolifarmagrodruguse.class.php
 * \ingroup     dolifarm
 * \brief       Authorised use of a plant protection product (impiego autorizzato), as written on its label
 *
 * One row for each crop (EPPO code) and pest: dose range, pre-harvest interval, maximum number of applications.
 * The register of doliTrace compares its records with these rows. The rows are typed from the label: the open
 * data of the Ministero della Salute do not contain the uses.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Authorised use of a plant protection product
 */
class DoliFarmAgroDrugUse extends CommonObject
{
	/**
	 * @var string	ID of module
	 */
	public $module = 'dolifarm';

	/**
	 * @var string	ID to identify managed object
	 */
	public $element = 'dolifarmagrodruguse';

	/**
	 * @var string	Name of table without prefix where object is stored
	 */
	public $table_element = 'dolifarm_agrodrug_use';

	/**
	 * @var string	Field with ID of parent key
	 */
	public $fk_element = 'fk_agrodrug';

	/**
	 * @var int<0,1>	The entity is the one of the plant protection product
	 */
	public $ismultientitymanaged = 0;

	/**
	 * @var int<0,1>	No extrafields
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var string	Picto
	 */
	public $picto = 'fa-list-check';

	/**
	 * @var array<string,array<string,mixed>>	Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'fk_agrodrug' => array('type' => 'integer', 'label' => 'DoliFarmAgroDrug', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0),
		'crop_eppo' => array('type' => 'varchar(10)', 'label' => 'AgroDrugUseCrop', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'crop_label' => array('type' => 'varchar(255)', 'label' => 'AgroDrugUseCropLabel', 'enabled' => 1, 'position' => 11, 'notnull' => 0, 'visible' => 1),
		'adversity_code' => array('type' => 'varchar(10)', 'label' => 'AgroDrugUseAdversity', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'adversity_label' => array('type' => 'varchar(255)', 'label' => 'AgroDrugUseAdversityLabel', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'dose_min' => array('type' => 'double(24,8)', 'label' => 'AgroDrugUseDoseMin', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'dose_max' => array('type' => 'double(24,8)', 'label' => 'AgroDrugUseDoseMax', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'dose_unit' => array('type' => 'varchar(16)', 'label' => 'AgroDrugUseDoseUnit', 'enabled' => 1, 'position' => 32, 'notnull' => 0, 'visible' => 1),
		'safety_days' => array('type' => 'integer', 'label' => 'AgroDrugUseSafetyDays', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'max_applications' => array('type' => 'integer', 'label' => 'AgroDrugMaxApplications', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => 1),
		'note' => array('type' => 'varchar(255)', 'label' => 'Note', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	/**
	 * @var int
	 */
	public $rowid;

	/**
	 * @var int		Plant protection product
	 */
	public $fk_agrodrug;

	/**
	 * @var string	EPPO code of the crop
	 */
	public $crop_eppo;

	/**
	 * @var string|null	Crop as written on the label
	 */
	public $crop_label;

	/**
	 * @var string|null	EPPO code of the pest, empty for all the pests of the label on this crop
	 */
	public $adversity_code;

	/**
	 * @var string|null	Pest as written on the label
	 */
	public $adversity_label;

	/**
	 * @var float|string|null
	 */
	public $dose_min;

	/**
	 * @var float|string|null
	 */
	public $dose_max;

	/**
	 * @var string|null	Code of the dose unit (dictionary of doliTrace, for example KG_HA, ML_HL)
	 */
	public $dose_unit;

	/**
	 * @var int|string|null	Pre-harvest interval in days for this crop
	 */
	public $safety_days;

	/**
	 * @var int|string|null	Maximum number of applications for this crop
	 */
	public $max_applications;

	/**
	 * @var string|null
	 */
	public $note;

	/**
	 * @var int|string|null
	 */
	public $fk_user_creat;

	/**
	 * @var int|string|null
	 */
	public $fk_user_modif;

	/**
	 * @var string|null
	 */
	public $import_key;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create the use
	 *
	 * @param	User	$user		User that creates
	 * @param	int		$notrigger	1 = no triggers
	 * @return	int					Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		if ($this->checkData() < 0) {
			return -1;
		}
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load the use
	 *
	 * @param	int		$id		Id
	 * @return	int				>0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id)
	{
		return $this->fetchCommon($id);
	}

	/**
	 * Update the use
	 *
	 * @param	User	$user		User that modifies
	 * @param	int		$notrigger	1 = no triggers
	 * @return	int					>0 if OK, <0 if KO
	 */
	public function update(User $user, $notrigger = 0)
	{
		if ($this->checkData() < 0) {
			return -1;
		}
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete the use
	 *
	 * @param	User	$user		User that deletes
	 * @param	int		$notrigger	1 = no triggers
	 * @return	int					>0 if OK, <0 if KO
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Check the data: crop required, codes in upper case, dose range and numbers not negative
	 *
	 * @return	int		>0 if OK, <0 if KO
	 */
	public function checkData()
	{
		global $langs;

		$langs->loadLangs(array('main', 'dolifarm@dolifarm'));
		$this->crop_eppo = strtoupper(trim((string) $this->crop_eppo));
		$this->adversity_code = (trim((string) $this->adversity_code) === '' ? null : strtoupper(trim((string) $this->adversity_code)));
		$this->dose_unit = (trim((string) $this->dose_unit) === '' ? null : strtoupper(trim((string) $this->dose_unit)));
		if ($this->fk_agrodrug <= 0 || $this->crop_eppo === '') {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('AgroDrugUseCrop'));
			return -1;
		}
		// EPPO codes checked on the imported data (only the syntax without them); empty labels from the EPPO names
		dol_include_once('/dolifarm/lib/dolifarm_eppo.lib.php');
		foreach (array('cropgroup' => array('crop_eppo', 'crop_label'), 'pest' => array('adversity_code', 'adversity_label')) as $use => $fields) {
			$code = (string) $this->{$fields[0]};
			if ($code === '') {
				continue;
			}
			$error = dolifarmEppoCheckCode($this->db, $code, $use);
			if ($error !== '') {
				$this->error = $error;
				return -1;
			}
			if (trim((string) $this->{$fields[1]}) === '') {
				$eppo = dolifarmEppoFetch($this->db, $code);
				if ($eppo) {
					$this->{$fields[1]} = dol_trunc((string) (substr($langs->defaultlang, 0, 2) == 'it' && $eppo->name_it ? $eppo->name_it : ($eppo->name_en ?: $eppo->prefname)), 250, 'right', 'UTF-8', 1);
				}
			}
		}
		foreach (array('dose_min', 'dose_max', 'safety_days', 'max_applications') as $field) {
			if ($this->$field === '') {
				$this->$field = null;
			}
			if ($this->$field !== null && (float) $this->$field < 0) {
				$this->error = $langs->trans('ErrorAgroDrugUseNegative', $langs->transnoentitiesnoconv($this->fields[$field]['label']));
				return -1;
			}
		}
		if ($this->dose_min !== null && $this->dose_max !== null && (float) $this->dose_min > (float) $this->dose_max) {
			$this->error = $langs->trans('ErrorAgroDrugUseDoseRange');
			return -1;
		}
		if (($this->dose_min !== null || $this->dose_max !== null) && $this->dose_unit === null) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('AgroDrugUseDoseUnit'));
			return -1;
		}
		return 1;
	}

	/**
	 * Uses of a plant protection product
	 *
	 * @param	DoliDB	$db				Database handler
	 * @param	int		$fk_agrodrug	Plant protection product
	 * @return	array<int,stdClass>		Rows by id, ordered by crop and pest
	 */
	public static function fetchAllForAgroDrug($db, $fk_agrodrug)
	{
		$uses = array();
		$sql = "SELECT rowid, fk_agrodrug, crop_eppo, crop_label, adversity_code, adversity_label, dose_min, dose_max, dose_unit, safety_days, max_applications, note";
		$sql .= " FROM ".$db->prefix()."dolifarm_agrodrug_use";
		$sql .= " WHERE fk_agrodrug = ".((int) $fk_agrodrug);
		$sql .= " ORDER BY crop_label, crop_eppo, adversity_label, adversity_code, rowid";
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
			return $uses;
		}
		while ($obj = $db->fetch_object($resql)) {
			$uses[(int) $obj->rowid] = $obj;
		}
		$db->free($resql);
		return $uses;
	}
}
