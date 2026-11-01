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
 * \file        class/dolifarmoperator.class.php
 * \ingroup     dolifarm
 * \brief       CRUD class for DoliFarmOperator: a person who carries out the treatments and fertilizations (operatore), with the license for the plant protection products
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/dolifarm/class/dolifarmobjecttrait.class.php');

/**
 * Class for DoliFarmOperator
 */
class DoliFarmOperator extends CommonObject
{
	use DoliFarmObjectTrait;

	/**
	 * @var string      ID of module.
	 */
	public $module = 'dolifarm';

	/**
	 * @var string      ID to identify managed object.
	 */
	public $element = 'dolifarmoperator';

	/**
	 * @var string      Prefix of the trigger codes
	 */
	public $TRIGGER_PREFIX = 'DOLIFARM_OPERATOR';

	/**
	 * @var string      Name of table without prefix where object is stored
	 */
	public $table_element = 'dolifarm_operator';

	/**
	 * @var string      Icon of the object
	 */
	public $picto = 'fa-user-shield';

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
		"label" => array("type" => "varchar(255)", "label" => "OperatorName", "enabled" => "1", 'position' => 20, 'notnull' => 1, "visible" => "1", "searchall" => "1", "css" => "minwidth300", "cssview" => "wordbreak", "csslist" => "tdoverflowmax150", "showoncombobox" => "2"),
		"fk_soc" => array("type" => "integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))", "label" => "ThirdParty", "picto" => "company", "enabled" => "1", 'position' => 21, 'notnull' => 1, "visible" => "1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "help" => "OperatorThirdPartyHelp"),
		"fk_socpeople" => array("type" => "integer:Contact:contact/class/contact.class.php:1", "label" => "Contact", "picto" => "contact", "enabled" => "1", 'position' => 22, 'notnull' => 0, "visible" => "-1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "help" => "OperatorContactHelp"),
		"fk_user" => array("type" => "integer:User:user/class/user.class.php", "label" => "User", "picto" => "user", "enabled" => "1", 'position' => 23, 'notnull' => 0, "visible" => "-1", "index" => "1", "css" => "maxwidth500 widthcentpercentminusxx", "csslist" => "tdoverflowmax150", "help" => "OperatorUserHelp"),
		"tax_code" => array("type" => "varchar(32)", "label" => "OperatorTaxCode", "enabled" => "1", 'position' => 24, 'notnull' => 0, "visible" => "1", "searchall" => "1", "css" => "maxwidth200", "copytoclipboard" => "1"),
		"license_type" => array("type" => "integer", "label" => "LicenseType", "enabled" => "1", 'position' => 30, 'notnull' => 0, "visible" => "1", "arrayofkeyval" => array("1" => "LicenseTypeUser", "2" => "LicenseTypeDistributor", "3" => "LicenseTypeAdvisor"), "help" => "LicenseTypeHelp"),
		"license_number" => array("type" => "varchar(64)", "label" => "LicenseNumber", "enabled" => "1", 'position' => 31, 'notnull' => 0, "visible" => "1", "searchall" => "1", "css" => "maxwidth200", "copytoclipboard" => "1"),
		"license_issuer" => array("type" => "varchar(128)", "label" => "LicenseIssuer", "enabled" => "1", 'position' => 32, 'notnull' => 0, "visible" => "-1", "css" => "minwidth300", "help" => "LicenseIssuerHelp"),
		"license_date" => array("type" => "date", "label" => "LicenseDate", "enabled" => "1", 'position' => 33, 'notnull' => 0, "visible" => "-1"),
		"license_end" => array("type" => "date", "label" => "LicenseEnd", "enabled" => "1", 'position' => 34, 'notnull' => 0, "visible" => "1", "help" => "LicenseEndHelp"),
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
	public $fk_socpeople;
	public $fk_user;
	public $tax_code;
	public $license_type;
	public $license_number;
	public $license_issuer;
	public $license_date;
	public $license_end;
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
		$this->picto = dolifarm_get_fa_icon('operator');
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
		if (!empty($this->license_date) && !empty($this->license_end) && $this->license_end < $this->license_date) {
			$this->error = $langs->trans('ErrorPeriodEndBeforeStart');
			return -1;
		}
		if (!empty($this->tax_code)) {
			$this->tax_code = strtoupper(trim((string) $this->tax_code));
		}
		// The contact must be one of the third party
		if (!empty($this->fk_socpeople) && $this->fk_socpeople > 0 && !empty($this->fk_soc)) {
			$sql = "SELECT fk_soc FROM ".$this->db->prefix()."socpeople WHERE rowid = ".((int) $this->fk_socpeople);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->fk_soc != (int) $this->fk_soc) {
				$this->error = $langs->trans('ErrorContactOfAnotherThirdParty');
				return -1;
			}
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
		return 'DoliFarmOperator';
	}

	/**
	 * Initialize object with example values
	 *
	 * @return	int
	 */
	public function initAsSpecimen()
	{
		$this->ref = 'OPER-SPEC-001';
		$this->label = 'Mario Rossi';
		$this->tax_code = 'RSSMRA80A01H501U';
		$this->license_type = 1;
		$this->license_number = 'PAN-123456';

		return $this->initAsSpecimenCommon();
	}
}
