<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2026		SuperAdmin
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
 * 	\defgroup   dolifarm     Module Dolifarm
 *  \brief      Dolifarm module descriptor.
 *
 *  \file       htdocs/dolifarm/core/modules/modDolifarm.class.php
 *  \ingroup    dolifarm
 *  \brief      Description and activation file for module Dolifarm
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');

/**
 *  Description and activation class for module Dolifarm
 */
class modDolifarm extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');
		$this->picto = dolifarm_get_fa_icon('dolifarm');
		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 500000; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'dolifarm';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "other";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleDolifarmName' not found (Dolifarm is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// DESCRIPTION_FLAG
		// Module description, used if translation string 'ModuleDolifarmDesc' not found (Dolifarm is name of module).
		$this->description = "DolifarmDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "DolifarmDescription";

		// Author
		$this->editor_name = 'doliFarm.com';
		$this->editor_url = 'https://www.dolifarm.com';		// Must be an external online web site
		$this->editor_squarred_logo = '';					// Must be image filename into the module/img directory followed with @modulename. Example: 'myimage.png@dolifarm'

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where DOLIFARM is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = dolifarm_get_fa_icon('dolifarm');

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 0,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				//    '/dolifarm/css/dolifarm.css.php',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				//   '/dolifarm/js/dolifarm.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			/* BEGIN MODULEBUILDER HOOKSCONTEXTS */
			'hooks' => array(
				//   'data' => array(
				//       'hookcontext1',
				//       'hookcontext2',
				//   ),
				//   'entity' => '0',
			),
			/* END MODULEBUILDER HOOKSCONTEXTS */
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
			// Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
			'websitetemplates' => 0,
			// Set this to 1 if the module provides a captcha driver
			'captcha' => 0
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/dolifarm/temp","/dolifarm/subdir");
		$this->dirs = array("/dolifarm/temp");

		// Config pages. Put here list of php page, stored into dolifarm/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@dolifarm");

		// Dependencies
		// A condition to hide module
		$this->hidden = getDolGlobalInt('MODULE_DOLIFARM_DISABLED'); // A condition to disable module;
		// List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
		$this->depends = array();
		// List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->requiredby = array();
		// List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("dolifarm@dolifarm");

		// Prerequisites
		$this->phpmin = array(7, 2); // Minimum version of PHP required by module
		// $this->phpmax = array(8, 0); // Maximum version of PHP required by module
		$this->need_dolibarr_version = array(19, -3); // Minimum version of Dolibarr required by module
		// $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); 		// Warning to show when we activate a module. Example: array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); 	// Warning to show when we activate a module if another module is on. Example: array('modOtherModule' => array('always'=>'text')) or array('always' => array('FR'=>'textfr','MX'=>'textmx'...))
		//$this->automatic_activation = array('FR'=>'DolifarmWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = false;			// If true, can't be disabled. Value true is reserved for core modules. Not allowed for external modules.

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('DOLIFARM_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('DOLIFARM_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array();

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isModEnabled("dolifarm")) {
			$conf->dolifarm = new stdClass();
			$conf->dolifarm->enabled = 0;
		}

		// Array to add new pages in new tabs
		/* BEGIN MODULEBUILDER TABS */
		// Don't forget to deactivate/reactivate your module to test your changes
		$this->tabs = array();
		/* END MODULEBUILDER TABS */
		// Example:
		// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data' => 'objecttype:+tabname1:Title1:mylangfile@dolifarm:$user->hasRight('dolifarm', 'dolifarmplot', 'read'):/dolifarm/mynewtab1.php?id=__ID__');
		// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data' => 'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@dolifarm:$user->hasRight('othermodule', 'otherobject', 'read'):/dolifarm/mynewtab2.php?id=__ID__',
		// To remove an existing tab identified by code tabname
		// $this->tabs[] = array('data' => 'objecttype:-tabname:NU:conditiontoremove');
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'delivery'         to add a tab in delivery view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'supplier_invoice' to add a tab in supplier invoice view
		// 'member'           to add a tab in foundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in sale order view
		// 'supplier_order'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'supplier_payment' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view


		// Dictionaries
		/* Example:
		 $this->dictionaries=array(
		 'langs' => 'dolifarm@dolifarm',
		 // List of tables we want to see into dictionary editor
		 'tabname' => array("table1", "table2", "table3"),
		 // Label of tables
		 'tablib' => array("Table1", "Table2", "Table3"),
		 // Request to select fields
		 'tabsql' => array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table3 as f'),
		 // Sort order
		 'tabsqlsort' => array("label ASC", "label ASC", "label ASC"),
		 // List of fields (result of select to show dictionary)
		 'tabfield' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields to edit a record)
		 'tabfieldvalue' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields for insert)
		 'tabfieldinsert' => array("code,label", "code,label", "code,label"),
		 // Name of columns with primary key (try to always name it 'rowid')
		 'tabrowid' => array("rowid", "rowid", "rowid"),
		 // Condition to show each dictionary
		 'tabcond' => array(isModEnabled('dolifarm'), isModEnabled('dolifarm'), isModEnabled('dolifarm')),
		 // Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
		 'tabhelp' => array(array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		 );
		 */
		/* BEGIN MODULEBUILDER DICTIONARIES */
        // Dictionaries
        $this->dictionaries = array(
            'langs' => 'dolifarm@dolifarm',
            
            // List of tables we want to see into dictionary editor
            'tabname' => array(
                'dolifarm_c_ownership',
                'dolifarm_c_biostatus',
                'dolifarm_c_machinetype',
                'dolifarm_c_fueltype',
                'dolifarm_c_operation_type' // [NUOVO] Tabella Tipi Operazione
            ),
            
            // Label of tables
            'tablib' => array(
                'DictionaryOwnership',
                'DictionaryBioStatus',
                'DictionaryMachineType',
                'DictionaryFuelType',
                'DictionaryOperationType'   // [NUOVO] Chiave da tradurre in dolifarm.lang
            ),
            
            // Request to select fields
            'tabsql' => array(
                'SELECT rowid, code, label, active, position FROM '.MAIN_DB_PREFIX.'dolifarm_c_ownership',
                'SELECT rowid, code, label, description, active, position FROM '.MAIN_DB_PREFIX.'dolifarm_c_biostatus',
                'SELECT rowid, code, label, active, position FROM '.MAIN_DB_PREFIX.'dolifarm_c_machinetype',
                'SELECT rowid, code, label, active FROM '.MAIN_DB_PREFIX.'dolifarm_c_fueltype',
                'SELECT rowid, code, label, active, position FROM '.MAIN_DB_PREFIX.'dolifarm_c_operation_type' // [NUOVO]
            ),
            
            // Sort order
            'tabsqlsort' => array(
                'position ASC, code ASC',
                'position ASC, code ASC',
                'position ASC, code ASC',
                'code ASC',
                'position ASC, code ASC' // [NUOVO]
            ),
            
            // List of fields (result of select to show dictionary)
            'tabfield' => array(
                'code,label',
                'code,label,description',
                'code,label',
                'code,label',
                'code,label' // [NUOVO]
            ),
            
            // List of fields (list of fields to edit a record)
            'tabfieldvalue' => array(
                'code,label',
                'code,label,description',
                'code,label',
                'code,label',
                'code,label' // [NUOVO]
            ),
            
            // List of fields (list of fields for insert)
            'tabfieldinsert' => array(
                'code,label',
                'code,label,description',
                'code,label',
                'code,label',
                'code,label' // [NUOVO]
            ),
            
            // Name of columns with primary key (try to always name it 'rowid')
            'tabrowid' => array('rowid', 'rowid', 'rowid', 'rowid', 'rowid'),
            
            // Condition to show each dictionary (Always 1 if the module is active)
            'tabcond' => array(1, 1, 1, 1, 1),
            
            // Tooltip for every fields of dictionaries
            'tabhelp' => array(
                array('code' => $langs->trans('Code'), 'label' => $langs->trans('Label')),
                array('code' => $langs->trans('Code'), 'label' => $langs->trans('Label'), 'description' => $langs->trans('Description')),
                array('code' => $langs->trans('Code'), 'label' => $langs->trans('Label')),
                array('code' => $langs->trans('Code'), 'label' => $langs->trans('Label')),
                array('code' => $langs->trans('Code'), 'label' => $langs->trans('Label')) // [NUOVO]
            )
        );

        /* END MODULEBUILDER DICTIONARIES */

		// Boxes/Widgets
		// Add here list of php file(s) stored in dolifarm/core/boxes that contains a class to show a widget.
/* BEGIN MODULEBUILDER WIDGETS */
        $this->boxes = array(
            0 => array(
                'file' => 'dolifarm_TotDossiers.php@dolifarm',
                'note' => $langs->trans('BoxTotDossiersDescription'), 
                'enabledbydefaulton' => 'dolifarm',
            ),
            1 => array(
                'file' => 'dolifarm_TotPlots.php@dolifarm',
                'note' => $langs->trans('BoxTotPlotsDescription'),
                'enabledbydefaulton' => 'dolifarm',
            ),
            2 => array(
                'file' => 'dolifarm_TotMachines.php@dolifarm',
                'note' => $langs->trans('BoxTotMachinesDescription'),
                'enabledbydefaulton' => 'dolifarm',
            ),
            3 => array(
                'file' => 'dolifarm_CropPlansMonitoring.php@dolifarm',
                'note' => $langs->trans('BoxCropPlansMonitoringDescription'),
                'enabledbydefaulton' => 'dolifarm',
            ),
			 4 => array(
                'file' => 'dolifarmwidgettopplannedproducts.php@dolifarm',
                'note' => $langs->trans('BoxProductsPlanOverview'),
                'enabledbydefaulton' => 'dolifarm',
            ),
        );
        /* END MODULEBUILDER WIDGETS */

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		/* BEGIN MODULEBUILDER CRON */
		$this->cronjobs = array(
			//  0 => array(
			//      'label' => 'MyJob label',
			//      'jobtype' => 'method',
			//      'class' => '/dolifarm/class/dolifarmplot.class.php',
			//      'objectname' => 'DoliFarmPlot',
			//      'method' => 'doScheduledJob',
			//      'parameters' => '',
			//      'comment' => 'Comment',
			//      'frequency' => 2,
			//      'unitfrequency' => 3600,
			//      'status' => 0,
			//      'test' => 'isModEnabled("dolifarm")',
			//      'priority' => 50,
			//  ),
		);
		/* END MODULEBUILDER CRON */
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'isModEnabled("dolifarm")', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'isModEnabled("dolifarm")', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array(); 
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read DoliFarmPlot object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmplot';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update DoliFarmPlot object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmplot';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete DoliFarmPlot object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmplot';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (1 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read DoliFarmFarmDossier object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmfarmdossier';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (1 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update DoliFarmFarmDossier object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmfarmdossier';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (1 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete DoliFarmFarmDossier object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmfarmdossier';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (2 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read DoliFarmMachine object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmmachine';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (2 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update DoliFarmMachine object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmmachine';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (2 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete DoliFarmMachine object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmmachine';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (3 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read DoliFarmAgroDrug object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmagrodrug';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (3 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update DoliFarmAgroDrug object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmagrodrug';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (3 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete DoliFarmAgroDrug object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmagrodrug';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (4 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read DoliFarmCrops object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmcrops';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (4 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update DoliFarmCrops object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmcrops';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (4 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete DoliFarmCrops object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmcrops';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (5 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read DoliFarmEstimateCosts object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmestimatecosts';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (5 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update DoliFarmEstimateCosts object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmestimatecosts';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (5 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete DoliFarmEstimateCosts object of Dolifarm';
		$this->rights[$r][4] = 'dolifarmestimatecosts';
		$this->rights[$r][5] = 'delete';
		$r++;

		/* END MODULEBUILDER PERMISSIONS */


		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'Dolifarm',
			'prefix' => img_picto('', dolifarm_get_fa_icon('dolifarm'), 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => '',
			'url' => '/dolifarm/dolifarmindex.php',
			'langs' => 'dolifarm@dolifarm', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('dolifarm')", // Define condition to show or hide menu entry. Use "isModEnabled('dolifarm')" if entry must be visible if module is enabled (those quote marks are importants).
			'perms' => '1', // Use 'perms'=>'$user->hasRight("dolifarm", "dolifarmplot", "read")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU DOLIFARMFARMDOSSIER */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm',
			'type' => 'left',
			'titre' => 'DoliFarmFarmDossiers',
			'prefix' => img_picto('', dolifarm_get_fa_icon('farmdossier'), 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmfarmdossier',
			'url' => '/dolifarm/dolifarmfarmdossier_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmfarmdossier", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmFarmDossier'
		);
		/*
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmfarmdossier',
			'type' => 'left',
			'titre' => 'ListDoliFarmFarmDossiers',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_farmdossier_list',
			'url' => '/dolifarm/dolifarmfarmdossier_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmfarmdossier", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmFarmDossier'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmfarmdossier',
			'type' => 'left',
			'titre' => 'NewDoliFarmFarmDossier',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_farmdossier_new',
			'url' => '/dolifarm/dolifarmfarmdossier_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmfarmdossier", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmFarmDossier'
		);
		*/
		
		/* BEGIN MODULEBUILDER LEFTMENU DOLIFARMPLOT */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm',
			'type' => 'left',
			'titre' => 'DoliFarmPlots',
			'prefix' => img_picto('', dolifarm_get_fa_icon('plot'), 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmplot',
			'url' => '/dolifarm/dolifarmplot_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmplot", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmPlot'
		);
		/*
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmplot',
			'type' => 'left',
			'titre' => 'ListDoliFarmPlot',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_plot_list',
			'url' => '/dolifarm/dolifarmplot_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmplot", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmPlot'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmplot',
			'type' => 'left',
			'titre' => 'NewDoliFarmPlot',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_plot_new',
			'url' => '/dolifarm/dolifarmplot_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmplot", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmPlot'
		);
		 */
		/* BEGIN MODULEBUILDER LEFTMENU DOLIFARMMACHINE */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm',
			'type' => 'left',
			'titre' => 'DoliFarmMachines',
			'prefix' => img_picto('', dolifarm_get_fa_icon('machine'), 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmmachine',
			'url' => '/dolifarm/dolifarmmachine_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmmachine", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmMachine'
		);
		/*
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmmachine',
			'type' => 'left',
			'titre' => 'ListDoliFarmMachine',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_machine_list',
			'url' => '/dolifarm/dolifarmmachine_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmmachine", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmMachine'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmmachine',
			'type' => 'left',
			'titre' => 'NewDoliFarmMachine',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_machine_new',
			'url' => '/dolifarm/dolifarmmachine_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmmachine", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmMachine'
		);
		 */
		/* BEGIN MODULEBUILDER LEFTMENU DOLIFARMAGRODRUG */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm',
			'type' => 'left',
			'titre' => 'DoliFarmAgroDrug',
			'prefix' => img_picto('', dolifarm_get_fa_icon('agrodrug'), 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmagrodrug',
			'url' => '/dolifarm/dolifarmagrodrug_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmagrodrug", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmAgroDrug'
		);
		/*
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmagrodrug',
			'type' => 'left',
			'titre' => 'List DoliFarmAgroDrug',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_agrodrug_list',
			'url' => '/dolifarm/dolifarmagrodrug_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmagrodrug", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmAgroDrug'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmagrodrug',
			'type' => 'left',
			'titre' => 'New DoliFarmAgroDrug',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_agrodrug_new',
			'url' => '/dolifarm/dolifarmagrodrug_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmagrodrug", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmAgroDrug'
		);
		*/
		/* BEGIN MODULEBUILDER LEFTMENU DOLIFARMCROPS */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm',
			'type' => 'left',
			'titre' => 'DoliFarmCrops',
			'prefix' => img_picto('', dolifarm_get_fa_icon('crops'), 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmcrops',
			'url' => '/dolifarm/dolifarmcrops_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmcrops", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmCrops'
		);
		/*
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmcrops',
			'type' => 'left',
			'titre' => 'List DoliFarmCrops',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_crops_list',
			'url' => '/dolifarm/dolifarmcrops_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmcrops", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmCrops'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmcrops',
			'type' => 'left',
			'titre' => 'New DoliFarmCrops',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_crops_new',
			'url' => '/dolifarm/dolifarmcrops_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmcrops", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmCrops'
		);
		 */
		/* BEGIN MODULEBUILDER LEFTMENU DOLIFARMESTIMATECOSTS */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm',
			'type' => 'left',
			'titre' => 'DoliFarmEstimateCosts',
			'prefix' => img_picto('', dolifarm_get_fa_icon('estimatecosts'), 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmestimatecosts',
			'url' => '/dolifarm/dolifarmestimatecosts_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmestimatecosts", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmEstimateCosts'
		);
		/*
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmestimatecosts',
			'type' => 'left',
			'titre' => 'List DoliFarmEstimateCosts',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_estimatecosts_list',
			'url' => '/dolifarm/dolifarmestimatecosts_list.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmestimatecosts", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmEstimateCosts'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmestimatecosts',
			'type' => 'left',
			'titre' => 'New DoliFarmEstimateCosts',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_estimatecosts_new',
			'url' => '/dolifarm/dolifarmestimatecosts_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolifarm", "dolifarmestimatecosts", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'DoliFarmEstimateCosts'
		);
		 */
		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/*
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=dolifarm',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                          // This is a Left menu entry
			'titre' => 'DoliFarmPlot',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarmplot',
			'url' => '/dolifarm/dolifarmindex.php',
			'langs' => 'dolifarm@dolifarm',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('dolifarm')", // Define condition to show or hide menu entry. Use isModEnabled("dolifarm") if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("dolifarm", "dolifarmplot", "read")',
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'DoliFarmPlot'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmplot',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'New_DoliFarmPlot',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_plot_new',
			'url' => '/dolifarm/dolifarmplot_card.php?action=create',
			'langs' => 'dolifarm@dolifarm',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('dolifarm')", // Define condition to show or hide menu entry. Use isModEnabled("dolifarm") if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->hasRight("dolifarm", "dolifarmplot", "write")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'DoliFarmPlot'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=dolifarm,fk_leftmenu=dolifarmplot',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'List_DoliFarmPlot',
			'mainmenu' => 'dolifarm',
			'leftmenu' => 'dolifarm_plot_list',
			'url' => '/dolifarm/dolifarmplot_list.php',
			'langs' => 'dolifarm@dolifarm',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => "isModEnabled('dolifarm')", // Define condition to show or hide menu entry. Use isModEnabled("dolifarm") if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("dolifarm", "dolifarmplot", "read")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'DoliFarmPlot'
		);
		*/
		/* END MODULEBUILDER LEFTMENU MYOBJECT */


		// Exports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		/*
		$langs->load("dolifarm@dolifarm");
		$this->export_code[$r] = $this->rights_class.'_'.$r;
		$this->export_label[$r] = 'DoliFarmPlotLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r] = $this->picto;
		// Define $this->export_fields_array, $this->export_TypeFields_array and $this->export_entities_array
		$keyforclass = 'DoliFarmPlot'; $keyforclassfile='/dolifarm/class/dolifarmplot.class.php'; $keyforelement='dolifarmplot@dolifarm';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		//$this->export_fields_array[$r]['t.fieldtoadd']='FieldToAdd'; $this->export_TypeFields_array[$r]['t.fieldtoadd']='Text';
		//unset($this->export_fields_array[$r]['t.fieldtoremove']);
		//$keyforclass = 'DoliFarmPlotLine'; $keyforclassfile='/dolifarm/class/dolifarmplot.class.php'; $keyforelement='dolifarmplotline@dolifarm'; $keyforalias='tl';
		//include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		$keyforselect='dolifarmplot'; $keyforaliasextra='extra'; $keyforelement='dolifarmplot@dolifarm';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$keyforselect='dolifarmplotline'; $keyforaliasextra='extraline'; $keyforelement='dolifarmplotline@dolifarm';
		//include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$this->export_dependencies_array[$r] = array('dolifarmplotline' => array('tl.rowid','tl.ref')); // To force to activate one or several fields if we select some fields that need same (like to select a unique key if we ask a field of a child to avoid the DISTINCT to discard them, or for computed field than need several other fields)
		//$this->export_special_array[$r] = array('t.field' => '...');
		//$this->export_examplevalues_array[$r] = array('t.field' => 'Example');
		//$this->export_help_array[$r] = array('t.field' => 'FieldDescHelp');
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.$this->db->prefix().'dolifarm_plot as t';
		//$this->export_sql_end[$r]  .=' LEFT JOIN '.$this->db->prefix().'dolifarm_plot_line as tl ON tl.fk_dolifarmplot = t.rowid';
		$this->export_sql_end[$r] .=' WHERE 1 = 1';
		$this->export_sql_end[$r] .=' AND t.entity IN ('.getEntity('dolifarmplot').')';
		$r++; */
		/* END MODULEBUILDER EXPORT MYOBJECT */

		// Imports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/*
		$langs->load("dolifarm@dolifarm");
		$this->import_code[$r] = $this->rights_class.'_'.$r;
		$this->import_label[$r] = 'DoliFarmPlotLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->import_icon[$r] = $this->picto;
		$this->import_tables_array[$r] = array('t' => $this->db->prefix().'dolifarm_plot', 'extra' => $this->db->prefix().'dolifarm_plot_extrafields');
		$this->import_tables_creator_array[$r] = array('t' => 'fk_user_author'); // Fields to store import user id
		$import_sample = array();
		$keyforclass = 'DoliFarmPlot'; $keyforclassfile='/dolifarm/class/dolifarmplot.class.php'; $keyforelement='dolifarmplot@dolifarm';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinimport.inc.php';
		$import_extrafield_sample = array();
		$keyforselect='dolifarmplot'; $keyforaliasextra='extra'; $keyforelement='dolifarmplot@dolifarm';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinimport.inc.php';
		$this->import_fieldshidden_array[$r] = array('extra.fk_object' => 'lastrowid-'.$this->db->prefix().'dolifarm_plot');
		$this->import_regex_array[$r] = array();
		$this->import_examplevalues_array[$r] = array_merge($import_sample, $import_extrafield_sample);
		$this->import_updatekeys_array[$r] = array('t.ref' => 'Ref');
		$this->import_convertvalue_array[$r] = array(
			't.ref' => array(
				'rule'=>'getrefifauto',
				'class'=>(!getDolGlobalString('DOLIFARM_MYOBJECT_ADDON') ? 'mod_dolifarmplot_standard' : getDolGlobalString('DOLIFARM_MYOBJECT_ADDON')),
				'path'=>"/core/modules/dolifarm/".(!getDolGlobalString('DOLIFARM_MYOBJECT_ADDON') ? 'mod_dolifarmplot_standard' : getDolGlobalString('DOLIFARM_MYOBJECT_ADDON')).'.php',
				'classobject'=>'DoliFarmPlot',
				'pathobject'=>'/dolifarm/class/dolifarmplot.class.php',
			),
			't.fk_soc' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
			't.fk_user_valid' => array('rule' => 'fetchidfromref', 'file' => '/user/class/user.class.php', 'class' => 'User', 'method' => 'fetch', 'element' => 'user'),
			't.fk_mode_reglement' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/compta/paiement/class/cpaiement.class.php', 'class' => 'Cpaiement', 'method' => 'fetch', 'element' => 'cpayment'),
		);
		$this->import_run_sql_after_array[$r] = array();
		$r++; */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs, $user;

		// Create tables of module at module activation
		//$result = $this->_load_tables('/install/mysql/', 'dolifarm');
		$result = $this->_load_tables('/dolifarm/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extrafields during init
		//include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		//$extrafields = new ExtraFields($this->db);
		//$result0=$extrafields->addExtraField('dolifarm_separator1', "Separator 1", 'separator', 1,  0, 'thirdparty',   0, 0, '', array('options'=>array(1=>1)), 1, '', 1, 0, '', '', 'dolifarm@dolifarm', 'isModEnabled("dolifarm")');
		//$result1=$extrafields->addExtraField('dolifarm_myattr1', "New Attr 1 label", 'boolean', 1,  3, 'thirdparty',   0, 0, '', '', 1, '', -1, 0, '', '', 'dolifarm@dolifarm', 'isModEnabled("dolifarm")');
		//$result2=$extrafields->addExtraField('dolifarm_myattr2', "New Attr 2 label", 'varchar', 1, 10, 'project',      0, 0, '', '', 1, '', -1, 0, '', '', 'dolifarm@dolifarm', 'isModEnabled("dolifarm")');
		//$result3=$extrafields->addExtraField('dolifarm_myattr3', "New Attr 3 label", 'varchar', 1, 10, 'bank_account', 0, 0, '', '', 1, '', -1, 0, '', '', 'dolifarm@dolifarm', 'isModEnabled("dolifarm")');
		//$result4=$extrafields->addExtraField('dolifarm_myattr4', "New Attr 4 label", 'select',  1,  3, 'thirdparty',   0, 1, '', array('options'=>array('code1'=>'Val1','code2'=>'Val2','code3'=>'Val3')), 1,'', -1, 0, '', '', 'dolifarm@dolifarm', 'isModEnabled("dolifarm")');
		//$result5=$extrafields->addExtraField('dolifarm_myattr5', "New Attr 5 label", 'text',    1, 10, 'user',         0, 0, '', '', 1, '', -1, 0, '', '', 'dolifarm@dolifarm', 'isModEnabled("dolifarm")');

		// Permissions
		$this->remove($options);

		$sql = array();

		// Document templates
		$moduledir = dol_sanitizeFileName('dolifarm');
		$myTmpObjects = array();
		$myTmpObjects['DoliFarmPlot'] = array('includerefgeneration' => 0, 'includedocgeneration' => 0);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/'.$moduledir.'/template_dolifarmplots.odt';
				$dirodt = DOL_DATA_ROOT.($conf->entity > 1 ? '/'.$conf->entity : '').'/doctemplates/'.$moduledir;
				$dest = $dirodt.'/template_dolifarmplots.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, '0', 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, array(
					"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'standard_".strtolower($myTmpObjectKey)."' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('standard_".strtolower($myTmpObjectKey)."', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")",
					"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'generic_".strtolower($myTmpObjectKey)."_odt' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES('generic_".strtolower($myTmpObjectKey)."_odt', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")"
				));
			}
		}

		require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';

        // Definizione dei gruppi necessari
		// TODO DA MIGLIORRARE
        $groups_to_create = array(
            'DAP_AGRONOMI' => array(
                'name' => $langs->trans('Agronomists'),
                'note' => 'Gruppo per i tecnici agronomi abilitati alla compilazione tecnica degli Audit.'
            ),
            'DAP_VALUTATORI' => array(
                'name' => $langs->trans('Auditors'),
                'note' => 'Gruppo per i valutatori che validano i punteggi finali (Score Override).'
            ),
            'DAP_AGRICOLTORI' => array(
                'name' => $langs->trans('Farmers'),
                'note' => 'Gruppo per gli utenti esterni (aziende) per autovalutazione.'
            )
        );

        foreach ($groups_to_create as $key => $gdata) {
            // 1. Controllo se il gruppo esiste già (per nome)
            $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup WHERE nom = '".$this->db->escape($gdata['name'])."' AND entity = ".$conf->entity;
            $res_check = $this->db->query($sql_check);

            if ($res_check && $this->db->num_rows($res_check) == 0) {
                // 2. Il gruppo non esiste, lo creo
                $newGroup = new UserGroup($this->db);
                $newGroup->nom = $gdata['name'];
                $newGroup->note = $gdata['note'];
                $newGroup->entity = $conf->entity;

                // Create group
                $res_create = $newGroup->create($user);

                if ($res_create > 0) {
                    dol_syslog("DoliAgroPass::init Group created: " . $gdata['name'], LOG_INFO);
                    
                    // 3. [OPZIONALE] Assegnazione Permessi di Default al Gruppo
                    // Qui potresti assegnare automaticamente i permessi del modulo al gruppo appena creato
                    // Esempio: Agronomi hanno diritti di lettura/scrittura sugli Audit
                    /*
                    if ($key == 'DAP_AGRONOMI') {
                        $perm_rights = array('doliagropass_audit_read', 'doliagropass_audit_write');
                        foreach ($perm_rights as $perm) {
                            $newGroup->addrights($newGroup->id, 'doliagropass', $perm); 
                        }
                    }
                    */
                    
                } else {
                    $this->errors[] = "Failed to create group " . $gdata['name'] . ": " . $newGroup->error;
                    dol_syslog("DoliAgroPass::init Failed to create group: " . $newGroup->error, LOG_ERR);
                }
            }
        }

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
