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
 *  \file       core/modules/dolifarm/doc/pdf_fascicolo_dolifarmfarmdossier.modules.php
 *  \ingroup    dolifarm
 *  \brief      Document of the farm dossier: farm, data of the dossier, land consistency (plots with cadastre, title of
 *              conduction, surfaces, organic status), summaries of the surfaces, machines, buildings, livestock and
 *              production plans of the season (doliTrace)
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/dolifarm/core/modules/dolifarm/modules_dolifarmfarmdossier.php');


/**
 *  Class to build the document of a farm dossier
 */
class pdf_fascicolo_dolifarmfarmdossier extends ModelePDFDoliFarmFarmDossier
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
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe Issuer
	 */
	public $emetteur;

	/**
	 * Fields of the dossier printed in the section "Data of the dossier"
	 * @var string[]
	 */
	public $dossierfields = array('paying_agency', 'act_type', 'sheet_number', 'protocol', 'date_signature', 'date_validity_end', 'date_reference', 'fk_soc_caa', 'date_mandate', 'ote_code', 'economic_size', 'fk_soc_bio_body', 'date_bio_membership', 'fk_warehouse_inputs', 'fk_warehouse_products');

	/**
	 * @var TCPDF|TCPDI PDF being built
	 */
	protected $pdf;

	/**
	 * @var Translate Output languages
	 */
	protected $outputlangs;

	/**
	 * @var int Default font size
	 */
	protected $fontsize = 10;

	/**
	 * @var float Current vertical position
	 */
	protected $posy = 0;

	/**
	 * @var string Reference of the dossier, in the header of the pages
	 */
	protected $headerref = '';

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
		$this->name = "fascicolo";
		$this->description = $langs->trans('DossierPdfModel');
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
	 *  @param	DoliFarmFarmDossier	$object				Source object to generate document from
	 *  @param	Translate			$outputlangs		Lang output object
	 *  @param	string				$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param	int<0,1>			$hidedetails		Do not show line details
	 *  @param	int<0,1>			$hidedesc			Do not show desc
	 *  @param	int<0,1>			$hideref			Do not show ref
	 *  @return	int<-1,1>								1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $langs, $hookmanager, $user;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array("main", "dict", "companies", "dolifarm@dolifarm"));
		$this->outputlangs = $outputlangs;

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

		$soc = new Societe($this->db);
		if ($object->fk_soc > 0) {
			$soc->fetch($object->fk_soc);
		}

		$pdf = pdf_getInstance($this->format);
		$this->pdf = $pdf;
		$this->fontsize = pdf_getPDFFontSize($outputlangs);
		$this->headerref = (string) $object->ref;
		$pdf->setAutoPageBreak(false);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$title = $outputlangs->transnoentities("DossierPdfTitle");
		$pdf->SetTitle($outputlangs->convToOutputCharset($title.' '.$object->ref));
		$pdf->SetSubject($outputlangs->convToOutputCharset($title));
		$pdf->SetCreator("Dolibarr ".DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($this->emetteur->name));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref.' '.$title));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$this->newPage(true, $soc);

		// Farm
		$rows = array(array($outputlangs->transnoentities('Name'), (string) $soc->name));
		$address = trim(implode(', ', array_filter(array(trim(preg_replace('/\s*\R\s*/', ', ', (string) $soc->address)), trim($soc->zip.' '.$soc->town), (string) $soc->state))));
		if ($address !== '') {
			$rows[] = array($outputlangs->transnoentities('Address'), $address);
		}
		for ($i = 1; $i <= 6; $i++) {
			$key = 'idprof'.$i;
			if (!empty($soc->$key)) {
				$rows[] = array($outputlangs->transcountrynoentities('ProfId'.$i, (string) $soc->country_code), (string) $soc->$key);
			}
		}
		if (!empty($soc->tva_intra)) {
			$rows[] = array($outputlangs->transnoentities('VATIntra'), (string) $soc->tva_intra);
		}
		foreach (array('phone' => 'Phone', 'email' => 'Email') as $key => $label) {
			if (!empty($soc->$key)) {
				$rows[] = array($outputlangs->transnoentities($label), (string) $soc->$key);
			}
		}
		$this->printSection($outputlangs->transnoentities('DossierPdfFarm'));
		$this->printPairs($rows);

		// Data of the dossier
		$rows = array(array($outputlangs->transnoentities('Ref'), (string) $object->ref), array($outputlangs->transnoentities('Status'), dol_string_nohtmltag($object->getLibStatut(0), 1)));
		foreach ($this->dossierfields as $key) {
			$value = $this->getFieldText($object, $key, $outputlangs);
			if ($value !== '') {
				$rows[] = array($outputlangs->transnoentities($object->fields[$key]['label']), $value);
			}
		}
		$this->printSection($outputlangs->transnoentities('DossierPdfData'));
		$this->printPairs($rows);

		// Land consistency
		$plots = $this->getPlots($object);
		$this->printSection($outputlangs->transnoentities('DossierPdfPlots'));
		$cols = array(
			array($outputlangs->transnoentities('DossierPdfColPlot'), 26, 'L'),
			array($outputlangs->transnoentities('DossierPdfColComune'), 17, 'L'),
			array($outputlangs->transnoentities('DossierPdfColSection'), 9, 'C'),
			array($outputlangs->transnoentities('DossierPdfColSheet'), 8, 'C'),
			array($outputlangs->transnoentities('DossierPdfColParcel'), 9, 'C'),
			array($outputlangs->transnoentities('DossierPdfColSub'), 7, 'C'),
			array($outputlangs->transnoentities('DossierPdfColTitle'), 18, 'L'),
			array($outputlangs->transnoentities('DossierPdfColConductionEnd'), 17, 'C'),
			array($outputlangs->transnoentities('DossierPdfColSizeCadastral'), 16, 'R'),
			array($outputlangs->transnoentities('DossierPdfColSizeConducted'), 16, 'R'),
			array($outputlangs->transnoentities('DossierPdfColSau'), 14, 'R'),
			array($outputlangs->transnoentities('DossierPdfColBio'), 18, 'L'),
		);
		$rows = array();
		$totals = array('cad' => 0.0, 'cond' => 0.0, 'sau' => 0.0);
		$bytitle = array();
		$bybio = array();
		foreach ($plots as $plot) {
			$rows[] = array(
				$plot['ref'].($plot['label'] !== '' && $plot['label'] != $plot['ref'] ? ' - '.$plot['label'] : ''),
				$plot['comune'], $plot['section'], $plot['sheet'], $plot['parcel'], $plot['sub'],
				$plot['title'], $plot['conduction_end'],
				$this->ha($plot['size_cadastral']), $this->ha($plot['size_conducted']), $this->ha($plot['size_sau']),
				$plot['bio'],
			);
			$totals['cad'] += (float) $plot['size_cadastral'];
			$totals['cond'] += (float) $plot['size_conducted'];
			$totals['sau'] += (float) $plot['size_sau'];
			foreach (array('title' => &$bytitle, 'bio' => &$bybio) as $key => &$summary) {
				$group = ($plot[$key] !== '' ? $plot[$key] : $outputlangs->transnoentities('DossierPdfNotSet'));
				if (!isset($summary[$group])) {
					$summary[$group] = array('nb' => 0, 'cond' => 0.0, 'sau' => 0.0);
				}
				$summary[$group]['nb']++;
				$summary[$group]['cond'] += (float) $plot['size_conducted'];
				$summary[$group]['sau'] += (float) $plot['size_sau'];
			}
			unset($summary);
		}
		if (count($rows)) {
			$total = array($outputlangs->transnoentities('DossierPdfTotalPlots', count($rows)), '', '', '', '', '', '', '', $this->ha($totals['cad']), $this->ha($totals['cond']), $this->ha($totals['sau']), '');
			$this->printTable($cols, $rows, $total);

			// Summaries of the surfaces
			foreach (array('DossierPdfByTitle' => $bytitle, 'DossierPdfByBio' => $bybio) as $label => $summary) {
				$scols = array(
					array($outputlangs->transnoentities($label), 70, 'L'),
					array($outputlangs->transnoentities('DossierPdfColCount'), 30, 'R'),
					array($outputlangs->transnoentities('DossierPdfColSizeConducted'), 45, 'R'),
					array($outputlangs->transnoentities('DossierPdfColSau'), 45, 'R'),
				);
				$srows = array();
				ksort($summary);
				foreach ($summary as $group => $values) {
					$srows[] = array($group, (string) $values['nb'], $this->ha($values['cond']), $this->ha($values['sau']));
				}
				$this->printTable($scols, $srows);
			}
		} else {
			$this->printText($outputlangs->transnoentities('DossierPdfNoPlots'));
		}

		// Machines
		$machines = $this->getMachines($object);
		if (count($machines)) {
			$this->printSection($outputlangs->transnoentities('DossierPdfMachines'));
			$cols = array(
				array($outputlangs->transnoentities('DossierPdfColMachine'), 42, 'L'),
				array($outputlangs->transnoentities('DossierPdfColType'), 28, 'L'),
				array($outputlangs->transnoentities('DossierPdfColPower'), 11, 'R'),
				array($outputlangs->transnoentities('DossierPdfColFuel'), 28, 'L'),
				array($outputlangs->transnoentities('DossierPdfColYear'), 13, 'C'),
				array($outputlangs->transnoentities('DossierPdfColRevision'), 22, 'C'),
				array($outputlangs->transnoentities('DossierPdfColInsurance'), 22, 'C'),
				array($outputlangs->transnoentities('DossierPdfColSprayerCheck'), 24, 'C'),
			);
			$this->printTable($cols, $machines);
		}

		// Buildings and livestock: only with the right to read them (like the tabs of the dossier)
		if ($user->hasRight('dolifarm', 'dolifarmbuilding', 'read')) {
			$buildings = $this->getBuildings($object);
			if (count($buildings)) {
				$this->printSection($outputlangs->transnoentities('DossierPdfBuildings'));
				$cols = array(
					array($outputlangs->transnoentities('DossierPdfColBuilding'), 42, 'L'),
					array($outputlangs->transnoentities('DossierPdfColUse'), 30, 'L'),
					array($outputlangs->transnoentities('DossierPdfColTitle'), 22, 'L'),
					array($outputlangs->transnoentities('DossierPdfColComune'), 24, 'L'),
					array($outputlangs->transnoentities('DossierPdfColSheet'), 10, 'C'),
					array($outputlangs->transnoentities('DossierPdfColParcel'), 11, 'C'),
					array($outputlangs->transnoentities('DossierPdfColSub'), 10, 'C'),
					array($outputlangs->transnoentities('DossierPdfColCovered'), 20, 'R'),
					array($outputlangs->transnoentities('DossierPdfColVolume'), 20, 'R'),
				);
				$this->printTable($cols, $buildings);
			}
		}
		if ($user->hasRight('dolifarm', 'dolifarmlivestock', 'read')) {
			$livestock = $this->getLivestock($object);
			if (count($livestock)) {
				$this->printSection($outputlangs->transnoentities('DossierPdfLivestock'));
				$cols = array(
					array($outputlangs->transnoentities('DossierPdfColLivestock'), 44, 'L'),
					array($outputlangs->transnoentities('DossierPdfColBdn'), 24, 'L'),
					array($outputlangs->transnoentities('DossierPdfColSpecies'), 30, 'L'),
					array($outputlangs->transnoentities('DossierPdfColHeads'), 14, 'R'),
					array($outputlangs->transnoentities('DossierPdfColProduction'), 30, 'L'),
					array($outputlangs->transnoentities('DossierPdfColOrganic'), 14, 'C'),
					array($outputlangs->transnoentities('DossierPdfColComune'), 34, 'L'),
				);
				$this->printTable($cols, $livestock);
			}
		}

		// Production plans of the last season (doliTrace)
		if (isModEnabled('dolitrace') && $user->hasRight('dolitrace', 'dolitracecropplan', 'read')) {
			$season = 0;
			$cropplans = $this->getCropPlans($object, $season);
			if (count($cropplans)) {
				$this->printSection($outputlangs->transnoentities('DossierPdfCropPlans', $season));
				$cols = array(
					array($outputlangs->transnoentities('DossierPdfColCropPlan'), 38, 'L'),
					array($outputlangs->transnoentities('DossierPdfColPlot'), 36, 'L'),
					array($outputlangs->transnoentities('DossierPdfColCrop'), 40, 'L'),
					array($outputlangs->transnoentities('DossierPdfColPeriod'), 34, 'C'),
					array($outputlangs->transnoentities('DossierPdfColSau'), 18, 'R'),
					array($outputlangs->transnoentities('Status'), 24, 'L'),
				);
				$this->printTable($cols, $cropplans);
			}
		}

		// Public note
		$note = trim(dol_string_nohtmltag((string) $object->note_public, 1));
		if ($note !== '') {
			$this->printSection($outputlangs->transnoentities('NotePublic'));
			$this->printText($note);
		}

		$this->printFooters($soc);

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
	 * New page with the header (the full header with logo on the first page)
	 *
	 * @param	bool		$first	First page
	 * @param	?Societe	$soc	Farm
	 * @return	void
	 */
	protected function newPage($first = false, $soc = null)
	{
		global $conf;

		$pdf = $this->pdf;
		$outputlangs = $this->outputlangs;
		$pdf->AddPage();
		$usablewidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$posy = $this->marge_haute;
		$fontsize = $this->fontsize;

		if ($first) {
			$logo = '';
			if (!empty($this->emetteur->logo)) {
				$logodir = $conf->mycompany->dir_output;
				$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				if (empty($this->emetteur->logo_small) || !is_readable($logo)) {
					$logo = $logodir.'/logos/'.$this->emetteur->logo;
				}
			}
			if ($logo && is_readable($logo)) {
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, pdf_getHeightForLogo($logo));
			} else {
				$pdf->SetFont('', 'B', $fontsize + 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(100, 6, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			}
			$pdf->SetFont('', 'B', $fontsize + 6);
			$pdf->SetXY($this->marge_gauche + 90, $posy);
			$pdf->MultiCell($usablewidth - 90, 8, $outputlangs->transnoentities('DossierPdfTitle'), 0, 'R');
			$pdf->SetFont('', 'B', $fontsize + 1);
			$pdf->SetX($this->marge_gauche + 90);
			$pdf->MultiCell($usablewidth - 90, 6, $outputlangs->convToOutputCharset(($soc && $soc->name ? $soc->name.' - ' : '').$this->headerref), 0, 'R');
			$pdf->SetFont('', '', $fontsize - 1);
			$pdf->SetX($this->marge_gauche + 90);
			$pdf->MultiCell($usablewidth - 90, 5, $outputlangs->transnoentities('DossierPdfGenerated', dol_print_date(dol_now(), 'dayhour', 'tzuser', $outputlangs)), 0, 'R');
			$posy = max($pdf->GetY(), $this->marge_haute + 22) + 3;
		} else {
			$pdf->SetFont('', '', $fontsize - 2);
			$pdf->SetTextColor(90, 90, 90);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell($usablewidth, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('DossierPdfTitle').' '.$this->headerref), 0, 'R');
			$pdf->SetTextColor(0, 0, 0);
			$posy = $pdf->GetY() + 1;
		}
		$pdf->Line($this->marge_gauche, $posy, $this->page_largeur - $this->marge_droite, $posy);
		$this->posy = $posy + 4;
	}

	/**
	 * New page when the next block does not fit
	 *
	 * @param	float	$height		Height of the next block
	 * @return	bool				True when a page was added
	 */
	protected function ensureSpace($height)
	{
		if ($this->posy + $height > $this->page_hauteur - $this->marge_basse - 10) {
			$this->newPage();
			return true;
		}
		return false;
	}

	/**
	 * Title of a section
	 *
	 * @param	string	$title	Title
	 * @return	void
	 */
	protected function printSection($title)
	{
		$pdf = $this->pdf;
		$this->ensureSpace(20);
		$pdf->SetFont('', 'B', $this->fontsize);
		$pdf->SetFillColor(230, 236, 230);
		$pdf->SetXY($this->marge_gauche, $this->posy);
		$pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 6, $this->outputlangs->convToOutputCharset($title), 0, 'L', true);
		$this->posy = $pdf->GetY() + 2;
	}

	/**
	 * Free text
	 *
	 * @param	string	$text	Text
	 * @return	void
	 */
	protected function printText($text)
	{
		$pdf = $this->pdf;
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$pdf->SetFont('', '', $this->fontsize - 1);
		foreach (preg_split('/\R/', $text) as $line) {
			$height = max(5, $pdf->getStringHeight($width, $line));
			$this->ensureSpace($height);
			$pdf->SetXY($this->marge_gauche, $this->posy);
			$pdf->MultiCell($width, 5, $this->outputlangs->convToOutputCharset($line), 0, 'L');
			$this->posy = $pdf->GetY();
		}
		$this->posy += 4;
	}

	/**
	 * Label / value pairs on two columns
	 *
	 * @param	array<array{0:string,1:string}>	$rows	Rows
	 * @return	void
	 */
	protected function printPairs($rows)
	{
		$pdf = $this->pdf;
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$labelwidth = 60;
		foreach ($rows as $row) {
			$pdf->SetFont('', '', $this->fontsize - 1);
			$height = max(5, $pdf->getStringHeight($width - $labelwidth, $row[1]));
			$this->ensureSpace($height);
			$pdf->SetTextColor(90, 90, 90);
			$pdf->SetXY($this->marge_gauche, $this->posy);
			$pdf->MultiCell($labelwidth, 5, $this->outputlangs->convToOutputCharset($row[0]), 0, 'L');
			$labelbottom = $pdf->GetY();
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($this->marge_gauche + $labelwidth, $this->posy);
			$pdf->MultiCell($width - $labelwidth, 5, $this->outputlangs->convToOutputCharset($row[1]), 0, 'L');
			$this->posy = max($labelbottom, $pdf->GetY()) + 0.5;
		}
		$this->posy += 4;
	}

	/**
	 * Table with a header repeated on each page and an optional total row. Widths of the columns are relative.
	 *
	 * @param	array<array{0:string,1:float,2:string}>	$cols		Columns: label, relative width, alignment
	 * @param	array<string[]>							$rows		Rows
	 * @param	?string[]								$total		Total row
	 * @return	void
	 */
	protected function printTable($cols, $rows, $total = null)
	{
		$pdf = $this->pdf;
		$usablewidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$sum = array_sum(array_column($cols, 1));
		$widths = array();
		foreach ($cols as $i => $col) {
			$widths[$i] = $usablewidth * $col[1] / $sum;
		}
		$fontsize = $this->fontsize - 2;

		$printrow = function ($cells, $style, $fill) use ($pdf, $cols, $widths, $fontsize) {
			$pdf->SetFont('', $style, $fontsize);
			$height = 4.5;
			foreach ($cells as $i => $cell) {
				$height = max($height, $pdf->getStringHeight($widths[$i], (string) $cell) + 0.5);
			}
			return $height;
		};
		$draw = function ($cells, $style, $fill, $height) use ($pdf, $cols, $widths, $fontsize) {
			$pdf->SetFont('', $style, $fontsize);
			if ($fill) {
				$pdf->SetFillColor(242, 242, 242);
			}
			$x = $this->marge_gauche;
			foreach ($cells as $i => $cell) {
				$pdf->SetXY($x, $this->posy);
				$pdf->MultiCell($widths[$i], $height, $this->outputlangs->convToOutputCharset((string) $cell), 'B', $cols[$i][2], $fill, 1, '', '', true, 0, false, true, $height, 'M');
				$x += $widths[$i];
			}
			$this->posy += $height;
		};

		$header = array_column($cols, 0);
		$headerheight = $printrow($header, 'B', true);
		$this->ensureSpace($headerheight + 5);
		$draw($header, 'B', true, $headerheight);
		foreach ($rows as $row) {
			$height = $printrow($row, '', false);
			if ($this->ensureSpace($height)) {
				$draw($header, 'B', true, $headerheight);
			}
			$draw($row, '', false, $height);
		}
		if ($total) {
			$height = $printrow($total, 'B', true);
			if ($this->ensureSpace($height)) {
				$draw($header, 'B', true, $headerheight);
			}
			$draw($total, 'B', true, $height);
		}
		$this->posy += 5;
	}

	/**
	 * Footer of all the pages: farm and page number
	 *
	 * @param	Societe	$soc	Farm
	 * @return	void
	 */
	protected function printFooters($soc)
	{
		$pdf = $this->pdf;
		$nb = $pdf->getNumPages();
		$usablewidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		for ($i = 1; $i <= $nb; $i++) {
			$pdf->setPage($i);
			$pdf->SetFont('', '', $this->fontsize - 3);
			$pdf->SetTextColor(90, 90, 90);
			$pdf->SetXY($this->marge_gauche, $this->page_hauteur - $this->marge_basse - 5);
			$text = $this->outputlangs->transnoentities('DossierPdfTitle').' '.$this->headerref.($soc->name ? ' - '.$soc->name : '');
			$pdf->MultiCell($usablewidth * 0.75, 4, $this->outputlangs->convToOutputCharset($text), 0, 'L');
			$pdf->SetXY($this->marge_gauche + $usablewidth * 0.75, $this->page_hauteur - $this->marge_basse - 5);
			$pdf->MultiCell($usablewidth * 0.25, 4, $this->outputlangs->transnoentities('DossierPdfPage', $i, $nb), 0, 'R');
			$pdf->SetTextColor(0, 0, 0);
		}
	}

	/**
	 * Surface in hectares, '' when empty
	 *
	 * @param	mixed	$value	Surface
	 * @return	string
	 */
	protected function ha($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return price((float) $value, 0, $this->outputlangs, 0, 4, 4);
	}

	/**
	 * Date of the database as text, '' when empty
	 *
	 * @param	mixed	$value	Date of the database
	 * @return	string
	 */
	protected function day($value)
	{
		$date = $this->db->jdate($value);
		return ($date ? dol_print_date($date, 'day', false, $this->outputlangs) : '');
	}

	/**
	 * Label of a dictionary entry, translated when it is a key
	 *
	 * @param	mixed	$label	Label
	 * @return	string
	 */
	protected function dictLabel($label)
	{
		$label = (string) $label;
		return ($label === '' ? '' : $this->outputlangs->transnoentities($label));
	}

	/**
	 * Plots of the dossier
	 *
	 * @param	DoliFarmFarmDossier	$object	Dossier
	 * @return	array<int,array<string,string>>
	 */
	protected function getPlots($object)
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT p.ref, p.label, p.cadastral_comune, p.cadastral_section, p.cadastral_sheet, p.cadastral_parcel, p.cadastral_sub,";
		$sql .= " p.date_conduction_end, p.size_cadastral, p.size_conducted, p.size_sau, o.label as ownership, b.label as bio";
		$sql .= " FROM ".$prefix."dolifarm_plot as p";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_c_ownership as o ON o.code = p.fk_ownership";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_c_biostatus as b ON b.code = p.fk_status_bio";
		$sql .= " WHERE p.fk_dossier = ".((int) $object->id)." AND p.entity IN (".getEntity('dolifarmplot').")";
		$sql .= " ORDER BY p.cadastral_comune, p.cadastral_sheet, p.cadastral_parcel, p.ref";
		$rows = array();
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'ref' => (string) $obj->ref, 'label' => (string) $obj->label, 'comune' => (string) $obj->cadastral_comune,
				'section' => (string) $obj->cadastral_section, 'sheet' => (string) $obj->cadastral_sheet, 'parcel' => (string) $obj->cadastral_parcel,
				'sub' => (string) $obj->cadastral_sub, 'title' => $this->dictLabel($obj->ownership), 'conduction_end' => $this->day($obj->date_conduction_end),
				'size_cadastral' => $obj->size_cadastral, 'size_conducted' => $obj->size_conducted, 'size_sau' => $obj->size_sau,
				'bio' => $this->dictLabel($obj->bio),
			);
		}
		return $rows;
	}

	/**
	 * Machines of the dossier, as rows of the table
	 *
	 * @param	DoliFarmFarmDossier	$object	Dossier
	 * @return	array<string[]>
	 */
	protected function getMachines($object)
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT m.ref, m.label, t.label as type, m.power_kw, f.label as fuel, m.year_registration, m.date_revision_next, m.date_insurance_end, m.date_sprayer_check_end";
		$sql .= " FROM ".$prefix."dolifarm_machine as m";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_c_machinetype as t ON t.code = m.fk_type";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_c_fueltype as f ON f.code = m.fuel_type";
		$sql .= " WHERE m.fk_dossier = ".((int) $object->id)." AND m.entity IN (".getEntity('dolifarmmachine').")";
		$sql .= " ORDER BY m.ref";
		$rows = array();
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				$obj->ref.($obj->label ? ' - '.$obj->label : ''), $this->dictLabel($obj->type),
				($obj->power_kw > 0 ? price($obj->power_kw, 0, $this->outputlangs, 0, -1, -1) : ''), $this->dictLabel($obj->fuel),
				($obj->year_registration > 0 ? (string) $obj->year_registration : ''), $this->day($obj->date_revision_next),
				$this->day($obj->date_insurance_end), $this->day($obj->date_sprayer_check_end),
			);
		}
		return $rows;
	}

	/**
	 * Buildings of the dossier, as rows of the table
	 *
	 * @param	DoliFarmFarmDossier	$object	Dossier
	 * @return	array<string[]>
	 */
	protected function getBuildings($object)
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT b.ref, b.label, b.building_use, o.label as ownership, b.cadastral_comune, b.cadastral_sheet, b.cadastral_parcel, b.cadastral_sub, b.surface_covered, b.volume";
		$sql .= " FROM ".$prefix."dolifarm_building as b";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_c_ownership as o ON o.code = b.fk_ownership";
		$sql .= " WHERE b.fk_dossier = ".((int) $object->id)." AND b.entity IN (".getEntity('dolifarmbuilding').")";
		$sql .= " ORDER BY b.ref";
		$rows = array();
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				$obj->ref.($obj->label ? ' - '.$obj->label : ''), (string) $obj->building_use, $this->dictLabel($obj->ownership), (string) $obj->cadastral_comune,
				(string) $obj->cadastral_sheet, (string) $obj->cadastral_parcel, (string) $obj->cadastral_sub,
				($obj->surface_covered > 0 ? price($obj->surface_covered, 0, $this->outputlangs, 0, -1, -1) : ''),
				($obj->volume > 0 ? price($obj->volume, 0, $this->outputlangs, 0, -1, -1) : ''),
			);
		}
		return $rows;
	}

	/**
	 * Livestock of the dossier, as rows of the table
	 *
	 * @param	DoliFarmFarmDossier	$object	Dossier
	 * @return	array<string[]>
	 */
	protected function getLivestock($object)
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT l.ref, l.label, l.bdn_code, s.label as species, l.nb_heads, l.production_type, l.organic, l.town";
		$sql .= " FROM ".$prefix."dolifarm_livestock as l";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_c_livestock_species as s ON s.code = l.fk_species";
		$sql .= " WHERE l.fk_dossier = ".((int) $object->id)." AND l.entity IN (".getEntity('dolifarmlivestock').")";
		$sql .= " ORDER BY l.ref";
		$rows = array();
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				$obj->ref.($obj->label ? ' - '.$obj->label : ''), (string) $obj->bdn_code, $this->dictLabel($obj->species),
				($obj->nb_heads !== null ? (string) $obj->nb_heads : ''), (string) $obj->production_type,
				$this->outputlangs->transnoentities($obj->organic ? 'Yes' : 'No'), (string) $obj->town,
			);
		}
		return $rows;
	}

	/**
	 * Production plans of the last season of the dossier (doliTrace), as rows of the table
	 *
	 * @param	DoliFarmFarmDossier	$object	Dossier
	 * @param	int					$season	Season of the plans (output)
	 * @return	array<string[]>
	 */
	protected function getCropPlans($object, &$season)
	{
		$prefix = $this->db->prefix();
		$rows = array();
		$where = "(cp.fk_dossier = ".((int) $object->id)." OR ((cp.fk_dossier IS NULL OR cp.fk_dossier = 0) AND cp.fk_plot IN (SELECT rowid FROM ".$prefix."dolifarm_plot WHERE fk_dossier = ".((int) $object->id).")))";
		$where .= " AND cp.entity IN (".getEntity('dolitracecropplan').")";
		$resql = $this->db->query("SELECT MAX(cp.season) as season FROM ".$prefix."dolitrace_cropplan as cp WHERE ".$where);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		$season = ($obj && $obj->season > 0) ? (int) $obj->season : 0;
		if (!$season) {
			return $rows;
		}
		dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
		$this->outputlangs->loadLangs(array('dolitrace@dolitrace'));
		$static = class_exists('DoliTraceCropplan') ? new DoliTraceCropplan($this->db) : null;
		$sql = "SELECT cp.ref, cp.label, cp.date_start, cp.date_end, cp.status, p.ref as plot_ref, p.label as plot_label, p.size_sau, p.size_conducted, c.label as crop";
		$sql .= " FROM ".$prefix."dolitrace_cropplan as cp";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_plot as p ON p.rowid = cp.fk_plot";
		$sql .= " LEFT JOIN ".$prefix."dolifarm_crops as c ON c.rowid = cp.fk_crop";
		$sql .= " WHERE ".$where." AND cp.season = ".$season;
		$sql .= " ORDER BY p.ref, cp.date_start";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$period = trim($this->day($obj->date_start).' - '.$this->day($obj->date_end), ' -');
			$status = ($static ? trim(dol_string_nohtmltag($static->LibStatut((int) $obj->status, 0), 1)) : (string) $obj->status);
			$rows[] = array(
				$obj->ref.($obj->label ? ' - '.$obj->label : ''), trim($obj->plot_ref.($obj->plot_label ? ' - '.$obj->plot_label : '')),
				$this->dictLabel($obj->crop), $period, $this->ha($obj->size_sau !== null ? $obj->size_sau : $obj->size_conducted), $status,
			);
		}
		return $rows;
	}

	/**
	 * Value of a field of the dossier as plain text, '' if empty or not enabled
	 *
	 * @param	DoliFarmFarmDossier	$object			Dossier
	 * @param	string				$key			Field key
	 * @param	Translate			$outputlangs	Lang output object
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
		if (in_array($val['type'], array('date', 'datetime'))) {
			return dol_print_date($value, ($val['type'] == 'date' ? 'day' : 'dayhour'), false, $outputlangs);
		}
		if ($val['type'] == 'price') {
			return price($value, 0, $outputlangs, 0, -1, -1, 'auto');
		}
		if (!empty($val['arrayofkeyval'])) {
			return isset($val['arrayofkeyval'][$value]) ? $outputlangs->transnoentities($val['arrayofkeyval'][$value]) : (string) $value;
		}
		$text = $object->showOutputField($val, $key, $value, '', '', '', 0);
		return trim(dol_string_nohtmltag($text, 1));
	}
}
