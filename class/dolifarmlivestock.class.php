<?php
/* Copyright (C) 2017      Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2023-2025 Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026      SuperAdmin
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
 * \file        class/dolifarmlivestock.class.php
 * \ingroup     dolifarm
 * \brief       CRUD class for DoliFarmLivestock: a herd of the farm dossier (consistenza zootecnica)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolifarm/class/dolifarmobjecttrait.class.php');

/**
 * Class for DoliFarmLivestock
 */
class DoliFarmLivestock extends CommonObject
{
	use DoliFarmObjectTrait;

	/**
	 * @var string      ID of module.
	 */
	public $module = 'dolifarm';

	/**
	 * @var string      ID to identify managed object.
	 */
	public $element = 'dolifarmlivestock';

	/**
	 * @var string      Prefix of the trigger codes
	 */
	public $TRIGGER_PREFIX = 'DOLIFARM_LIVESTOCK';

	/**
	 * @var string      Name of table without prefix where object is stored
	 */
	public $table_element = 'dolifarm_livestock';

	/**
	 * @var string      Icon of the object
	 */
	public $picto = 'fa-paw';

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
		"bdn_code" => array("type" => "varchar(32)", "label" => "BdnCode", "enabled" => "1", 'position' => 30, 'notnull' => 0, "visible" => "1", "searchall" => "1", "css" => "maxwidth200", "help" => "BdnCodeHelp", "copytoclipboard" => "1"),
		"fk_species" => array("type" => "sellist:dolifarm_c_livestock_species:label:code::active=1", "label" => "LivestockSpecies", "enabled" => "1", 'position' => 31, 'notnull' => 0, "visible" => "1", "css" => "maxwidth300 widthcentpercentminusx"),
		"nb_heads" => array("type" => "integer", "label" => "NbHeads", "enabled" => "1", 'position' => 32, 'notnull' => 0, "visible" => "1", "isameasure" => "1", "css" => "maxwidth100", "csslist" => "right"),
		"production_type" => array("type" => "varchar(64)", "label" => "ProductionType", "enabled" => "1", 'position' => 40, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth300", "help" => "ProductionTypeHelp"),
		"breeding_type" => array("type" => "varchar(64)", "label" => "BreedingType", "enabled" => "1", 'position' => 41, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth300", "help" => "BreedingTypeHelp"),
		"breeding_mode" => array("type" => "varchar(64)", "label" => "BreedingMode", "enabled" => "1", 'position' => 42, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth300", "help" => "BreedingModeHelp"),
		"milk_authorization" => array("type" => "varchar(64)", "label" => "MilkAuthorization", "enabled" => "1", 'position' => 43, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth300", "help" => "MilkAuthorizationHelp"),
		"organic" => array("type" => "boolean", "label" => "OrganicLivestock", "enabled" => "1", 'position' => 44, 'notnull' => 0, "visible" => "-1"),
		"address" => array("type" => "varchar(255)", "label" => "Address", "enabled" => "1", 'position' => 50, 'notnull' => 0, "visible" => "-1", "css" => "minwidth300", "cssview" => "wordbreak"),
		"town" => array("type" => "varchar(100)", "label" => "Town", "enabled" => "1", 'position' => 51, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth300"),
		"holder" => array("type" => "varchar(255)", "label" => "LivestockHolder", "enabled" => "1", 'position' => 52, 'notnull' => 0, "visible" => "-1", "css" => "minwidth300", "help" => "LivestockHolderHelp"),
		"date_start" => array("type" => "date", "label" => "DateStart", "enabled" => "1", 'position' => 60, 'notnull' => 0, "visible" => "-1"),
		"date_end" => array("type" => "date", "label" => "DateEnd", "enabled" => "1", 'position' => 61, 'notnull' => 0, "visible" => "-1"),
		"date_bdn_update" => array("type" => "date", "label" => "DateBdnUpdate", "enabled" => "1", 'position' => 62, 'notnull' => 0, "visible" => "-1"),
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
	public $bdn_code;
	public $fk_species;
	public $nb_heads;
	public $production_type;
	public $breeding_type;
	public $breeding_mode;
	public $milk_authorization;
	public $organic;
	public $address;
	public $town;
	public $holder;
	public $date_start;
	public $date_end;
	public $date_bdn_update;
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
		$this->picto = dolifarm_get_fa_icon('livestock');
		$this->initFieldsDefinition();
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
		if (!empty($this->date_start) && !empty($this->date_end) && $this->date_end < $this->date_start) {
			$this->error = $langs->trans('ErrorPeriodEndBeforeStart');
			return -1;
		}
		if ($this->nb_heads !== null && $this->nb_heads !== '' && (int) $this->nb_heads < 0) {
			$this->error = $langs->trans('ErrorNbHeadsNegative');
			return -1;
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
		return 'DoliFarmLivestock';
	}

	/**
	 * Initialize object with example values
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		$this->ref = 'ALLEV-SPEC-001';
		$this->label = 'Allevamento bovini da latte';
		$this->bdn_code = '001AB123';
		$this->fk_species = 'BOVINI';
		$this->nb_heads = 120;

		return $this->initAsSpecimenCommon();
	}
}
