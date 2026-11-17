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
 * \file    dolifarm/lib/dolifarm_plotsign.lib.php
 * \ingroup dolifarm
 * \brief   QR signs of the plots, to put in the field: the QR code is the address of the card of the plot with its
 *          reference (read with the camera it opens the card after the login; read by the field app it opens the plot)
 */

/**
 * Formats of the signs: one sign on an A5 or A4 page, or adhesive labels on an A4 sheet (sizes in mm)
 *
 * @return	array<string,array<string,float|int|string>>
 */
function dolifarmPlotSignFormats()
{
	return array(
		'A5' => array('type' => 'page', 'page' => 'A5', 'w' => 148, 'h' => 210),
		'A4' => array('type' => 'page', 'page' => 'A4', 'w' => 210, 'h' => 297),
		'A4_2x4' => array('type' => 'label', 'w' => 105, 'h' => 74, 'cols' => 2, 'rows' => 4, 'left' => 0, 'top' => 0.5, 'font' => 10),
		'A4_2x5' => array('type' => 'label', 'w' => 105, 'h' => 57, 'cols' => 2, 'rows' => 5, 'left' => 0, 'top' => 6, 'font' => 9),
		'A4_3x8' => array('type' => 'label', 'w' => 70, 'h' => 37, 'cols' => 3, 'rows' => 8, 'left' => 0, 'top' => 0.5, 'font' => 7),
	);
}

/**
 * Address in the QR code of a plot: card of the plot by reference
 *
 * @param	string	$ref	Reference of the plot
 * @return	string			Absolute URL
 */
function dolifarmPlotSignUrl($ref)
{
	return dol_buildpath('/dolifarm/dolifarmplot_card.php', 2).'?ref='.urlencode((string) $ref);
}

/**
 * Data printed on the sign of a plot
 *
 * @param	DoliDB			$db		Database handler
 * @param	Translate		$langs	Language of the output
 * @param	DoliFarmPlot	$plot	Plot
 * @return	array{ref:string,label:string,url:string,place:string,area:string,farm:string,crop:string,season:string}
 */
function dolifarmPlotSignData($db, $langs, $plot)
{
	$place = trim((string) $plot->cadastral_comune);
	$parts = array();
	if ((string) $plot->cadastral_sheet !== '') {
		$parts[] = $langs->transnoentities('PlotSignSheet', $plot->cadastral_sheet);
	}
	if ((string) $plot->cadastral_parcel !== '') {
		$parts[] = $langs->transnoentities('PlotSignParcel', $plot->cadastral_parcel);
	}
	if (count($parts)) {
		$place .= ($place !== '' ? ' - ' : '').implode(' ', $parts);
	}
	$size = (float) ($plot->size_conducted > 0 ? $plot->size_conducted : ($plot->size_sau > 0 ? $plot->size_sau : $plot->size_total));
	$farm = '';
	if ($plot->fk_soc > 0) {
		$resql = $db->query("SELECT nom, name_alias FROM ".$db->prefix()."societe WHERE rowid = ".((int) $plot->fk_soc));
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$farm = (string) $obj->nom;
		}
	}
	// Crop in progress: production plan of doliTrace not closed whose period contains today, else the last one not closed
	$crop = '';
	$season = '';
	if (isModEnabled('dolitrace')) {
		$today = $db->idate(dol_now());
		$sql = "SELECT cp.season, c.label as crop_label, c.variety";
		$sql .= " FROM ".$db->prefix()."dolitrace_cropplan as cp";
		$sql .= " LEFT JOIN ".$db->prefix()."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " WHERE cp.fk_plot = ".((int) $plot->id)." AND cp.entity IN (".getEntity('dolitracecropplan').") AND cp.status IN (0, 1)";
		$sql .= " ORDER BY CASE WHEN cp.date_start <= '".$db->escape($today)."' AND (cp.date_end IS NULL OR cp.date_end >= '".$db->escape($today)."') THEN 0 ELSE 1 END, cp.date_start DESC, cp.rowid DESC";
		$sql .= $db->plimit(1);
		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$crop = $langs->transnoentities((string) $obj->crop_label);
			if ((string) $obj->variety !== '') {
				$crop .= ' - '.$obj->variety;
			}
			$season = (string) $obj->season;
		}
	}
	return array(
		'ref' => (string) $plot->ref,
		'label' => (string) $plot->label,
		'url' => dolifarmPlotSignUrl($plot->ref),
		'place' => $place,
		'area' => ($size > 0 ? price($size, 0, $langs, 0, 0, 4).' ha' : ''),
		'farm' => $farm,
		'crop' => $crop,
		'season' => $season,
	);
}

/**
 * PDF of the signs of some plots
 *
 * @param	array<int,array<string,string>>	$signs	Data of the plots (dolifarmPlotSignData())
 * @param	string		$format		Format (dolifarmPlotSignFormats())
 * @param	int			$copies		Copies of each sign
 * @param	int			$skip		Labels already used on the first sheet (labels only)
 * @param	Translate	$outputlangs	Language
 * @return	string					Content of the PDF
 */
function dolifarmPlotSignPdf(array $signs, $format, $copies, $skip, $outputlangs)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
	$formats = dolifarmPlotSignFormats();
	$f = $formats[$format] ?? $formats['A5'];
	$copies = max(1, min((int) $copies, 100));
	$islabel = ($f['type'] == 'label');

	$pdf = pdf_getInstance($islabel ? array(210, 297) : array($f['w'], $f['h']), 'mm', 'P');
	$pdf->SetCreator('Dolibarr '.DOL_VERSION);
	$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentities('PlotSignTitle')));
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(0, 0, 0);
	$pdf->SetAutoPageBreak(false, 0);
	$pdf->setCellPaddings(0, 0.2, 0, 0.2);
	$font = pdf_getPDFFont($outputlangs);
	$style = array('border' => 0, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false);
	$out = function ($text) use ($outputlangs) {
		return $outputlangs->convToOutputCharset($text);
	};

	$list = array();
	foreach ($signs as $sign) {
		for ($i = 0; $i < $copies; $i++) {
			$list[] = $sign;
		}
	}

	if (!$islabel) {
		// One sign for each page: title, QR code in the middle, data under it
		$w = $f['w'];
		$h = $f['h'];
		$m = ($format == 'A4' ? 15 : 10);
		$k = $w / 148;	// scale of the fonts on A4
		foreach ($list as $s) {
			$pdf->AddPage();
			$pdf->SetLineWidth(0.6);
			$pdf->Rect($m / 2, $m / 2, $w - $m, $h - $m);
			$pdf->SetXY($m, $m);
			$pdf->SetFont($font, 'B', 22 * $k);
			$pdf->MultiCell($w - 2 * $m, 0, $out($s['label'] !== '' ? $s['label'] : $s['ref']), 0, 'C', false, 1, '', '', true, 0, false, true, 0, 'T', true);
			$pdf->SetFont($font, '', 13 * $k);
			$pdf->SetX($m);
			$pdf->MultiCell($w - 2 * $m, 0, $out($s['ref']), 0, 'C', false, 1);
			$qr = $w - 2 * $m - 20 * $k;
			$y = $pdf->GetY() + 5 * $k;
			$pdf->write2DBarcode($s['url'], 'QRCODE,M', ($w - $qr) / 2, $y, $qr, $qr, $style, 'N');
			$pdf->SetY($y + $qr + 4 * $k);
			$lines = array(
				array($s['crop'] !== '' ? $outputlangs->transnoentities('PlotSignCrop', $s['crop']).($s['season'] !== '' ? ' ('.$s['season'].')' : '') : '', 'B', 14),
				array(trim($s['place'].($s['area'] !== '' ? ' - '.$s['area'] : ''), ' -'), '', 12),
				array($s['farm'], '', 12),
			);
			foreach ($lines as $line) {
				if ($line[0] === '') {
					continue;
				}
				$pdf->SetFont($font, $line[1], $line[2] * $k);
				$pdf->SetX($m);
				$pdf->MultiCell($w - 2 * $m, 0, $out($line[0]), 0, 'C', false, 1);
			}
			$pdf->SetFont($font, 'I', 9 * $k);
			$pdf->SetXY($m, $h - $m - 6 * $k);
			$pdf->MultiCell($w - 2 * $m, 0, $out($outputlangs->transnoentities('PlotSignScan')), 0, 'C', false, 1);
		}
		return $pdf->Output('', 'S');
	}

	// Labels: QR code on the left, text on the right
	$perpage = $f['cols'] * $f['rows'];
	$skip = max(0, min((int) $skip, $perpage - 1));
	foreach ($list as $i => $s) {
		$pos = ($i + $skip) % $perpage;
		if ($i == 0 || $pos == 0) {
			$pdf->AddPage();
		}
		$x = $f['left'] + ($pos % $f['cols']) * $f['w'];
		$y = $f['top'] + intdiv($pos, $f['cols']) * $f['h'];
		$pad = 3;
		$qr = min($f['h'] - 2 * $pad, $f['w'] * 0.42);
		$pdf->write2DBarcode($s['url'], 'QRCODE,M', $x + $pad, $y + ($f['h'] - $qr) / 2, $qr, $qr, $style, 'N');
		$tx = $x + $pad + $qr + 2;
		$tw = $f['w'] - ($tx - $x) - $pad;
		$texts = array(
			array($s['label'] !== '' ? $s['label'] : $s['ref'], 'B', 1.15),
			array($s['ref'], '', 1),
			array($s['crop'], '', 1),
			array($s['place'], '', 0.9),
			array($s['farm'], '', 0.9),
		);
		$pdf->SetXY($tx, $y + $pad);
		foreach ($texts as $text) {
			if ($text[0] === '') {
				continue;
			}
			$pdf->SetFont($font, $text[1], $f['font'] * $text[2]);
			$pdf->SetX($tx);
			$pdf->MultiCell($tw, 0, $out($text[0]), 0, 'L', false, 1, '', '', true, 0, false, true, $f['font'] * $text[2] * 0.9, 'T', true);
			if ($pdf->GetY() > $y + $f['h'] - $pad) {
				break;
			}
		}
	}
	return $pdf->Output('', 'S');
}
