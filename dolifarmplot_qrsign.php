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
 *  \file       dolifarmplot_qrsign.php
 *  \ingroup    dolifarm
 *  \brief      QR signs of the plots (A5, A4 or labels), from the card of a plot or from the list (mass action)
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
dol_include_once('/dolifarm/class/dolifarmplot.class.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_plotsign.lib.php');

$langs->loadLangs(array('dolifarm@dolifarm', 'other'));

$action = GETPOST('action', 'aZ09');
$formats = dolifarmPlotSignFormats();
$format = GETPOST('format', 'aZ09');
if (!isset($formats[$format])) {
	$format = 'A5';
}
$copies = GETPOSTISSET('copies') ? GETPOSTINT('copies') : 1;
$skip = GETPOSTINT('skip');
// One plot (id) or several (ids, from the list)
$ids = array();
foreach (explode(',', GETPOST('ids', 'alphanohtml').','.GETPOSTINT('id')) as $value) {
	if ((int) $value > 0) {
		$ids[(int) $value] = (int) $value;
	}
}

// Security check: read permission, then the farm of each plot (entity and sales representative)
if (!isModEnabled('dolifarm') || !$user->hasRight('dolifarm', 'dolifarmplot', 'read') || empty($ids) || count($ids) > 500) {
	accessforbidden();
}
$plots = array();
foreach ($ids as $plotid) {
	$plot = new DoliFarmPlot($db);
	if ($plot->fetch($plotid) <= 0) {
		continue;
	}
	dolifarmCheckSocAccess($user, $plot);
	$plots[] = $plot;
}
if (empty($plots)) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

if ($action == 'pdf') {
	$signs = array();
	foreach ($plots as $plot) {
		$signs[] = dolifarmPlotSignData($db, $langs, $plot);
	}
	$content = dolifarmPlotSignPdf($signs, $format, $copies, $skip, $langs);
	$name = (count($plots) == 1 ? $plots[0]->ref : count($plots).'_appezzamenti');
	top_httphead('application/pdf');
	header('Content-Disposition: inline; filename="'.dol_sanitizeFileName('cartelli_qr_'.$name.'.pdf').'"');
	header('Content-Length: '.strlen($content));
	print $content;
	exit;
}


/*
 * View
 */

$title = $langs->trans('PlotSignTitle');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifarm page-plot-qrsign');
if (count($plots) == 1) {
	$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmplot_card.php', 1).'?id='.$plots[0]->id.'">'.$langs->trans('Back').'</a>';
} else {
	$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmplot_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
}
print load_fiche_titre($title, $linkback, 'fa-qrcode');

print '<span class="opacitymedium">'.$langs->trans('PlotSignHelp').'</span><br><br>';

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" target="_blank">';
print '<input type="hidden" name="ids" value="'.dol_escape_htmltag(implode(',', array_map(function ($p) {
	return $p->id;
}, $plots))).'">';
print '<input type="hidden" name="action" value="pdf">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('DoliFarmPlots').'</td><td>';
$links = array();
foreach ($plots as $plot) {
	$links[] = $plot->getNomUrl(1);
}
print implode(', ', $links);
print '</td></tr>';
if (count($plots) == 1) {
	print '<tr><td>'.$langs->trans('PlotSignQrContent').'</td><td class="wordbreak">'.dol_escape_htmltag(dolifarmPlotSignUrl($plots[0]->ref)).'</td></tr>';
}
print '<tr><td>'.$langs->trans('PlotSignFormat').'</td><td><select name="format" id="format" class="minwidth300">';
foreach ($formats as $key => $def) {
	print '<option value="'.$key.'"'.($key == $format ? ' selected' : '').'>'.$langs->trans('PlotSignFormat'.$key).'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('PlotSignCopies').'</td><td><input type="number" name="copies" min="1" max="100" class="width75" value="'.((int) max(1, $copies)).'"></td></tr>';
print '<tr id="plotsign-skip"><td>'.$langs->trans('PlotSignSkip').'</td><td><input type="number" name="skip" min="0" max="23" class="width75" value="'.((int) $skip).'"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('PlotSignPrint')).'"></div>';
print '</form>';
// The skipped labels make sense only for the sheets of labels
print '<script>$(function () { function s() { $("#plotsign-skip").toggle($("#format").val().indexOf("A4_") === 0); } $("#format").on("change", s); s(); });</script>';

llxFooter();
$db->close();
