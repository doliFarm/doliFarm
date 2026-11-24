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
 * \file    core/boxes/dolifarm_AreaExternal.php
 * \ingroup dolifarm
 * \brief   Widget with the state of the automatic update of the external databases
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Widget with the state of the automatic update of the external databases
 */
class dolifarm_AreaExternal extends ModeleBoxes
{
	public $boxcode = "dolifarmboxAreaExternal";
	public $boximg = "fa-sync-alt";
	public $boxlabel = 'BoxAreaExternal';
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
		$this->info_box_head = array('text' => $langs->trans('AreaBoxExternal'), 'sublink' => $url, 'subpicto' => 'fa-external-link-alt', 'limit' => 0, 'graph' => 0);
		$i = 0;
		foreach (dolifarmAreaExternal($this->db, $user, $langs) as $row) {
			$this->info_box_contents[$i][] = array('td' => 'class="tdoverflowmax200"', 'text' => $row['source'], 'tooltip' => $row['message']);
			$this->info_box_contents[$i][] = array('td' => 'class="center nowraponall"', 'text' => ($row['lastrun'] ? dol_print_date($row['lastrun'], 'dayhour') : $langs->trans('AreaExtNever')));
			$this->info_box_contents[$i][] = array('td' => 'class="right nowraponall"', 'text' => dolifarmAreaBadge($row['state'], $langs), 'asis' => 1);
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
