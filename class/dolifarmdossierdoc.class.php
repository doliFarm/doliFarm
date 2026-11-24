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
 * \file    dolifarm/class/dolifarmdossierdoc.class.php
 * \ingroup dolifarm
 * \brief   Document of the paper dossier listed by the SIAN (identity document, CCIAA, cadastral survey...),
 *          with its expiry. Created by the import of the dossier; shown in the tab "SIAN" of the dossier.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Document of a farm dossier
 */
class DoliFarmDossierDoc extends CommonObject
{
	/** @var string */
	public $module = 'dolifarm';
	/** @var string */
	public $element = 'dolifarmdossierdoc';
	/** @var string */
	public $table_element = 'dolifarm_dossierdoc';
	/** @var string */
	public $picto = 'fa-file-contract';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	/** @var array<string,array<string,mixed>> */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1'),
		'ref' => array('type' => 'varchar(255)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 0),
		'label' => array('type' => 'varchar(255)', 'label' => 'DossierDocType', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'section' => array('type' => 'varchar(255)', 'label' => 'DossierDocSection', 'enabled' => 1, 'position' => 25, 'notnull' => 0, 'visible' => 1),
		'fk_dossier' => array('type' => 'integer:DoliFarmFarmDossier:dolifarm/class/dolifarmfarmdossier.class.php', 'label' => 'FarmDossier', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'protocol' => array('type' => 'varchar(64)', 'label' => 'DossierDocProtocol', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'date_signature' => array('type' => 'date', 'label' => 'DossierDocDateSignature', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'date_start' => array('type' => 'date', 'label' => 'DossierDocDateStart', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'date_end' => array('type' => 'date', 'label' => 'DossierDocDateEnd', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'status' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 0, 'default' => '1'),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => -2),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => -2),
	);

	/** @var int */
	public $rowid;
	/** @var int */
	public $entity;
	/** @var string */
	public $ref;
	/** @var string */
	public $label;
	/** @var string */
	public $section;
	/** @var int */
	public $fk_dossier;
	/** @var int */
	public $fk_soc;
	/** @var string */
	public $protocol;
	/** @var int|string|null */
	public $date_signature;
	/** @var int|string|null */
	public $date_start;
	/** @var int|string|null */
	public $date_end;
	/** @var int */
	public $status;
	/** @var string */
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
	 * Create in database
	 *
	 * @param	User	$user		User that creates
	 * @param	int		$notrigger	1 = no trigger
	 * @return	int					Id, <0 if KO
	 */
	public function create(User $user, $notrigger = 1)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load from database
	 *
	 * @param	int		$id		Id
	 * @return	int				>0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id)
	{
		return $this->fetchCommon($id);
	}

	/**
	 * Update in database
	 *
	 * @param	User	$user		User that modifies
	 * @param	int		$notrigger	1 = no trigger
	 * @return	int					>0 if OK, <0 if KO
	 */
	public function update(User $user, $notrigger = 1)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Link to the tab SIAN of its dossier
	 *
	 * @param	int		$withpicto	1 = with picto
	 * @return	string				Html
	 */
	public function getNomUrl($withpicto = 0)
	{
		global $langs;

		$url = dol_buildpath('/dolifarm/dolifarmfarmdossier_sian.php', 1).'?docid='.((int) $this->id);
		$text = dol_escape_htmltag((string) ($this->label ? $this->label : $this->ref));
		$title = $langs->trans('DossierDocType').': '.$text.($this->section ? '<br>'.$langs->trans('DossierDocSection').': '.dol_escape_htmltag((string) $this->section) : '');
		$out = '<a href="'.$url.'" class="classfortooltip" title="'.dol_escape_htmltag($title, 1).'">';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		return $out.$text.'</a>';
	}
}
