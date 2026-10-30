<?php
/* Copyright (C) 2026 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/dolifarm_fields.lib.php
 * \ingroup dolifarm
 * \brief   Print the fields of a CommonObject card in groups instead of one long list.
 *
 * Same behaviour as core/tpl/commonfields_{add,edit,view}.tpl.php (visibility, values read back
 * from POST, showInputField/showOutputField), but only for the fields given, in the given order.
 * A layout item is either a field key or array('label' => 'LangKey', 'fields' => array(key1, key2))
 * to print several fields on one row (for example the start and end date of a period), with an optional
 * 'prefixes' => array('FromDate', 'ToDate') to print a short label before each field.
 */

/**
 * Tell if a field is shown in a mode, with the same rules as the core templates
 *
 * @param	string					$key	Field key
 * @param	array<string,mixed>		$val	Field definition
 * @param	string					$mode	'create', 'edit' or 'view'
 * @return	bool
 */
function dolifarmFieldIsShown($key, $val, $mode)
{
	if (array_key_exists('enabled', $val) && isset($val['enabled']) && !verifCond($val['enabled'])) {
		return false;
	}
	$visible = abs((int) $val['visible']);
	if ($mode == 'create') {
		return in_array($visible, array(1, 3, 6));
	}
	if ($mode == 'edit') {
		return in_array($visible, array(1, 3, 4));
	}
	// rowid, ref and status are already in the banner
	return in_array($visible, array(1, 3, 4, 5)) && !in_array($key, array('rowid', 'ref', 'status'));
}

/**
 * Value to show in a form: the posted one if the form was sent back, else the object one
 *
 * @param	CommonObject			$object	Object
 * @param	string					$key	Field key
 * @param	array<string,mixed>		$val	Field definition
 * @param	string					$mode	'create' or 'edit'
 * @return	mixed
 */
function dolifarmFieldFormValue($object, $key, $val, $mode)
{
	$type = $val['type'];
	if ($mode == 'edit' && !GETPOSTISSET($key) && !GETPOSTISSET($key.'year')) {
		return ($type == 'price') ? price2num($object->$key) : $object->$key;
	}

	if (in_array($type, array('int', 'integer'))) {
		return ($mode == 'create') ? GETPOST($key) : GETPOSTINT($key);	// In creation the value can still be ""
	}
	if ($type == 'double' || preg_match('/^double/', $type)) {
		return price2num(GETPOST($key, 'alphanohtml'));
	}
	if (preg_match('/^(text|html)/', $type)) {
		$tmparray = explode(':', $type);
		$check = !empty($tmparray[1]) ? $tmparray[1] : (preg_match('/^html/', $type) ? 'restricthtml' : 'nohtml');
		return GETPOST($key, $check);
	}
	if ($type == 'date') {
		return dol_mktime(12, 0, 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
	}
	if ($type == 'datetime') {
		return dol_mktime(GETPOSTINT($key.'hour'), GETPOSTINT($key.'min'), 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
	}
	if ($type == 'boolean') {
		return ($mode == 'create') ? (GETPOST($key) == 'on' ? 1 : 0) : GETPOST($key, 'alpha');
	}
	if ($type == 'price') {
		return price2num(GETPOST($key));
	}
	return GETPOST($key, ($mode == 'create') ? 'alphanohtml' : 'alpha');
}

/**
 * Print a table with some fields of an object
 *
 * @param	CommonObject			$object		Object
 * @param	array<int,mixed>		$items		Layout items: field keys or array('label' => ..., 'fields' => array(...))
 * @param	string					$mode		'create', 'edit' or 'view'
 * @param	string					$title		Title of the group (lang key), '' for none
 * @param	array<string,mixed>		$options	'morerows' => html printed before </table>
 * @return	void
 */
function dolifarmPrintFieldsTable($object, $items, $mode, $title = '', $options = array())
{
	global $langs;

	$tableclass = ($mode == 'view') ? 'tableforfield' : 'tableforfield'.$mode;
	print '<table class="border centpercent '.$tableclass.' dolifarm-fields">'."\n";
	if ($title) {
		print '<tr class="liste_titre dolifarm-fields-title"><td colspan="2">'.$langs->trans($title).'</td></tr>'."\n";
	}
	foreach ($items as $item) {
		if (is_array($item)) {
			$prefixes = empty($item['prefixes']) ? array() : array_combine($item['fields'], $item['prefixes']);
			dolifarmPrintFieldRow($object, $item['fields'], $mode, $item['label'], $options, $prefixes);
		} else {
			dolifarmPrintFieldRow($object, array($item), $mode, '', $options);
		}
	}
	if (!empty($options['morerows'])) {
		print $options['morerows'];
	}
	print '</table>'."\n";
}

/**
 * Print one row with one or more fields
 *
 * @param	CommonObject			$object		Object
 * @param	string[]				$keys		Field keys
 * @param	string					$mode		'create', 'edit' or 'view'
 * @param	string					$label		Label of the row (lang key), '' to use the label of the field
 * @param	array<string,mixed>		$options	See dolifarmPrintFieldsTable()
 * @param	array<string,string>	$prefixes	Short label (lang key) printed before a field, by field key
 * @return	void
 */
function dolifarmPrintFieldRow($object, $keys, $mode, $label = '', $options = array(), $prefixes = array())
{
	global $langs, $form;

	if (!is_object($form)) {
		$form = new Form($object->db);
	}

	$fields = array();
	foreach ($keys as $key) {
		if (isset($object->fields[$key]) && dolifarmFieldIsShown($key, $object->fields[$key], $mode)) {
			$fields[$key] = $object->fields[$key];
		}
	}
	if (empty($fields)) {
		return;
	}
	reset($fields);
	$firstkey = key($fields);
	$first = $fields[$firstkey];

	// Label
	$required = false;
	$tdtop = false;
	foreach ($fields as $val) {
		$required = $required || (isset($val['notnull']) && $val['notnull'] > 0);
		$tdtop = $tdtop || preg_match('/^(text|html)/', $val['type']);
	}
	if ($label) {
		$labeltoshow = $langs->trans($label);
	} elseif (!empty($first['help'])) {
		$labeltoshow = $form->textwithpicto($langs->trans($first['label']), $langs->trans($first['help']));
	} else {
		$labeltoshow = $langs->trans($first['label']);
	}

	print '<tr class="field_'.implode(' field_', array_keys($fields)).'">';
	if ($mode == 'view') {
		$tdcss = empty($first['tdcss']) ? 'titlefieldmiddle' : $first['tdcss'];
		print '<td class="'.$tdcss.' fieldname_'.$firstkey.($tdtop ? ' tdtop' : '').'">';
		if (count($fields) == 1 && !empty($first['alwayseditable'])) {
			print $form->editfieldkey($labeltoshow, $firstkey, $object->$firstkey, $object, 1, $first['type']);
		} else {
			print $labeltoshow;
		}
		print '</td>';
		print '<td class="valuefield fieldname_'.$firstkey.(empty($first['cssview']) ? '' : ' '.$first['cssview']).'">';
	} else {
		print '<td class="titlefieldcreate'.($required ? ' fieldrequired' : '').($tdtop ? ' tdtop' : '').'">';
		print '<label for="'.$firstkey.'" class="block">'.$labeltoshow.'</label>';
		print '</td>';
		print '<td class="valuefieldcreate">';
	}

	$parts = array();
	foreach ($fields as $key => $val) {
		$out = '';
		if ($mode == 'view') {
			$value = $object->$key;
			if (count($fields) == 1 && !empty($val['alwayseditable'])) {
				$out = $form->editfieldval($labeltoshow, $key, $value, $object, 1, $val['type']);
			} else {
				$out = $object->showOutputField($val, $key, $value, '', '', '', 0);
				if (preg_match('/^(text|html)/', $val['type'])) {
					$out = '<div class="longmessagecut">'.$out.'</div>';
				}
			}
			if ($out !== '' && !empty($prefixes[$key])) {
				$out = '<span class="opacitymedium">'.$langs->trans($prefixes[$key]).'</span> '.$out;
			}
		} else {
			$value = dolifarmFieldFormValue($object, $key, $val, $mode);
			if (!empty($val['picto'])) {
				$out .= img_picto('', $val['picto'], '', 0, 0, 0, '', 'pictofixedwidth');
			}
			if (!empty($val['noteditable'])) {
				$out .= $object->showOutputField($val, $key, $value, '', '', '', 0);
			} else {
				$out .= $object->showInputField($val, $key, $value, '', '', '', 0);
			}
			if (!empty($prefixes[$key])) {
				$out = '<span class="opacitymedium paddingright">'.$langs->trans($prefixes[$key]).'</span>'.$out;
			}
			if (count($fields) > 1) {
				// Keep each field and its own label together when the row wraps
				$out = '<span class="nowraponall inline-block marginrightonly" title="'.dol_escape_htmltag($langs->trans($val['label'])).'">'.$out.'</span>';
			}
		}
		if ($mode != 'view' || $out !== '') {
			$parts[] = $out;
		}
	}
	// In forms the fields of a row may wrap, so no separator that could stay alone at the end of a line
	print implode((($mode == 'view' && empty($prefixes)) ? ' <span class="opacitymedium">&ndash;</span> ' : ' '), $parts);

	print '</td>';
	print '</tr>'."\n";
}

/**
 * Fields shown in a mode that are not placed in a layout, to print them anyway (new fields are never lost)
 *
 * @param	CommonObject			$object		Object
 * @param	array<int,mixed>		$layout		All layout items of the page
 * @param	string					$mode		'create', 'edit' or 'view'
 * @return	string[]
 */
function dolifarmFieldsNotInLayout($object, $layout, $mode)
{
	$placed = array();
	foreach ($layout as $item) {
		if (is_array($item)) {
			$placed = array_merge($placed, $item['fields']);
		} else {
			$placed[] = $item;
		}
	}

	$missing = array();
	foreach (dol_sort_array($object->fields, 'position') as $key => $val) {
		if (!in_array($key, $placed) && dolifarmFieldIsShown($key, $val, $mode)) {
			$missing[] = $key;
		}
	}
	return $missing;
}

/**
 * Print the CSS of the grouped fields (once)
 *
 * @return	void
 */
function dolifarmPrintFieldsCss()
{
	static $done = false;
	if ($done) {
		return;
	}
	$done = true;

	print '<style>
.dolifarm-section { margin-top: 18px; }
.dolifarm-fields { margin-bottom: 12px; table-layout: fixed; }
.dolifarm-fields td.titlefieldcreate, .dolifarm-fields td.titlefieldmiddle { width: 35%; }
.dolifarm-fields td.valuefieldcreate input[type="text"], .dolifarm-fields td.valuefieldcreate textarea { max-width: 100%; box-sizing: border-box; }
.dolifarm-fields td.valuefieldcreate input.minwidth300, .dolifarm-fields td.valuefieldcreate input.minwidth400 { min-width: 0; width: 100%; }
.dolifarm-fields .select2-container { max-width: 100%; }
.dolifarm-cols { display: flex; flex-wrap: wrap; justify-content: space-between; }
</style>'."\n";
}
