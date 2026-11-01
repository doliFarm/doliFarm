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
 * \file        class/dolifarmfertilizer.class.php
 * \ingroup     dolifarm
 * \brief       CRUD class for DoliFarmFertilizer: a fertilizer of the catalog (concime, ammendante, correttivo), with its nutrient content
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolifarm/class/dolifarmobjecttrait.class.php');

/**
 * Class for DoliFarmFertilizer
 */
class DoliFarmFertilizer extends CommonObject
{
	use DoliFarmObjectTrait;

	/**
	 * @var string      ID of module.
	 */
	public $module = 'dolifarm';

	/**
	 * @var string      ID to identify managed object.
	 */
	public $element = 'dolifarmfertilizer';

	/**
	 * @var string      Prefix of the trigger codes
	 */
	public $TRIGGER_PREFIX = 'DOLIFARM_FERTILIZER';

	/**
	 * @var string      Name of table without prefix where object is stored
	 */
	public $table_element = 'dolifarm_fertilizer';

	/**
	 * @var string      Icon of the object
	 */
	public $picto = 'fa-tint';

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
		"label" => array("type" => "varchar(255)", "label" => "Label", "enabled" => "1", 'position' => 20, 'notnull' => 1, "visible" => "1", "searchall" => "1", "css" => "minwidth300", "cssview" => "wordbreak", "csslist" => "tdoverflowmax150", "showoncombobox" => "2"),
		"fk_product" => array("type" => "integer:Product:product/class/product.class.php:1", "label" => "Product", "picto" => "product", "enabled" => "1", 'position' => 21, 'notnull' => 0, "visible" => "-1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "help" => "FertilizerProductHelp"),
		"fk_fert_type" => array("type" => "sellist:dolifarm_c_fert_type:label:code::active=1", "label" => "FertilizerType", "enabled" => "1", 'position' => 22, 'notnull' => 0, "visible" => "1", "css" => "maxwidth300 widthcentpercentminusx"),
		"manufacturer" => array("type" => "varchar(255)", "label" => "FertilizerManufacturer", "enabled" => "1", 'position' => 23, 'notnull' => 0, "visible" => "1", "searchall" => "1", "css" => "minwidth300", "csslist" => "tdoverflowmax150"),
		"registration_number" => array("type" => "varchar(64)", "label" => "FertilizerRegistration", "enabled" => "1", 'position' => 24, 'notnull' => 0, "visible" => "-1", "searchall" => "1", "css" => "maxwidth200", "help" => "FertilizerRegistrationHelp", "copytoclipboard" => "1"),
		"n_content" => array("type" => "double(8,2)", "label" => "NitrogenContent", "enabled" => "1", 'position' => 30, 'notnull' => 0, "visible" => "1", "css" => "maxwidth75", "csslist" => "right", "help" => "NutrientContentHelp"),
		"p2o5_content" => array("type" => "double(8,2)", "label" => "PhosphorusContent", "enabled" => "1", 'position' => 31, 'notnull' => 0, "visible" => "1", "css" => "maxwidth75", "csslist" => "right", "help" => "NutrientContentHelp"),
		"k2o_content" => array("type" => "double(8,2)", "label" => "PotassiumContent", "enabled" => "1", 'position' => 32, 'notnull' => 0, "visible" => "1", "css" => "maxwidth75", "csslist" => "right", "help" => "NutrientContentHelp"),
		"cu_content" => array("type" => "double(8,2)", "label" => "CopperContent", "enabled" => "1", 'position' => 33, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth75", "csslist" => "right", "help" => "NutrientContentHelp"),
		"efficiency" => array("type" => "double(8,2)", "label" => "FertilizerEfficiency", "enabled" => "1", 'position' => 34, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth75", "csslist" => "right", "help" => "FertilizerEfficiencyHelp"),
		"dry_matter" => array("type" => "double(8,2)", "label" => "FertilizerDryMatter", "enabled" => "1", 'position' => 35, 'notnull' => 0, "visible" => "-1", "css" => "maxwidth75", "csslist" => "right", "help" => "FertilizerDryMatterHelp"),
		"organic" => array("type" => "boolean", "label" => "AllowedInOrganic", "enabled" => "1", 'position' => 33, 'notnull' => 0, "visible" => "1", "help" => "AllowedInOrganicHelp"),
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
	public $fk_product;
	public $fk_fert_type;
	public $manufacturer;
	public $registration_number;
	public $n_content;

	/**
	 * @var float|string|null	Copper content (%), AGEA field notebook sheet 7
	 */
	public $cu_content;

	/**
	 * @var float|string|null	Efficiency of the nitrogen (coefficient from 0 to 1), AGEA field notebook sheet 6
	 */
	public $efficiency;

	/**
	 * @var float|string|null	Dry matter (%), AGEA field notebook sheet 6
	 */
	public $dry_matter;
	public $p2o5_content;
	public $k2o_content;
	public $organic;
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
		$this->picto = dolifarm_get_fa_icon('fertilizer');
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
		foreach (array('n_content', 'p2o5_content', 'k2o_content', 'cu_content', 'dry_matter') as $field) {
			if ($this->$field !== null && $this->$field !== '' && ((float) $this->$field < 0 || (float) $this->$field > 100)) {
				$this->error = $langs->trans('ErrorNutrientContent', $langs->transnoentitiesnoconv($this->fields[$field]['label']));
				return -1;
			}
		}
		if ($this->efficiency !== null && $this->efficiency !== '' && ((float) $this->efficiency < 0 || (float) $this->efficiency > 1)) {
			$this->error = $langs->trans('ErrorFertilizerEfficiency');
			return -1;
		}
		return 1;
	}

	/**
	 * Return the label to use in tooltips and links
	 *
	 * @return	string
	 */
	public function getObjectLabel()
	{
		return 'DoliFarmFertilizer';
	}

	/**
	 * Initialize object with example values
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		$this->ref = 'FERT-SPEC-001';
		$this->label = 'Concime NPK 20-10-10';
		$this->fk_fert_type = 'MIN_NPK';
		$this->n_content = 20;
		$this->p2o5_content = 10;
		$this->k2o_content = 10;

		return $this->initAsSpecimenCommon();
	}
}
