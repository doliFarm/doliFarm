<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       dolifarmagrodrug_uses.php
 *  \ingroup    dolifarm
 *  \brief      Tab of the authorised uses (impieghi autorizzati) of a plant protection product
 *
 * The uses are typed from the label: crop (EPPO code), pest (EPPO code, empty for every pest of the label),
 * dose range, pre-harvest interval and maximum number of applications on the crop. doliTrace compares its records with them.
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
dol_include_once('/dolifarm/class/dolifarmagrodrug.class.php');
dol_include_once('/dolifarm/class/dolifarmagrodruguse.class.php');
dol_include_once('/dolifarm/lib/dolifarm_agrodrug.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_eppo.lib.php');

$langs->loadLangs(array("dolifarm@dolifarm", "other"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$lineid = GETPOSTINT('lineid');

$object = new DoliFarmAgroDrug($db);
$hookmanager->initHooks(array($object->element.'uses', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'

$permissiontoread = $user->hasRight('dolifarm', 'dolifarmagrodrug', 'read');
$permissiontoadd = $user->hasRight('dolifarm', 'dolifarmagrodrug', 'write');

if (!isModEnabled('dolifarm') || !$permissiontoread) {
	accessforbidden();
}
if (empty($object->id)) {
	accessforbidden('', 0, 0, 1);
}
// Entity of the product
if (!empty($object->entity) && !in_array((int) $object->entity, array_map('intval', explode(',', getEntity($object->element))))) {
	accessforbidden();
}

$withtrace = isModEnabled('dolitrace');
$selfurl = dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id]);

/**
 * Crops of the farm with an EPPO code: code => label
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Languages
 * @return	array<string,string>
 */
function dolifarmAgroDrugUseCrops($db, $langs)
{
	$crops = array();
	$sql = "SELECT eppo_code, label FROM ".$db->prefix()."dolifarm_crops";
	$sql .= " WHERE entity IN (".getEntity('dolifarmcrops').") AND eppo_code IS NOT NULL AND eppo_code <> ''";
	$sql .= " ORDER BY label";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$code = strtoupper($obj->eppo_code);
			if (!isset($crops[$code])) {
				$crops[$code] = $langs->trans($obj->label);
			}
		}
		$db->free($resql);
	}
	asort($crops);
	return $crops;
}

/**
 * Rows of a dictionary of doliTrace: code => label
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	Query returning code and label
 * @return	array<string,string>
 */
function dolifarmAgroDrugUseDictionary($db, $sql)
{
	$rows = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rows[(string) $obj->code] = (string) $obj->label;
		}
		$db->free($resql);
	}
	return $rows;
}

$crops = dolifarmAgroDrugUseCrops($db, $langs);
$adversities = array();
$doseunits = array();
if ($withtrace) {
	$adversities = dolifarmAgroDrugUseDictionary($db, "SELECT code, label FROM ".$db->prefix()."dolitrace_c_adversity WHERE active = 1 ORDER BY label");
	// Units of the doses of plant protection products (kind 0 = all, 1 = plant protection)
	$doseunits = dolifarmAgroDrugUseDictionary($db, "SELECT code, label FROM ".$db->prefix()."dolitrace_c_dose_unit WHERE active = 1 AND kind IN (0, 1) ORDER BY position, label");
}


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook) && $permissiontoadd) {
	if ($cancel) {
		$action = '';
	}

	if ($action == 'add' || ($action == 'update' && $lineid > 0)) {
		$use = new DoliFarmAgroDrugUse($db);
		if ($action == 'update' && ($use->fetch($lineid) <= 0 || (int) $use->fk_agrodrug != (int) $object->id)) {
			accessforbidden();
		}
		$use->fk_agrodrug = $object->id;
		$use->crop_eppo = GETPOST('crop_eppo', 'alphanohtml');
		$use->crop_label = GETPOST('crop_label', 'alphanohtml');
		$use->adversity_code = GETPOST('adversity_code', 'alphanohtml');
		$use->adversity_label = GETPOST('adversity_label', 'alphanohtml');
		$use->dose_min = (GETPOST('dose_min', 'alpha') === '' ? null : price2num(GETPOST('dose_min', 'alpha')));
		$use->dose_max = (GETPOST('dose_max', 'alpha') === '' ? null : price2num(GETPOST('dose_max', 'alpha')));
		$use->dose_unit = (GETPOST('dose_unit', 'aZ09') == '-1' ? '' : GETPOST('dose_unit', 'aZ09'));
		$use->safety_days = (GETPOST('safety_days', 'alpha') === '' ? null : GETPOSTINT('safety_days'));
		$use->max_applications = (GETPOST('max_applications', 'alpha') === '' ? null : GETPOSTINT('max_applications'));
		$use->note = GETPOST('note', 'alphanohtml');
		// Labels from the crops of the farm and the dictionary of the pests, when left empty
		$code = strtoupper(trim((string) $use->crop_eppo));
		if (trim((string) $use->crop_label) === '' && isset($crops[$code])) {
			$use->crop_label = $crops[$code];
		}
		$code = strtoupper(trim((string) $use->adversity_code));
		if (trim((string) $use->adversity_label) === '' && isset($adversities[$code])) {
			$use->adversity_label = $adversities[$code];
		}

		$result = ($action == 'add' ? $use->create($user) : $use->update($user));
		if ($result > 0) {
			header('Location: '.$selfurl);
			exit;
		}
		setEventMessages($use->error, $use->errors, 'errors');
		$action = ($action == 'add' ? '' : 'edit');
	}

	if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes' && $lineid > 0) {
		$use = new DoliFarmAgroDrugUse($db);
		if ($use->fetch($lineid) > 0 && (int) $use->fk_agrodrug == (int) $object->id) {
			if ($use->delete($user) > 0) {
				setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
				header('Location: '.$selfurl);
				exit;
			}
			setEventMessages($use->error, $use->errors, 'errors');
		}
		$action = '';
	}
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('DoliFarmAgroDrug').' - '.$langs->trans('AgroDrugUses');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-card_uses');

$head = dolifarmagrodrugPrepareHead($object);
print dol_get_fiche_head($head, 'uses', $langs->trans("DoliFarmAgroDrug"), -1, $object->picto);

if ($action == 'delete' && $lineid > 0) {
	print $form->formconfirm(dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id, 'lineid' => $lineid]), $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_delete', '', 0, 1);
}

$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmagrodrug_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<div class="opacitymedium margintoponly marginbottomonly">'.$langs->trans('AgroDrugUsesHelp').'</div>';

// Lists for the inputs: crops of the farm and pests of the dictionary
print '<datalist id="dolifarm_use_crops">';
foreach ($crops as $code => $label) {
	print '<option value="'.dol_escape_htmltag($code).'">'.dol_escape_htmltag($label).'</option>';
}
print '</datalist>';
print '<datalist id="dolifarm_use_adversities">';
foreach ($adversities as $code => $label) {
	print '<option value="'.dol_escape_htmltag($code).'">'.dol_escape_htmltag($label).'</option>';
}
print '</datalist>';

/**
 * Inputs of a use, in the cells of a row of the table
 *
 * @param	DoliFarmAgroDrugUse|stdClass|null	$use		Use to edit, null for a new one
 * @param	array<string,string>				$doseunits	Units of the dictionary of doliTrace (empty: free text)
 * @param	Form								$form		Form
 * @return	string
 */
function dolifarmAgroDrugUseInputs($use, $doseunits, $form)
{
	global $langs;

	$val = function ($field) use ($use) {
		$post = GETPOST($field, 'alphanohtml');
		if ($post !== '') {
			return $post;
		}
		if ($use && isset($use->$field) && $use->$field !== null) {
			return in_array($field, array('dose_min', 'dose_max')) ? price2num($use->$field) : (string) $use->$field;
		}
		return '';
	};
	global $db, $conf;
	if (dolifarmEppoDataAvailable() && !empty($conf->use_javascript_ajax)) {
		// Search on the whole EPPO database; the label is optional (EPPO name when empty)
		$out = '<td>'.dolifarmEppoSelect($db, 'crop_eppo', $val('crop_eppo'), 'crop', 'minwidth200 maxwidth250');
		$out .= '<br><input type="text" name="crop_label" class="minwidth100 maxwidth200" maxlength="255" value="'.dol_escape_htmltag($val('crop_label')).'" placeholder="'.dol_escape_htmltag($langs->trans('AgroDrugUseCropLabel')).'"></td>';
		$out .= '<td>'.dolifarmEppoSelect($db, 'adversity_code', $val('adversity_code'), 'pest', 'minwidth200 maxwidth250');
		$out .= '<br><input type="text" name="adversity_label" class="minwidth100 maxwidth200" maxlength="255" value="'.dol_escape_htmltag($val('adversity_label')).'" placeholder="'.dol_escape_htmltag($langs->trans('AgroDrugUseAdversityLabel')).'"></td>';
		return $out.dolifarmAgroDrugUseOtherInputs($val, $doseunits, $form);
	}
	$out = '<td><input type="text" name="crop_eppo" list="dolifarm_use_crops" class="maxwidth75" maxlength="10" value="'.dol_escape_htmltag($val('crop_eppo')).'" placeholder="'.dol_escape_htmltag($langs->trans('AgroDrugUseCropCode')).'" required>';
	$out .= ' <input type="text" name="crop_label" class="minwidth100 maxwidth150" maxlength="255" value="'.dol_escape_htmltag($val('crop_label')).'" placeholder="'.dol_escape_htmltag($langs->trans('AgroDrugUseCropLabel')).'"></td>';
	$out .= '<td><input type="text" name="adversity_code" list="dolifarm_use_adversities" class="maxwidth75" maxlength="10" value="'.dol_escape_htmltag($val('adversity_code')).'" placeholder="'.dol_escape_htmltag($langs->trans('AgroDrugUseAdversityCode')).'">';
	$out .= ' <input type="text" name="adversity_label" class="minwidth100 maxwidth150" maxlength="255" value="'.dol_escape_htmltag($val('adversity_label')).'" placeholder="'.dol_escape_htmltag($langs->trans('AgroDrugUseAdversityLabel')).'"></td>';
	return $out.dolifarmAgroDrugUseOtherInputs($val, $doseunits, $form);
}

/**
 * Inputs of a use after crop and pest: dose, safety days, applications, note
 *
 * @param	callable				$val		Value of a field
 * @param	array<string,string>	$doseunits	Units of the dictionary of doliTrace (empty: free text)
 * @param	Form					$form		Form
 * @return	string
 */
function dolifarmAgroDrugUseOtherInputs($val, $doseunits, $form)
{
	$out = '';
	$out .= '<td class="nowraponall"><input type="text" name="dose_min" class="width50 right" value="'.dol_escape_htmltag($val('dose_min')).'">';
	$out .= ' - <input type="text" name="dose_max" class="width50 right" value="'.dol_escape_htmltag($val('dose_max')).'"> ';
	if (count($doseunits)) {
		$out .= $form->selectarray('dose_unit', $doseunits, $val('dose_unit'), 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
	} else {
		$out .= '<input type="text" name="dose_unit" class="width75" maxlength="16" value="'.dol_escape_htmltag($val('dose_unit')).'">';
	}
	$out .= '</td>';
	$out .= '<td class="center"><input type="number" min="0" step="1" name="safety_days" class="width50" value="'.dol_escape_htmltag($val('safety_days')).'"></td>';
	$out .= '<td class="center"><input type="number" min="0" step="1" name="max_applications" class="width50" value="'.dol_escape_htmltag($val('max_applications')).'"></td>';
	$out .= '<td><input type="text" name="note" class="minwidth100" maxlength="255" value="'.dol_escape_htmltag($val('note')).'"></td>';
	return $out;
}

$uses = DoliFarmAgroDrugUse::fetchAllForAgroDrug($db, $object->id);

print '<form method="POST" action="'.$selfurl.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
if ($action == 'edit' && $lineid > 0) {
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="lineid" value="'.((int) $lineid).'">';
} else {
	print '<input type="hidden" name="action" value="add">';
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('AgroDrugUseCrop').'</td>';
print '<td>'.$langs->trans('AgroDrugUseAdversity').'</td>';
print '<td>'.$langs->trans('AgroDrugUseDose').'</td>';
print '<td class="center">'.$form->textwithpicto($langs->trans('AgroDrugUseSafetyDays'), $langs->trans('AgroDrugUseSafetyDaysHelp')).'</td>';
print '<td class="center">'.$form->textwithpicto($langs->trans('AgroDrugMaxApplications'), $langs->trans('AgroDrugUseMaxApplicationsHelp')).'</td>';
print '<td>'.$langs->trans('Note').'</td>';
print '<td></td>';
print '</tr>';

foreach ($uses as $use) {
	print '<tr class="oddeven">';
	if ($action == 'edit' && $lineid == (int) $use->rowid && $permissiontoadd) {
		print dolifarmAgroDrugUseInputs($use, $doseunits, $form);
		print '<td class="center nowraponall">';
		print '<input type="submit" class="button buttongen button-save small" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' <input type="submit" class="button buttongen button-cancel small" name="cancel" value="'.dol_escape_htmltag($langs->trans('Cancel')).'" formnovalidate>';
		print '</td>';
	} else {
		print '<td><span class="badge badge-secondary">'.dol_escape_htmltag($use->crop_eppo).'</span> '.dol_escape_htmltag((string) $use->crop_label).'</td>';
		print '<td>';
		if ((string) $use->adversity_code === '' && (string) $use->adversity_label === '') {
			print '<span class="opacitymedium">'.$langs->trans('AgroDrugUseAllAdversities').'</span>';
		} else {
			print ((string) $use->adversity_code !== '' ? '<span class="badge badge-secondary">'.dol_escape_htmltag($use->adversity_code).'</span> ' : '').dol_escape_htmltag((string) $use->adversity_label);
		}
		print '</td>';
		print '<td class="nowraponall">';
		if ($use->dose_min !== null || $use->dose_max !== null) {
			$unit = isset($doseunits[(string) $use->dose_unit]) ? $doseunits[(string) $use->dose_unit] : (string) $use->dose_unit;
			print ($use->dose_min !== null ? price(price2num($use->dose_min)) : '').' - '.($use->dose_max !== null ? price(price2num($use->dose_max)) : '').' '.dol_escape_htmltag($unit);
		}
		print '</td>';
		print '<td class="center">'.($use->safety_days !== null ? (int) $use->safety_days : '').'</td>';
		print '<td class="center">'.($use->max_applications !== null ? (int) $use->max_applications : '').'</td>';
		print '<td>'.dol_escape_htmltag((string) $use->note).'</td>';
		print '<td class="right nowraponall">';
		if ($permissiontoadd && $action != 'edit') {
			print '<a class="editfielda reposition marginrightonly" href="'.dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id, 'action' => 'edit', 'lineid' => $use->rowid]).'">'.img_edit().'</a>';
			print '<a class="reposition" href="'.dolBuildUrl($_SERVER['PHP_SELF'], ['id' => $object->id, 'action' => 'delete', 'lineid' => $use->rowid, 'token' => newToken()]).'">'.img_delete().'</a>';
		}
		print '</td>';
	}
	print '</tr>';
}
if (empty($uses)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('AgroDrugUsesNone').'</span></td></tr>';
}

// New use
if ($permissiontoadd && $action != 'edit') {
	print '<tr class="liste_titre nodrag nodrop"><td colspan="7">'.$langs->trans('AgroDrugUseAdd').'</td></tr>';
	print '<tr class="oddeven">';
	print dolifarmAgroDrugUseInputs(null, $doseunits, $form);
	print '<td class="center"><input type="submit" class="button buttongen small" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
