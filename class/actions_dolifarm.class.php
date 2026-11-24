<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_dolifarm.class.php
 * \ingroup dolifarm
 * \brief   Hooks of doliFarm (home page of Dolibarr: farm group of the dashboard of open elements)
 */



require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsDolifarm
 */
class ActionsDolifarm extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();


	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Home page of Dolibarr: line of the expiries (licences, checks of the sprayers, authorisations...) in the dashboard
	 *
	 * @param	array<string,mixed>	$parameters	Parameters
	 * @param	object				$object		Not used
	 * @param	string				$action		Not used
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function addOpenElementsDashboardLine($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;

		$this->results = array();
		if (!isModEnabled('dolifarm') || !in_array('index', explode(':', $parameters['context']))) {
			return 0;
		}
		dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');
		require_once DOL_DOCUMENT_ROOT.'/core/class/workboardresponse.class.php';
		$langs->load('dolifarm@dolifarm');

		$all = dolifarmExpiryList($this->db, $user);
		$board = new WorkboardResponse();
		$board->img = img_picto('', 'fa-calendar-times');
		$board->label = $langs->trans('DashboardExpiries', dolifarmExpiryDays());
		$board->labelShort = $langs->trans('DashboardExpiriesShort');
		$board->url = dol_buildpath('/dolifarm/dolifarmindex.php', 1).'?mainmenu=dolifarm';
		$board->warning_delay = 0;
		$board->nbtodo = count($all);
		foreach ($all as $row) {
			if ($row['state'] == 'overdue') {
				$board->nbtodolate++;
			}
		}
		$this->results = array('dolifarm_expiry' => $board);
		return 0;
	}

	/**
	 * Home page of Dolibarr: group "Farm" of the dashboard of open elements
	 *
	 * @param	array<string,mixed>	$parameters	Parameters
	 * @param	object				$object		Not used
	 * @param	string				$action		Not used
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function addOpenElementsDashboardGroup($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array();
		if (!isModEnabled('dolifarm') || !in_array('index', explode(':', $parameters['context']))) {
			return 0;
		}
		$this->results = array(
			'dolifarm' => array(
				'lang' => 'dolifarm@dolifarm',
				'groupName' => 'DashboardFarm',
				'stats' => array('dolifarm_expiry'),
			),
		);
		return 0;
	}

	/**
	 * After a successful login: the page "Getting started" opens at the first page shown, unless the user turned it off
	 *
	 * The redirect is not done here, because the login transaction is committed after this hook.
	 *
	 * @param	array<string,mixed>	$parameters	Hook metadatas (context, etc...)
	 * @param	User				$object		The user who logged in
	 * @param	?string				$action		Current action
	 * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int									0
	 */
	public function afterLogin($parameters, &$object, &$action, $hookmanager)
	{
		dol_include_once('/dolifarm/lib/dolifarm_start.lib.php');
		if ($object instanceof User && dolifarmStartAtLogin($object, false)) {
			$_SESSION['dolifarm_start_pending'] = 1;
		}
		return 0;
	}

	/**
	 * Before the header of the first page after the login: open the page "Getting started"
	 *
	 * @param	array<string,mixed>	$parameters	Hook metadatas (context, etc...)
	 * @param	?CommonObject		$object		Not used
	 * @param	?string				$action		Current action
	 * @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int									0
	 */
	public function llxHeader($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		if (empty($_SESSION['dolifarm_start_pending'])) {
			return 0;
		}
		dol_include_once('/dolifarm/lib/dolifarm_start.lib.php');
		dol_include_once('/dolifarm/lib/dolifarm_area.lib.php');

		// Help me or the area of doliFarm, as chosen in the setup (DOLIFARM_LOGIN_PAGE)
		$startpage = dolifarmLoginPageUrl($this->db, $user);
		if (!($user instanceof User) || !dolifarmStartAtLogin($user) || (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] === $startpage)) {
			unset($_SESSION['dolifarm_start_pending']);
			return 0;
		}
		// Not on a form submission (except the login form itself), a popup or a page without header: wait for the next page
		$islogin = (GETPOST('actionlogin', 'aZ09') === 'login');
		if (($_SERVER['REQUEST_METHOD'] !== 'GET' && !$islogin) || headers_sent() || GETPOST('dol_openinpopup', 'aZ09') || !empty($parameters['disablehead'])) {
			return 0;
		}
		unset($_SESSION['dolifarm_start_pending']);
		header('Location: '.$startpage);
		exit;
	}

	/**
	 * Left menu of the suite: only the section of the current page (Farm dossier, Field notebook, Field data) shows its entries
	 *
	 * The three sections are the entries of level 0 of the top menu "dolifarm" (doliFarm, doliTrace and doliField);
	 * the section of a page is the module of its folder. On the other pages, "Help me" and the area of doliFarm included, the sections are closed.
	 *
	 * @param	array<string,mixed>				$parameters	Hook metadatas, with 'mainmenu'
	 * @param	array<int,array<string,mixed>>	$object		Entries of the left menu
	 * @param	?string							$action		Current action
	 * @param	HookManager						$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int											0 if nothing to do, 1 to replace the entries by $this->results
	 */
	public function menuLeftMenuItems($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array();	// With 0, Dolibarr would add the results of a previous hook as a menu entry
		if (($parameters['mainmenu'] ?? '') !== 'dolifarm' || !is_array($object) || getDolGlobalInt('DOLIFARM_MENU_ALL_OPEN')) {
			return 0;
		}
		$sections = array('dolifarm' => 'dolifarmfarmdossier', 'dolitrace' => 'dolitrace', 'dolifield' => 'dolifield');
		$current = '';
		if (preg_match('#/(dolifarm|dolitrace|dolifield)/([^/]+)$#', (string) ($_SERVER['PHP_SELF'] ?? ''), $reg) && !in_array($reg[2], array('dolifarm_start.php', 'dolifarmindex.php'), true)) {
			$current = $sections[$reg[1]];
		}
		$entries = array();
		$open = true;
		foreach ($object as $entry) {
			if (empty($entry['level'])) {
				$open = !in_array((string) ($entry['leftmenu'] ?? ''), $sections, true) || $entry['leftmenu'] === $current;
				$entries[] = $entry;
			} elseif ($open) {
				$entries[] = $entry;
			}
		}
		if (count($entries) == count($object)) {
			return 0;
		}
		$this->results = $entries;
		return 1;
	}
}
