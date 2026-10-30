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
 * \file        class/dolifarmobjecttrait.class.php
 * \ingroup     dolifarm
 * \brief       Methods shared by the simple objects of the farm dossier (livestock, buildings)
 *
 * The objects have no validation step: the final ref is given at creation, the status is only
 * Enabled / Disabled. The class must define checkData() and getObjectLabel().
 */

/**
 * Shared methods of the objects linked to a third party and a farm dossier
 */
trait DoliFarmObjectTrait
{
	/**
	 * Adjust the definition of the fields (call it from the constructor)
	 *
	 * @return	void
	 */
	protected function initFieldsDefinition()
	{
		global $langs;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database
	 *
	 * @param	User		$user		User that creates
	 * @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,max>				Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (!isset($this->entity)) {
			$this->entity = $conf->entity;
		}
		if (!isset($this->status) || $this->status === '') {
			$this->status = self::STATUS_VALIDATED;
		}
		if ($this->checkData() < 0) {
			return -1;
		}
		// No validation step: the final ref is given at creation
		if (empty($this->ref) || preg_match('/^[\(]?PROV/i', $this->ref)) {
			$this->ref = $this->getNextNumRef();
			if ($this->ref == '') {
				return -1;
			}
		}
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param	int			$id				Id object
	 * @param	string		$ref			Ref
	 * @param	int<0,1>	$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @return	int<-1,1>					Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $noextrafields = 0)
	{
		return $this->fetchCommon($id, $ref, '', $noextrafields);
	}

	/**
	 * Update object into database
	 *
	 * @param	User		$user		User that modifies
	 * @param	int<0,1>	$notrigger	0=launch triggers after, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		if ($this->checkData() < 0) {
			return -1;
		}
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param	User		$user		User that deletes
	 * @param	int<0,1>	$notrigger	0=launch triggers, 1=disable triggers
	 * @return	int<-1,1>				Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Set the object as disabled (action confirm_close of the card)
	 *
	 * @param	User		$user		User making the change
	 * @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 * @return	int						Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function cancel($user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_CANCELED) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, $this->TRIGGER_PREFIX.'_CANCEL');
	}

	/**
	 * Set the object as enabled again (action confirm_reopen of the card)
	 *
	 * @param	User		$user		User making the change
	 * @param	int<0,1>	$notrigger	1=Does not execute triggers, 0=Execute triggers
	 * @return	int						Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function reopen($user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_VALIDATED) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, $this->TRIGGER_PREFIX.'_REOPEN');
	}

	/**
	 * Check that the dossier belongs to the third party of the object
	 *
	 * @return	int		Return integer <0 if KO, >0 if OK
	 */
	public function checkDossierOfThirdparty()
	{
		global $langs;

		if (!empty($this->fk_dossier) && !empty($this->fk_soc)) {
			$sql = "SELECT fk_soc FROM ".$this->db->prefix()."dolifarm_farmdossier WHERE rowid = ".((int) $this->fk_dossier);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->fk_soc != (int) $this->fk_soc) {
				$langs->load('dolifarm@dolifarm');
				$this->error = $langs->trans('ErrorDossierOfAnotherThirdParty');
				return -1;
			}
		}
		return 1;
	}

	/**
	 * Return the next free ref, from the numbering module of the object (constant DOLIFARM_<ELEMENT>_ADDON)
	 *
	 * @return	string		Free ref, '' if KO
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;

		$langs->load("dolifarm@dolifarm");
		$addon = getDolGlobalString('DOLIFARM_'.strtoupper($this->element).'_ADDON', 'mod_'.$this->element.'_standard');

		$found = false;
		$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
		foreach ($dirmodels as $reldir) {
			$found = $found || @include_once dol_buildpath($reldir."core/modules/dolifarm/").$addon.".php";
		}
		if (!$found || !class_exists($addon)) {
			$this->error = $langs->trans("Error")." ".$langs->trans("ClassNotFound").' '.$addon;
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return '';
		}

		$obj = new $addon();
		$numref = $obj->getNextValue($this);
		if ($numref != '' && $numref != '-1') {
			return $numref;
		}
		$this->error = $obj->error;
		return '';
	}

	/**
	 * Tooltip of the link to the object
	 *
	 * @param	array<string,string>	$params		Params to construct tooltip data
	 * @return	array<string,string>
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$datas = array();
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans($this->getObjectLabel()).'</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' '.$this->getLibStatut(5);
		}
		$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag((string) $this->ref);
		if (!empty($this->label)) {
			$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.dol_escape_htmltag((string) $this->label);
		}
		return $datas;
	}

	/**
	 * Return a link to the object card (with optionally the picto)
	 *
	 * @param	int		$withpicto				Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 * @param	string	$option					'nolink' or ''
	 * @param	int		$notooltip				1=Disable tooltip
	 * @param	string	$morecss				Add more css on link
	 * @param	int		$save_lastsearch_value	-1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 * @return	string							String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $hookmanager, $action;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1;
		}

		$label = implode($this->getTooltipContentArray(array()));

		$query = array('id' => $this->id);
		if ($option !== 'nolink') {
			if ($save_lastsearch_value == 1 || ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"]))) {
				$query['save_lastsearch_values'] = 1;
			}
		}
		$url = dolBuildUrl(dol_buildpath('/dolifarm/'.$this->element.'_card.php', 1), $query);

		if (empty($notooltip)) {
			$linkclose = ' title="'.dolPrintHTMLForAttribute($label).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}
		$linkstart = ($option == 'nolink' ? '<span' : '<a href="'.$url.'"').$linkclose.'>';
		$linkend = ($option == 'nolink' ? '</span>' : '</a>');

		$result = $linkstart;
		if ($withpicto) {
			$result .= img_object(($notooltip ? '' : $label), $this->picto, (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
		}
		if ($withpicto != 2) {
			$result .= dol_escape_htmltag((string) $this->ref);
		}
		$result .= $linkend;

		if (is_object($hookmanager)) {
			$hookmanager->initHooks(array($this->element.'dao'));
			$parameters = array('id' => $this->id, 'getnomurl' => &$result);
			$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action);
			if ($reshook > 0) {
				$result = $hookmanager->resPrint;
			} else {
				$result .= $hookmanager->resPrint;
			}
		}

		return $result;
	}

	/**
	 * Return the label of the status
	 *
	 * @param	int<0,6>	$mode	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return	string				Label of status
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return the label of the status
	 *
	 * @param	int<0,6>	$mode	0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return	string				Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return the label of a given status
	 *
	 * @param	int			$status		Id status
	 * @param	int<0,6>	$mode		0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return	string					Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		global $langs;

		if (is_null($status) || $status === '') {
			return '';
		}
		$status = (int) $status;
		if ($status == self::STATUS_CANCELED) {
			$label = $langs->transnoentitiesnoconv('Disabled');
			$statustype = 'status6';
		} elseif ($status == self::STATUS_VALIDATED) {
			$label = $langs->transnoentitiesnoconv('Enabled');
			$statustype = 'status4';
		} else {
			$label = $langs->transnoentitiesnoconv('Draft');
			$statustype = 'status0';
		}
		$paramsbadge = array('badgeParams' => array('attr' => array('data-status-element' => $this->element, 'data-status' => $status)));

		return dolGetStatus($label, $label, '', $statustype, $mode, '', $paramsbadge);
	}

	/**
	 * Load the creation and modification information of the object
	 *
	 * @param	int		$id		Id of object
	 * @return	void
	 */
	public function info($id)
	{
		$sql = "SELECT t.rowid, t.date_creation as datec, t.tms as datem, t.fk_user_creat, t.fk_user_modif";
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.rowid = ".((int) $id);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				$this->id = $obj->rowid;
				$this->user_creation_id = $obj->fk_user_creat;
				$this->user_modification_id = $obj->fk_user_modif;
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
			}
			$this->db->free($resql);
		} else {
			dol_print_error($this->db);
		}
	}
}
