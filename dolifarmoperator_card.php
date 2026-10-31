<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026       SuperAdmin
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
 * \file       dolifarmoperator_card.php
 * \ingroup    dolifarm
 * \brief      Page to create/edit/view a person who carries out the treatments and fertilizations (operatore), with the license for the plant protection products
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/dolifarm/class/dolifarmoperator.class.php');
dol_include_once('/dolifarm/lib/dolifarm_operator.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_fields.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "companies"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel');
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize technical objects
$object = new DoliFarmOperator($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($object->element.'card', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'.

// Permissions
$permissiontoread = $user->hasRight('dolifarm', 'dolifarmoperator', 'read');
$permissiontoadd = $user->hasRight('dolifarm', 'dolifarmoperator', 'write'); // Used by the include of actions_addupdatedelete.inc.php
$permissiontodelete = $user->hasRight('dolifarm', 'dolifarmoperator', 'delete');
$permissionnote = $permissiontoadd;
$permissiondellink = $permissiontoadd;
$upload_dir = $conf->dolifarm->multidir_output[isset($object->entity) ? $object->entity : $conf->entity].'/dolifarmoperator';

// Security check
if (!isModEnabled('dolifarm')) {
	accessforbidden('Module dolifarm not enabled');
}
restrictedArea($user, $object->module, $object->id, $object->table_element, $object->element, 'fk_soc', 'rowid');
if (!$permissiontoread) {
	accessforbidden();
}

$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = dol_buildpath('/dolifarm/dolifarmoperator_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/dolifarm/dolifarmoperator_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	$triggermodname = $object->TRIGGER_PREFIX.'_MODIFY';

	// Actions cancel, add, update, update_extras, confirm_delete, confirm_close, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';
}


/*
 * View
 */

$form = new Form($db);

// Layout of the card: identification, then license for the plant protection products
$cardlayout = array(
	array(
		'left' => array('ref', 'label', 'tax_code', 'fk_soc'),
		'right' => array('fk_socpeople', 'fk_user'),
	),
	array(
		'title' => 'OperatorLicense',
		'picto' => 'fa-id-card',
		'left' => array('license_type', 'license_number', 'license_issuer'),
		'right' => array(array('label' => 'LicenseValidity', 'fields' => array('license_date', 'license_end'), 'prefixes' => array('FromDate', 'ToDate'))),
	),
);

$title = $langs->trans("DoliFarmOperator")." - ".$langs->trans('Card');
if ($action == 'create') {
	$title = $langs->trans("NewObject", $langs->transnoentitiesnoconv("DoliFarmOperator"));
}

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-card');

// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden('NotEnoughPermissions', 0, 1);
	}

	print load_fiche_titre($title, '', $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.dol_escape_htmltag($backtopageforcancel).'">';
	}

	print dol_get_fiche_head(array(), '');
	dolifarmPrintCardLayout($object, $cardlayout, 'create');
	dolifarmPrintThirdpartyFilter('fk_socpeople', 'socpeople', 'contact', false);
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");
	print '</form>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("DoliFarmOperator"), '', $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.dol_escape_htmltag($backtopageforcancel).'">';
	}

	print dol_get_fiche_head();
	dolifarmPrintCardLayout($object, $cardlayout, 'edit');
	dolifarmPrintThirdpartyFilter('fk_socpeople', 'socpeople', 'contact', false);
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();
	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$head = dolifarmoperatorPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("DoliFarmOperator"), -1, $object->picto, 0, '', '', 0, '', 1);

	$formconfirm = '';
	if ($action == 'delete' || ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile))) {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteDoliFarmOperator'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 'action-delete');
	}
	if ($action == 'close') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Disable'), $langs->trans('ConfirmDisableDoliFarmOperator', $object->ref), 'confirm_close', '', 'yes', 1);
	}

	$parameters = array('formConfirm' => $formconfirm);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}
	print $formconfirm;

	$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmoperator_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
	$morehtmlref = '<div class="refidno">'.dolifarmObjectBannerRef($object).'</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	dolifarmPrintCardLayout($object, $cardlayout, 'view');
	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	// Buttons for actions
	print '<div class="tabsAction">'."\n";
	$parameters = array();
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}
	if (empty($reshook)) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
		if ($object->status == $object::STATUS_VALIDATED) {
			print dolGetButtonAction('', $langs->trans('Disable'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=close&token='.newToken(), '', $permissiontoadd);
		} else {
			print dolGetButtonAction('', $langs->trans('Enable'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_reopen&confirm=yes&token='.newToken(), '', $permissiontoadd);
		}
		$deleteUrl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken();
		$buttonId = 'action-delete-no-ajax';
		if ($conf->use_javascript_ajax && empty($conf->dol_use_jmobile)) {
			$deleteUrl = '';
			$buttonId = 'action-delete';
		}
		print dolGetButtonAction('', $langs->trans("Delete"), 'delete', $deleteUrl, $buttonId, $permissiontodelete);
	}
	print '</div>'."\n";

	// Linked objects
	print '<div class="fichecenter"><div class="fichehalfleft">';
	$tmparray = $form->showLinkToObjectBlock($object, array(), array('dolifarmoperator'), 1);
	if (is_array($tmparray)) {
		print $tmparray['htmltoenteralink'];
		$form->showLinkedObjectBlock($object, $tmparray['linktoelem']);
	} else {
		$form->showLinkedObjectBlock($object, $tmparray);
	}
	print '</div></div>';
}

// End of page
llxFooter();
$db->close();
