<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       core/modules/dolifarm/doc/pdf_scheda_dolifarmplot.modules.php
 *  \ingroup    dolifarm
 *  \brief      One page sheet of a plot: essential data and a QR code that opens the card of the plot
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/dolifarm/core/modules/dolifarm/modules_dolifarmplot.php');


/**
 *  Class to build the sheet of a plot
 */
class pdf_scheda_dolifarmplot extends ModelePDFDoliFarmPlot
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document
	 * @var string Version, possible values are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'''|'development'|'dolibarr'|'experimental'
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe Issuer
	 */
	public $emetteur;

	/**
	 * Groups of fields printed on the sheet (lang key of the title => field keys)
	 * @var array<string,string[]>
	 */
	public $sheetgroups = array(
		'DoliFarmPlot' => array('label', 'fk_soc', 'fk_dossier', 'fk_ownership', 'fk_owner', 'conduction_protocol', 'date_conduction_start', 'date_conduction_end'),
		'Cadastre' => array('cadastral_comune', 'cadastral_section', 'cadastral_sheet', 'cadastral_parcel', 'cadastral_sub', 'cadastral_reference'),
		'Surfaces' => array('size_cadastral', 'size_total', 'size_conducted', 'size_sau'),
		'Coordinates' => array('geo_lat', 'geo_lon'),
		'OrganicFarming' => array('fk_status_bio', 'date_conversion_start', 'date_conversion_end', 'zone_zvn', 'zone_natura2000'),
	);

	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array("main", "dolifarm@dolifarm"));

		$this->db = $db;
		$this->name = "scheda";
		$this->description = $langs->trans('PlotSheetPDFModel');
		$this->update_main_doc_field = 1;

		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->emetteur = $mysoc;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build and write pdf to disk
	 *
	 *  @param	DoliFarmPlot	$object				Source object to generate document from
	 *  @param	Translate		$outputlangs		Lang output object
	 *  @param	string			$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param	int<0,1>		$hidedetails		Do not show line details
	 *  @param	int<0,1>		$hidedesc			Do not show desc
	 *  @param	int<0,1>		$hideref			Do not show ref
	 *  @return	int<-1,1>							1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $langs, $conf, $hookmanager;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array("main", "dict", "companies", "dolifarm@dolifarm"));

		$dir_output = getMultidirOutput($object, $object->module);
		if (empty($dir_output)) {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "DOLIFARM_OUTPUTDIR");
			return 0;
		}
		$dir_output .= '/'.$object->element;
		if ($object->specimen) {
			$dir = $dir_output;
			$file = $dir."/SPECIMEN.pdf";
		} else {
			$objectref = dol_sanitizeFileName($object->ref);
			$dir = $dir_output."/".$objectref;
			$file = $dir."/".$objectref.".pdf";
		}
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$pdf->setAutoPageBreak(true, $this->marge_basse + 15);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentities("PlotSheet").' '.$object->ref));
		$pdf->SetSubject($outputlangs->transnoentities("PlotSheet"));
		$pdf->SetCreator("Dolibarr ".DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($this->emetteur->name));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PlotSheet"));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->AddPage();

		$usablewidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$qrsize = 40;
		$qrx = $this->page_largeur - $this->marge_droite - $qrsize;

		// Header: issuer on the left, title and ref on the right
		$posy = $this->marge_haute;
		$logo = '';
		if (!empty($this->emetteur->logo)) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$object->entity])) {
				$logodir = $conf->mycompany->multidir_output[$object->entity];
			}
			$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
			if (empty($this->emetteur->logo_small) || !is_readable($logo)) {
				$logo = $logodir.'/logos/'.$this->emetteur->logo;
			}
		}
		if ($logo && is_readable($logo)) {
			$height = pdf_getHeightForLogo($logo);
			$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
		} else {
			$pdf->SetFont('', 'B', $default_font_size + 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 6, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
		}

		$pdf->SetFont('', 'B', $default_font_size + 6);
		$pdf->SetXY($this->marge_gauche + 100, $posy);
		$pdf->MultiCell($usablewidth - 100, 8, $outputlangs->transnoentities("PlotSheet"), 0, 'R');
		$pdf->SetFont('', 'B', $default_font_size + 2);
		$pdf->SetX($this->marge_gauche + 100);
		$pdf->MultiCell($usablewidth - 100, 6, $outputlangs->convToOutputCharset($object->ref), 0, 'R');
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->SetX($this->marge_gauche + 100);
		$pdf->MultiCell($usablewidth - 100, 5, $outputlangs->transnoentities("PlotSheetGenerated").': '.dol_print_date(dol_now(), 'day', false, $outputlangs, true), 0, 'R');

		$posy = max($pdf->GetY(), $this->marge_haute + 22) + 6;
		$pdf->Line($this->marge_gauche, $posy, $this->page_largeur - $this->marge_droite, $posy);
		$posy += 5;

		// QR code with the link to the card of the plot, on the right of the first group
		$url = dol_buildpath('/dolifarm/dolifarmplot_card.php', 2).'?id='.((int) $object->id);
		$style = array('border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false);
		$pdf->write2DBarcode($url, 'QRCODE,M', $qrx, $posy, $qrsize, $qrsize, $style, 'N');
		$pdf->SetFont('', '', $default_font_size - 3);
		$pdf->SetXY($qrx - 5, $posy + $qrsize + 1);
		$pdf->MultiCell($qrsize + 5, 4, $outputlangs->transnoentities("PlotSheetScanToOpen"), 0, 'C');
		$qrbottom = $pdf->GetY();

		// Groups of fields: label / value on two columns, narrower next to the QR code
		$labelwidth = 55;
		foreach ($this->sheetgroups as $title => $keys) {
			$rows = array();
			foreach ($keys as $key) {
				$value = $this->getFieldText($object, $key, $outputlangs);
				if ($value !== '') {
					$rows[] = array($outputlangs->transnoentities($object->fields[$key]['label']), $value);
				}
			}
			if (empty($rows)) {
				continue;
			}

			$width = ($posy < $qrbottom) ? ($qrx - $this->marge_gauche - 6) : $usablewidth;
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetFillColor(230, 236, 230);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell($width, 6, $outputlangs->transnoentities($title), 0, 'L', true);
			$posy = $pdf->GetY() + 1;

			foreach ($rows as $row) {
				$width = ($posy < $qrbottom) ? ($qrx - $this->marge_gauche - 6) : $usablewidth;
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->SetTextColor(90, 90, 90);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell($labelwidth, 5, $outputlangs->convToOutputCharset($row[0]), 0, 'L');
				$labelbottom = $pdf->GetY();
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetXY($this->marge_gauche + $labelwidth, $posy);
				$pdf->MultiCell($width - $labelwidth, 5, $outputlangs->convToOutputCharset($row[1]), 0, 'L');
				$posy = max($labelbottom, $pdf->GetY()) + 0.5;
			}
			$posy += 4;
		}

		// Footer: link to the card, for who prints the sheet without a QR reader (in the bottom margin, so no page break)
		$pdf->setAutoPageBreak(false);
		$pdf->SetFont('', '', $default_font_size - 3);
		$pdf->SetTextColor(90, 90, 90);
		$pdf->SetXY($this->marge_gauche, $this->page_hauteur - $this->marge_basse - 8);
		$pdf->MultiCell($usablewidth, 4, (empty($this->emetteur->name) ? '' : $outputlangs->convToOutputCharset($this->emetteur->name).' - ').$url, 0, 'C');
		$pdf->SetTextColor(0, 0, 0);

		$pdf->Close();
		$pdf->Output($file, 'F');

		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
		}

		dolChmod($file);
		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * Value of a field as plain text, '' if empty or not enabled
	 *
	 * @param	DoliFarmPlot	$object			Plot
	 * @param	string			$key			Field key
	 * @param	Translate		$outputlangs	Lang output object
	 * @return	string
	 */
	protected function getFieldText($object, $key, $outputlangs)
	{
		if (empty($object->fields[$key]) || (isset($object->fields[$key]['enabled']) && !verifCond($object->fields[$key]['enabled']))) {
			return '';
		}
		$val = $object->fields[$key];
		$value = $object->$key;
		if ($value === null || $value === '' || (in_array($val['type'], array('date', 'datetime')) && empty($value))) {
			return '';
		}
		if (preg_match('/^integer:/', $val['type']) && (int) $value <= 0) {
			return '';
		}
		if ($val['type'] == 'boolean') {
			return $outputlangs->transnoentities($value ? 'Yes' : 'No');
		}
		if (in_array($val['type'], array('date', 'datetime'))) {
			return dol_print_date($value, ($val['type'] == 'date' ? 'day' : 'dayhour'), false, $outputlangs);
		}
		if (preg_match('/^double/', $val['type'])) {
			return price($value, 0, $outputlangs, 0, -1, -1);
		}
		// Linked objects and dictionaries: rendered by the object, then the html removed
		$text = $object->showOutputField($val, $key, $value, '', '', '', 0);
		return trim(dol_string_nohtmltag($text, 1));
	}
}
