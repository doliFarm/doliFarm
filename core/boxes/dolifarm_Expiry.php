<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    core/boxes/dolifarm_Expiry.php
 * \ingroup dolifarm
 * \brief   Widget with the overdue and near expiry dates of the farm
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Widget of the expiry dates
 */
class dolifarm_Expiry extends ModeleBoxes
{
	public $boxcode = "dolifarmboxExpiry";
	public $boximg = "fa-calendar-times";
	public $boxlabel = 'BoxExpiry';
	public $lang = 'dolifarm@dolifarm';
	public $depends = array('dolifarm');
	public $widgettype = '';

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	string	$param		More parameters
	 */
	public function __construct(DoliDB $db, $param = '')
	{
		parent::__construct($db, $param);
		$this->param = $param;
	}

	/**
	 * Load data into info_box_contents array to show array later
	 *
	 * @param	int		$max	Maximum number of records to load
	 * @return	void
	 */
	public function loadBox($max = 10)
	{
		global $user, $langs;

		$langs->load('dolifarm@dolifarm');
		dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');

		$this->max = $max;
		$all = dolifarmExpiryList($this->db, $user);
		$nboverdue = 0;
		foreach ($all as $row) {
			if ($row['state'] == 'overdue') {
				$nboverdue++;
			}
		}

		$this->info_box_head = array(
			'text' => $langs->trans("BoxExpiryTitle", dolifarmExpiryDays()).' ('.count($all).')'.($nboverdue ? ' '.dolGetBadge($langs->trans('ExpiryNbOverdue', $nboverdue), '', 'danger') : ''),
			'limit' => 0,
			'graph' => 0,
		);

		$this->info_box_contents = array();
		if (empty($all)) {
			$this->info_box_contents[0][] = array('td' => 'class="center"', 'text' => '<span class="opacitymedium">'.$langs->trans("ExpiryNone", dolifarmExpiryDays()).'</span>', 'asis' => 1);
			return;
		}

		$objects = array();
		$socs = array();
		$i = 0;
		foreach (array_slice($all, 0, $max) as $row) {
			$def = $row['def'];
			if (!isset($objects[$def['class']])) {
				if ($def['element'] != 'societe') {
					dol_include_once('/dolifarm/class/'.$def['element'].'.class.php');
				}
				$objects[$def['class']] = new $def['class']($this->db);
			}
			$object = $objects[$def['class']];
			$object->id = $row['id'];
			$object->ref = $row['ref'];
			$object->label = $row['label'];
			if ($def['element'] == 'societe') {
				// Third party: its name, and the certificate as label
				$object->name = $row['ref'];
				$object->nom = $row['ref'];
			}

			$sochtml = '';
			if ($row['fk_soc'] > 0) {
				if (!isset($socs[$row['fk_soc']])) {
					$socs[$row['fk_soc']] = new Societe($this->db);
					$socs[$row['fk_soc']]->fetch($row['fk_soc']);
				}
				$sochtml = $socs[$row['fk_soc']]->getNomUrl(1, '', 20);
			}

			$this->info_box_contents[$i][] = array('td' => 'class="nowraponall"', 'text' => dol_print_date($row['date'], 'day'));
			$this->info_box_contents[$i][] = array('td' => 'class="tdoverflowmax150"', 'text' => $langs->trans($def['label']));
			$this->info_box_contents[$i][] = array('td' => 'class="tdoverflowmax150"', 'text' => $object->getNomUrl(1), 'asis' => 1);
			$this->info_box_contents[$i][] = array('td' => 'class="tdoverflowmax150"', 'text' => $sochtml, 'asis' => 1);
			$this->info_box_contents[$i][] = array('td' => 'class="right nowraponall"', 'text' => dolifarmExpiryBadge($row['date'], $def['milestone']), 'asis' => 1);
			$i++;
		}
		if (count($all) > $max) {
			$this->info_box_contents[$i][] = array('td' => 'colspan="5" class="center"', 'text' => '<span class="opacitymedium">'.$langs->trans("ExpiryMore", count($all) - $max).'</span>', 'asis' => 1);
		}
	}

	/**
	 * Method to show box
	 *
	 * @param	?array<array{text?:string,sublink?:string,subpicto:?string,nbcol?:int,limit?:int,subclass?:string,graph?:int,target?:string}>	$head		Array with properties of box title
	 * @param	?array<array<array{tr?:string,td?:string,target?:string,text?:string,text2?:string,textnoformat?:string,tooltip?:string,logo?:string,url?:string,maxlength?:int,asis?:int,asis2?:int}>>	$contents	Array with properties of box lines
	 * @param	int<0,1>	$nooutput	No print, only return string
	 * @return	string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
