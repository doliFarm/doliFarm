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
 * \file    core/boxes/dolifarm_AreaAlerts.php
 * \ingroup dolifarm
 * \brief   Widget with the alerts of the farms (revoked products in stock, stock to regularize, old drafts, weather stations)
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Widget with the alerts of the farms (revoked products in stock, stock to regularize, old drafts, weather stations)
 */
class dolifarm_AreaAlerts extends ModeleBoxes
{
	public $boxcode = "dolifarmboxAreaAlerts";
	public $boximg = "fa-exclamation-triangle";
	public $boxlabel = 'BoxAreaAlerts';
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
		global $user;

		parent::__construct($db, $param);
		$this->param = $param;
		$this->hidden = !$user->hasRight('dolifarm', 'dolifarmfarmdossier', 'read');
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
		dol_include_once('/dolifarm/lib/dolifarm_area.lib.php');
		$this->max = $max;
		$this->info_box_contents = array();
		$url = dol_buildpath('/dolifarm/dolifarmindex.php', 1).'?mainmenu=dolifarm';
		$alerts = dolifarmAreaAlerts($this->db, $user, $langs, array_keys(dolifarmAreaFarms($this->db, $user)));
		$this->info_box_head = array('text' => $langs->trans('AreaBoxAlerts').' ('.count($alerts).')', 'sublink' => $url, 'subpicto' => 'fa-external-link-alt', 'limit' => 0, 'graph' => 0);
		if (empty($alerts)) {
			$this->info_box_contents[0][] = array('td' => 'class="center"', 'text' => '<span class="opacitymedium">'.$langs->trans('AreaAlertNone').'</span>', 'asis' => 1);
			return;
		}
		$i = 0;
		foreach (array_slice($alerts, 0, $max) as $alert) {
			$icon = ($alert[0] == 'error' ? img_picto('', 'fa-times-circle', 'class="pictofixedwidth" style="color: #c62828"') : img_picto('', 'fa-exclamation-circle', 'class="pictofixedwidth" style="color: #ef6c00"'));
			$this->info_box_contents[$i][] = array('td' => '', 'text' => $icon.dolifarmAreaItemHtml(array($alert[1], $alert[2], $alert[3])), 'asis' => 1);
			$i++;
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
