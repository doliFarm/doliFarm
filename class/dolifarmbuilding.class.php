<?php
/* Copyright (C) 2017      Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2023-2025 Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file        class/dolifarmbuilding.class.php
 * \ingroup     dolifarm
 * \brief       CRUD class for DoliFarmBuilding: a building of the farm dossier (fabbricati)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolifarm/class/dolifarmobjecttrait.class.php');

/**
 * Class for DoliFarmBuilding
 */
class DoliFarmBuilding extends CommonObject
{
	use DoliFarmObjectTrait;

	/**
	 * @var string      ID of module.
	 */
	public $module = 'dolifarm';

	/**
	 * @var string      ID to identify managed object.
	 */
	public $element = 'dolifarmbuilding';

	/**
	 * @var string      Prefix of the trigger codes
	 */
	public $TRIGGER_PREFIX = 'DOLIFARM_BUILDING';

	/**
	 * @var string      Name of table without prefix where object is stored
	 */
	public $table_element = 'dolifarm_building';

	/**
	 * @var string      Icon of the object
	 */
	public $picto = 'fa-warehouse';

	/**
	 * @var int<0,1>    Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var int<0,1>|string     Does this object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CANCELED = 9;

	/**
	 * @inheritdoc
	 * Array with all fields and their property (see DoliFarmPlot for the meaning of the keys)
	 */
	public $fields = array(
		"rowid" => array("type" => "integer", "label" => "TechnicalID", "enabled" => "1", 'position' => 1, 'notnull' => 1, "visible" => "0", "noteditable" => "1", "index" => "1"),
		"ref" => array("type" => "varchar(128)", "label" => "Ref", "enabled" => "1", 'position' => 10, 'notnull' => 1, "visible" => "4", "noteditable" => "1", "index" => "1", "searchall" => "1", "showoncombobox" => "1", "csslist" => "tdoverflowmax150"),
		"entity" => array("type" => "integer", "label" => "Entity", "enabled" => "1", 'position' => 11, 'notnull' => 1, "visible" => "0", 'default' => 1),
		"label" => array("type" => "varchar(255)", "label" => "Label", "enabled" => "1", 'position' => 20, 'notnull' => 0, "visible" => "1", "searchall" => "1", "css" => "minwidth300", "cssview" => "wordbreak", "csslist" => "tdoverflowmax150", "showoncombobox" => "2"),
		"fk_soc" => array("type" => "integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))", "label" => "ThirdParty", "picto" => "company", "enabled" => "1", 'position' => 21, 'notnull' => 1, "visible" => "1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150"),
		"fk_dossier" => array("type" => "integer:DoliFarmFarmDossier:dolifarm/class/dolifarmfarmdossier.class.php", "label" => "FkDossier", "enabled" => "1", 'position' => 22, 'notnull' => 1, "visible" => "1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150"),
		"fk_plot" => array("type" => "integer:DoliFarmPlot:dolifarm/class/dolifarmplot.class.php", "label" => "BuildingPlot", "enabled" => "1", 'position' => 23, 'notnull' => 0, "visible" => "-1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "help" => "BuildingPlotHelp"),
		"building_use" => array("type" => "varchar(128)", "label" => "BuildingUse", "enabled" => "1", 'position' => 30, 'notnull' => 0, "visible" => "1", "searchall" => "1", "css" => "minwidth300", "help" => "BuildingUseHelp"),
		"fk_ownership" => array("type" => "sellist:dolifarm_c_ownership:label:code::active=1", "label" => "Ownershiptype", "enabled" => "1", 'position' => 31, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth300 widthcentpercentminusx"),
		"date_conduction_start" => array("type" => "date", "label" => "DateConductionStart", "enabled" => "1", 'position' => 32, 'notnull' => 0, "visible" => "-1"),
		"date_conduction_end" => array("type" => "date", "label" => "DateConductionEnd", "enabled" => "1", 'position' => 33, 'notnull' => 0, "visible" => "-1"),
		"fk_warehouse" => array("type" => "integer:Entrepot:product/stock/class/entrepot.class.php:0:((statut:>:0) AND (entity:IN:__SHARED_ENTITIES__))", "label" => "BuildingWarehouse", "picto" => "stock", "enabled" => "isModEnabled('stock')", 'position' => 34, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth500 widthcentpercentminusx", "csslist" => "tdoverflowmax150", "help" => "BuildingWarehouseHelp"),
		"address" => array("type" => "varchar(255)", "label" => "Address", "enabled" => "1", 'position' => 39, 'notnull' => 0, "visible" => "-1", "css" => "minwidth300", "help" => "BuildingAddressHelp"),
		"cadastral_comune" => array("type" => "varchar(100)", "label" => "Cadastralcomune", "enabled" => "1", 'position' => 40, 'notnull' => 0, "visible" => "1", "css" => "maxwidth300"),
		"cadastral_section" => array("type" => "varchar(10)", "label" => "CadastralSection", "enabled" => "1", 'position' => 41, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth100"),
		"cadastral_sheet" => array("type" => "varchar(10)", "label" => "Cadastralsheet", "enabled" => "1", 'position' => 42, 'notnull' => 0, "visible" => "1", "css" => "maxwidth100"),
		"cadastral_parcel" => array("type" => "varchar(10)", "label" => "Cadastralparcel", "enabled" => "1", 'position' => 43, 'notnull' => 0, "visible" => "1", "css" => "maxwidth100"),
		"cadastral_sub" => array("type" => "varchar(10)", "label" => "Cadastralsub", "enabled" => "1", 'position' => 44, 'notnull' => 0, "visible" => "1", "css" => "maxwidth100"),
		"surface_covered" => array("type" => "double(24,8)", "label" => "SurfaceCovered", "enabled" => "1", 'position' => 50, 'notnull' => 0, "visible" => "1", "isameasure" => "1", "css" => "maxwidth100", "csslist" => "right"),
		"surface_uncovered" => array("type" => "double(24,8)", "label" => "SurfaceUncovered", "enabled" => "1", 'position' => 51, 'notnull' => 0, "visible" => "-1", "isameasure" => "1", "css" => "maxwidth100", "csslist" => "right"),
		"volume" => array("type" => "double(24,8)", "label" => "BuildingVolume", "enabled" => "1", 'position' => 52, 'notnull' => 0, "visible" => "-1", "isameasure" => "1", "css" => "maxwidth100", "csslist" => "right"),
		"nb_seats" => array("type" => "integer", "label" => "BuildingSeats", "enabled" => "1", 'position' => 53, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth100", "csslist" => "right", "help" => "BuildingSeatsHelp"),
		"nb_users" => array("type" => "integer", "label" => "BuildingUsers", "enabled" => "1", 'position' => 54, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth100", "csslist" => "right", "help" => "BuildingUsersHelp"),
		"note_public" => array("type" => "html", "label" => "NotePublic", "enabled" => "1", 'position' => 100, 'notnull' => 0, "visible" => "0", "cssview" => "wordbreak"),
		"note_private" => array("type" => "html", "label" => "NotePrivate", "enabled" => "1", 'position' => 105, 'notnull' => 0, "visible" => "0", "cssview" => "wordbreak"),
		"date_creation" => array("type" => "datetime", "label" => "DateCreation", "enabled" => "1", 'position' => 500, 'notnull' => 0, "visible" => "-2"),
		"tms" => array("type" => "timestamp", "label" => "DateModification", "enabled" => "1", 'position' => 501, 'notnull' => 0, "visible" => "-2"),
		"fk_user_creat" => array("type" => "integer:User:user/class/user.class.php", "label" => "UserAuthor", "picto" => "user", "enabled" => "1", 'position' => 510, 'notnull' => 0, "visible" => "-2", "csslist" => "tdoverflowmax150"),
		"fk_user_modif" => array("type" => "integer:User:user/class/user.class.php", "label" => "UserModif", "picto" => "user", "enabled" => "1", 'position' => 511, 'notnull' => -1, "visible" => "-2", "csslist" => "tdoverflowmax150"),
		"import_key" => array("type" => "varchar(14)", "label" => "ImportId", "enabled" => "1", 'position' => 1000, 'notnull' => -1, "visible" => "-2"),
		"model_pdf" => array("type" => "varchar(255)", "label" => "Model pdf", "enabled" => "1", 'position' => 1010, 'notnull' => -1, "visible" => "0"),
		"last_main_doc" => array("type" => "varchar(255)", "label" => "LastMainDoc", "enabled" => "1", 'position' => 1020, 'notnull' => -1, "visible" => "0"),
		"status" => array("type" => "integer", "label" => "Status", "enabled" => "1", 'position' => 2000, 'notnull' => 1, "visible" => "5", "default" => "1", "index" => "1", "arrayofkeyval" => array("1" => "Enabled", "9" => "Disabled")),
	);

	// Properties corresponding to fields
	public $rowid;
	public $ref;
	public $entity;
	public $label;
	public $fk_soc;
	public $fk_dossier;
	public $fk_plot;

	/**
	 * @var int|null	Warehouse of Dolibarr kept in the building (storage site of the AGEA field notebook)
	 */
	public $fk_warehouse;

	/**
	 * @var string|null	Address of the building (AGEA field notebook: storage sites and treated products)
	 */
	public $address;
	public $building_use;
	public $fk_ownership;
	public $date_conduction_start;
	public $date_conduction_end;
	public $cadastral_comune;
	public $cadastral_section;
	public $cadastral_sheet;
	public $cadastral_parcel;
	public $cadastral_sub;
	public $surface_covered;
	public $surface_uncovered;
	public $volume;
	public $nb_seats;
	public $nb_users;
	public $note_public;
	public $note_private;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;
	public $model_pdf;
	public $last_main_doc;
	public $status;


	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
		dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');
		$this->picto = dolifarm_get_fa_icon('building');
		$this->initFieldsDefinition();
		// Drop-down lists only with the farms the user can see
		dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
		dolifarmRestrictLinkFields($this, array('fk_dossier', 'fk_plot'));
	}

	/**
	 * Check the consistency of the data before saving
	 *
	 * @return	int		Return integer <0 if KO, >0 if OK
	 */
	public function checkData()
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		if (!empty($this->date_conduction_start) && !empty($this->date_conduction_end) && $this->date_conduction_end < $this->date_conduction_start) {
			$this->error = $langs->trans('ErrorConductionEndBeforeStart');
			return -1;
		}
		foreach (array('surface_covered', 'surface_uncovered', 'volume', 'nb_seats', 'nb_users') as $key) {
			if ($this->$key !== null && $this->$key !== '' && (float) $this->$key < 0) {
				$this->error = $langs->trans('ErrorValueMustBePositive', $langs->transnoentitiesnoconv($this->fields[$key]['label']));
				return -1;
			}
		}
		// The plot must be of the same third party
		if (!empty($this->fk_plot) && $this->fk_plot > 0 && !empty($this->fk_soc)) {
			$sql = "SELECT fk_soc FROM ".$this->db->prefix()."dolifarm_plot WHERE rowid = ".((int) $this->fk_plot);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->fk_soc != (int) $this->fk_soc) {
				$this->error = $langs->trans('ErrorPlotOfAnotherThirdParty');
				return -1;
			}
		}
		return $this->checkDossierOfThirdparty();
	}

	/**
	 * Return the label to use in tooltips and links
	 *
	 * @return	string
	 */
	public function getObjectLabel()
	{
		return 'DoliFarmBuilding';
	}

	/**
	 * Initialize object with example values
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		$this->ref = 'FABB-SPEC-001';
		$this->label = 'Stalla';
		$this->building_use = 'Ricovero animali';
		$this->surface_covered = 450;

		return $this->initAsSpecimenCommon();
	}
}
