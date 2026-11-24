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
 * \file        class/dolifarmimporter.class.php
 * \ingroup     dolifarm
 * \brief       Import of a farm dossier (fascicolo aziendale) into doliFarm
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
dol_include_once('/dolifarm/class/dolifarmplot.class.php');
dol_include_once('/dolifarm/class/dolifarmmachine.class.php');
dol_include_once('/dolifarm/class/dolifarmlivestock.class.php');
dol_include_once('/dolifarm/class/dolifarmbuilding.class.php');
dol_include_once('/dolifarm/class/dolifarmfascicoloxmlreader.class.php');
dol_include_once('/dolifarm/class/dolifarmfascicolopdfreader.class.php');
dol_include_once('/dolifarm/class/dolifarmdossierdoc.class.php');
dol_include_once('/dolifarm/lib/dolifarm_landuse.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');
dol_include_once('/dolifarm/lib/dolifarm.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

/**
 * Write a farm dossier, read by a reader (DoliFarmFascicoloXmlReader...), into the doliFarm objects.
 *
 * Every object is searched before being created, so that importing the same file twice, or the sheet of the next
 * year, updates the existing records instead of duplicating them:
 * - farm: CUAA (idprof4), then VAT number;
 * - dossier: farm + number of the validation sheet;
 * - plot: farm + comune, section, sheet, parcel, sub (the plot is moved to the latest dossier);
 * - building: farm + cadastral reference; livestock: farm + BDN code + address;
 * - machine: farm + plate, chassis or brand/model/year; bank account: farm + IBAN.
 */
class DoliFarmImporter
{
	/** @var DoliDB */
	private $db;

	/** @var User */
	private $user;

	/** @var string Error message */
	public $error = '';

	/** @var string[] Detailed errors */
	public $errors = array();

	/** @var string[] Messages of the import, for the user */
	public $output_log = array();

	/** @var string[] Warnings: data not imported or to check */
	public $warnings = array();

	/** @var array<string,array{created:int,updated:int}> Counters by kind of object */
	public $stats = array();

	/** @var string Picto of the importer */
	public $picto;

	/** @var string Import key written on the records created or updated */
	private $importKey = '';

	/** @var int Id of the farm (third party) */
	private $socid = 0;

	/** @var string CUAA of the farm */
	private $cuaa = '';

	/** Land uses that are not agricultural area (SAU): wood, buildings, non agricultural use, landscape features */
	const NON_SAU_LAND_USES = array('650', '660', '780', '785', '788');

	/** @var string Name of the farm in the file */
	private $farmName = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB	$db		Database handler
	 * @param User		$user	User importing
	 */
	public function __construct($db, $user)
	{
		$this->db = $db;
		$this->user = $user;

		dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');
		$this->picto = dolifarm_get_fa_icon('importer');
	}

	/**
	 * Read a dossier file and import it: XML in the doliFarm format, or PDF of the validation sheet printed by the SIAN
	 *
	 * @param	string	$filepath	Path of the file
	 * @param	bool	$dryrun		true = simulate: everything is done then rolled back (preview)
	 * @param	string	$filename	Original name of the file, stored on the dossier
	 * @return	int					Id of the dossier (0 in dry run), <0 if KO
	 */
	public function importFromFile($filepath, $dryrun = false, $filename = '')
	{
		$head = (string) @file_get_contents($filepath, false, null, 0, 5);
		$reader = ($head === '%PDF-') ? new DoliFarmFascicoloPdfReader() : new DoliFarmFascicoloXmlReader();
		$data = $reader->read($filepath);
		if ($data === null) {
			$this->error = $reader->error;
			$this->errors = $reader->errors;
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}
		$res = $this->import($data, $dryrun, $filename !== '' ? $filename : basename($filepath));
		// Warnings of the reader (data of the file not read) first
		$this->warnings = array_merge($reader->warnings, $this->warnings);
		return $res;
	}

	/**
	 * Read a file in the doliFarm XML format and import it (kept for the callers of older versions)
	 *
	 * @param	string	$filepath	Path of the file
	 * @param	bool	$dryrun		true = simulate
	 * @param	string	$filename	Original name of the file
	 * @return	int					Id of the dossier (0 in dry run), <0 if KO
	 */
	public function importFromXML($filepath, $dryrun = false, $filename = '')
	{
		return $this->importFromFile($filepath, $dryrun, $filename);
	}

	/**
	 * Import a dossier read by a reader
	 *
	 * @param	array<string,mixed>	$data		Neutral structure returned by a reader
	 * @param	bool				$dryrun		true = simulate and roll back
	 * @param	string				$filename	Name of the source file
	 * @return	int								Id of the dossier (0 in dry run), <0 if KO
	 */
	public function import(array $data, $dryrun = false, $filename = '')
	{
		global $langs;

		$langs->loadLangs(array('errors', 'companies', 'dolifarm@dolifarm'));
		$this->output_log = array();
		$this->warnings = array();
		$this->stats = array();
		$this->importKey = dol_print_date(dol_now(), 'dayhourlog');
		$this->farmName = (string) $data['anagrafica']['name'];

		$this->db->begin();
		try {
			$this->socid = $this->importFarm($data['anagrafica']);
			$this->importRepresentatives($data['anagrafica']['representatives']);
			$caa = $this->importCaa($data['scheda']);
			$bio = $this->importOrganisms($data['organismi']);
			$dossier = $this->importDossier($data, $caa, $bio, $filename);
			if ($caa['contactid'] > 0) {
				$this->linkDossierContact($dossier, $caa['contactid'], 'CAAOPERATOR');
			}
			$plots = $this->importPlots($data['particelle'], $dossier->id);
			$this->importGraphicPlan($data, $dossier->id);
			$this->importDocuments($data['documenti'], $dossier->id);
			$this->importCropPlans($data, $dossier, $plots);
			$this->importBuildings($data['fabbricati'], $dossier->id, $plots);
			$this->importLivestock($data['allevamenti'], $dossier->id);
			$this->importMachines($data['macchine'], $dossier->id);
			$this->importBankAccounts($data['anagrafica']['name'], $data['conti']);
			$this->reportNotImported($data);
		} catch (Exception $e) {
			$this->db->rollback();
			$this->error = $e->getMessage();
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}

		if ($dryrun) {
			$this->db->rollback();
			return 0;
		}
		$this->db->commit();
		dol_syslog(__METHOD__.' dossier '.$dossier->id.' imported from '.$filename, LOG_INFO);
		return $dossier->id;
	}

	// ------------------------------------------------------------------------------------------------
	// Farm and people
	// ------------------------------------------------------------------------------------------------

	/**
	 * Find the farm by CUAA, then by VAT number; create it, or fill its empty fields
	 *
	 * @param	array<string,mixed>	$a	Section anagrafica
	 * @return	int						Id of the third party
	 */
	private function importFarm(array $a)
	{
		global $langs;

		$cuaa = self::normalizeCuaa($a['cuaa']);
		if (!self::isValidTaxCode($cuaa)) {
			throw new Exception($langs->transnoentitiesnoconv('DossierImportCuaaInvalid', $cuaa));
		}
		$vat = '';
		foreach ($a['vat'] as $v) {
			if (!self::isValidVat($v)) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportVatInvalid', $v));
			}
			if ($vat === '') {
				$vat = $v;
			}
		}
		$this->cuaa = $cuaa;

		$socid = $this->findThirdparty($cuaa, $vat);
		$multifarm = getDolGlobalInt('DOLIFARM_MULTIFARM');
		if ($socid > 0 && !in_array($socid, dolifarmManagedFarmIds(true), true)) {
			throw new Exception($langs->transnoentitiesnoconv($multifarm ? 'DossierImportFarmNotAffiliated' : 'DossierImportFarmNotConfigured', $a['name'], $cuaa));
		}
		if ($socid == 0 && !$multifarm) {
			// Single-farm mode: the dossier must be the one of the farm of the installation
			throw new Exception($langs->transnoentitiesnoconv('DossierImportFarmNotConfigured', $a['name'], $cuaa));
		}
		$soc = new Societe($this->db);
		$new = ($socid == 0);
		if (!$new) {
			$soc->fetch($socid);
		} else {
			// Multi-farm mode: the new farm is affiliated, the trigger gives it a draft dossier and its warehouses
			$soc->array_options['options_dolifarm_affiliated'] = 1;
			$soc->name = $a['name'];
			$soc->client = 1;
			$soc->code_client = -1;
			$soc->country_id = $this->countryId('IT');
			$soc->status = 1;
			$typentid = dolifarmGetTypentId($this->db, 'TE_FARM');
			if ($typentid > 0) {
				$soc->typent_id = $typentid;
			}
		}

		// Fill only the empty fields: the data typed in Dolibarr wins over the file
		$changed = array();
		$fill = function ($field, $value) use ($soc, &$changed) {
			if ($value !== '' && $value !== null && empty($soc->$field)) {
				$soc->$field = $value;
				$changed[] = $field;
			}
		};
		$fill('idprof4', $cuaa);
		$fill('tva_intra', $vat);
		$fill('address', $a['address']);
		$fill('zip', $a['zip']);
		$fill('town', $a['town']['name']);
		$fill('state_id', $this->stateId($a['town']['province']));
		$fill('email', $a['email'] !== '' ? $a['email'] : $a['pec']);
		$fill('phone', $a['phone']);
		$fill('forme_juridique_code', self::legalFormCode($a['legal_form']));

		if ($new) {
			$soc->import_key = $this->importKey;
			if ($soc->create($this->user) < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportThirdPartyError', $a['name']).': '.$soc->error.' '.implode(', ', $soc->errors));
			}
			dolifarmManagedFarmIds(true);
			$this->count('thirdparty', true);
			$this->log($langs->transnoentitiesnoconv('DossierImportFarmCreated', $soc->name, $cuaa));
			return (int) $soc->id;
		}
		if ($changed) {
			if ($soc->update($soc->id, $this->user) < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportThirdPartyError', $soc->name).': '.$soc->error.' '.implode(', ', $soc->errors));
			}
			$this->count('thirdparty', false);
		}
		$this->log($langs->transnoentitiesnoconv('DossierImportFarmFound', $soc->name, $cuaa).($changed ? ' ('.implode(', ', $changed).')' : ''));
		return (int) $soc->id;
	}

	/**
	 * Create the legal representatives as contacts of the farm, when missing
	 *
	 * @param	array<int,array<string,mixed>>	$representatives	Representatives
	 * @return	void
	 */
	private function importRepresentatives(array $representatives)
	{
		global $langs;

		foreach ($representatives as $r) {
			$contactid = $this->findOrCreateContact($this->socid, $r['lastname'], $r['firstname'], $langs->transnoentitiesnoconv('DossierImportLegalRepresentative'), $r['birthday']);
			if ($contactid > 0) {
				$this->log($langs->transnoentitiesnoconv('DossierImportLegalRepresentative').': '.trim($r['firstname'].' '.$r['lastname']).' ('.$r['cf'].')');
			}
		}
	}

	/**
	 * CAA of the mandate (third party of type TE_CAA) and its operator (contact)
	 *
	 * @param	array<string,string>	$s	Section scheda
	 * @return	array{socid:int,contactid:int}
	 */
	private function importCaa(array $s)
	{
		global $langs;

		$res = array('socid' => 0, 'contactid' => 0);
		if ($s['caa'] === '') {
			return $res;
		}
		$res['socid'] = $this->findOrCreateOrganisation($s['caa'], '', 'TE_CAA');
		if ($s['caa_operator'] !== '') {
			// The validation sheet prints "SURNAME NAME" in one field: it is kept as surname
			$res['contactid'] = $this->findOrCreateContact($res['socid'], $s['caa_operator'], '', $langs->transnoentitiesnoconv('DossierImportCaaOperator'), '');
		}
		return $res;
	}

	/**
	 * Associated organisations: the organic control body is linked to the dossier, the others are only reported
	 *
	 * @param	array<int,array<string,string>>	$organisms	Organisations
	 * @return	array{socid:int,date:string}		Organic control body
	 */
	private function importOrganisms(array $organisms)
	{
		global $langs;

		$res = array('socid' => 0, 'date' => '');
		foreach ($organisms as $o) {
			if ($res['socid'] == 0 && stripos($o['type'], 'BIOLOGIC') !== false) {
				$res['socid'] = $this->findOrCreateOrganisation($o['name'], $o['cf'], 'TE_BIOCTRL');
				$res['date'] = $o['date_start'];
			} else {
				$this->warn($langs->transnoentitiesnoconv('DossierImportOrganismSkipped', $o['name'], $o['type']));
			}
		}
		return $res;
	}

	/**
	 * Third party searched by tax code, then by name; created with the given type when missing
	 *
	 * @param	string	$name		Name
	 * @param	string	$cf			Tax code, '' if unknown
	 * @param	string	$typecode	Code of the type of third party (c_typent), '' for none
	 * @return	int					Id of the third party
	 */
	private function findOrCreateOrganisation($name, $cf, $typecode)
	{
		global $langs;

		$cf = self::normalizeCuaa($cf);
		$socid = 0;
		if ($cf !== '') {
			$socid = $this->findThirdparty($cf, preg_match('/^[0-9]{11}$/', $cf) ? $cf : '');
		}
		if ($socid == 0) {
			$sql = "SELECT s.rowid FROM ".$this->db->prefix()."societe as s";
			$sql .= " WHERE UPPER(s.nom) = '".$this->db->escape(strtoupper($name))."' AND s.entity IN (".getEntity('societe').")";
			$socid = $this->singleId($sql);
		}
		if ($socid > 0) {
			return $socid;
		}

		$soc = new Societe($this->db);
		$soc->name = $name;
		$soc->idprof4 = $cf;
		if (preg_match('/^[0-9]{11}$/', $cf)) {
			$soc->tva_intra = $cf;
		}
		$soc->client = 0;
		$soc->fournisseur = 0;
		$soc->status = 1;
		$soc->country_id = $this->countryId('IT');
		$soc->import_key = $this->importKey;
		if ($typecode !== '') {
			$typentid = dolifarmGetTypentId($this->db, $typecode);
			if ($typentid > 0) {
				$soc->typent_id = $typentid;
			}
		}
		if ($soc->create($this->user) < 0) {
			throw new Exception($langs->transnoentitiesnoconv('DossierImportThirdPartyError', $name).': '.$soc->error.' '.implode(', ', $soc->errors));
		}
		$this->count('thirdparty', true);
		$this->log($langs->transnoentitiesnoconv('DossierImportThirdPartyCreated', $name));
		return (int) $soc->id;
	}

	/**
	 * Contact of a third party searched by name; created when missing
	 *
	 * @param	int		$socid		Third party
	 * @param	string	$lastname	Surname
	 * @param	string	$firstname	Name
	 * @param	string	$job		Job position
	 * @param	string	$birthday	Date of birth (YYYY-MM-DD), '' if unknown
	 * @return	int					Id of the contact
	 */
	private function findOrCreateContact($socid, $lastname, $firstname, $job, $birthday)
	{
		global $langs;

		$sql = "SELECT c.rowid FROM ".$this->db->prefix()."socpeople as c";
		$sql .= " WHERE c.fk_soc = ".((int) $socid);
		$sql .= " AND UPPER(c.lastname) = '".$this->db->escape(strtoupper($lastname))."'";
		$sql .= " AND UPPER(COALESCE(c.firstname, '')) = '".$this->db->escape(strtoupper($firstname))."'";
		$sql .= " AND c.entity IN (".getEntity('contact').")";
		$id = $this->singleId($sql);
		if ($id > 0) {
			return $id;
		}

		$contact = new Contact($this->db);
		$contact->socid = $socid;
		$contact->lastname = $lastname;
		$contact->firstname = $firstname;
		$contact->poste = $job;
		// Empty strings: Contact::create() trims them
		foreach (array('name_alias', 'email', 'phone_pro', 'phone_perso', 'phone_mobile', 'photo', 'fax') as $field) {
			$contact->$field = '';
		}
		$contact->status = 1;
		$contact->import_key = $this->importKey;
		if ($birthday !== '') {
			$contact->birthday = self::date($birthday);
		}
		$id = $contact->create($this->user);
		if ($id < 0) {
			throw new Exception($langs->transnoentitiesnoconv('DossierImportContactError', $lastname).': '.$contact->error.' '.implode(', ', $contact->errors));
		}
		$this->count('contact', true);
		return (int) $id;
	}

	// ------------------------------------------------------------------------------------------------
	// Dossier
	// ------------------------------------------------------------------------------------------------

	/**
	 * Create the dossier, or update the one with the same sheet number
	 *
	 * @param	array<string,mixed>			$data		Whole dossier
	 * @param	array{socid:int,contactid:int}	$caa	CAA
	 * @param	array{socid:int,date:string}	$bio	Organic control body
	 * @param	string						$filename	Source file
	 * @return	DoliFarmFarmDossier
	 */
	private function importDossier(array $data, array $caa, array $bio, $filename)
	{
		global $langs;

		$s = $data['scheda'];
		$dossier = new DoliFarmFarmDossier($this->db);
		$sql = "SELECT t.rowid FROM ".$this->db->prefix()."dolifarm_farmdossier as t";
		$sql .= " WHERE t.fk_soc = ".((int) $this->socid)." AND t.sheet_number = '".$this->db->escape($s['number'])."'";
		$sql .= " AND t.entity IN (".getEntity($dossier->element).")";
		$id = $this->singleId($sql);
		if ($id == 0) {
			// Draft dossier without sheet (e.g. created when the farm was affiliated): it becomes this one
			$sql = "SELECT t.rowid FROM ".$this->db->prefix()."dolifarm_farmdossier as t";
			$sql .= " WHERE t.fk_soc = ".((int) $this->socid)." AND t.status = ".DoliFarmFarmDossier::STATUS_DRAFT;
			$sql .= " AND (t.sheet_number IS NULL OR t.sheet_number = '') AND t.entity IN (".getEntity($dossier->element).")";
			$sql .= " ORDER BY t.rowid DESC".$this->db->plimit(1);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$id = (int) $obj->rowid;
			}
		}
		if ($id > 0) {
			$dossier->fetch($id);
		}

		$dossier->fk_soc = $this->socid;
		$dossier->sheet_number = $s['number'];
		$dossier->protocol = $s['protocol'];
		$dossier->act_type = ($s['act_type'] === 'ISCRIZIONE' ? 1 : ($s['act_type'] === 'AGGIORNAMENTO' ? 2 : null));
		$dossier->paying_agency = $this->dictionaryCode('dolifarm_c_paying_agency', $s['paying_agency']);
		if ($dossier->paying_agency === null) {
			$this->warn($langs->transnoentitiesnoconv('DossierImportPayingAgencyUnknown', $s['paying_agency']));
		}
		$dossier->year_validity = (int) $data['campagna'];
		$dossier->date_reference = self::date($s['date_reference']);
		$dossier->fk_soc_caa = $caa['socid'] ?: null;
		$dossier->date_mandate = self::date($s['date_mandate']);
		$dossier->ote_code = $data['ote']['code'] !== '' ? $data['ote']['code'] : null;
		$dossier->economic_size = $data['ote']['economic_size'];
		if ($bio['socid'] > 0) {
			$dossier->fk_soc_bio_body = $bio['socid'];
			$dossier->date_bio_membership = self::date($bio['date']);
		}
		// The signature date is required: without it, the print date of the sheet, then today
		if ($s['date_signature'] !== '') {
			$dossier->date_signature = self::date($s['date_signature']);
		} elseif (empty($dossier->date_signature)) {
			$dossier->date_signature = $s['date_print'] !== '' ? self::date($s['date_print']) : dol_now();
			$this->warn($langs->transnoentitiesnoconv('DossierImportNoSignatureDate', dol_print_date($dossier->date_signature, 'day')));
		}
		$dossier->date_import = dol_now();
		$dossier->source_file = dol_trunc($filename, 250, 'right', 'UTF-8', 1);
		$dossier->import_key = $this->importKey;

		if ($id > 0) {
			if ($dossier->update($this->user) < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportDossierError').': '.$dossier->error.' '.implode(', ', $dossier->errors));
			}
			$this->count('dossier', false);
			$this->log($langs->transnoentitiesnoconv('DossierImportDossierUpdated', $dossier->ref, $s['number']));
		} else {
			$dossier->status = DoliFarmFarmDossier::STATUS_DRAFT;
			if ($dossier->create($this->user) < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportDossierError').': '.$dossier->error.' '.implode(', ', $dossier->errors));
			}
			$this->count('dossier', true);
			$this->log($langs->transnoentitiesnoconv('DossierImportDossierCreated', $dossier->ref, $s['number']));
		}
		return $dossier;
	}

	/**
	 * Link a contact to the dossier with a role, if not already linked
	 *
	 * @param	DoliFarmFarmDossier	$dossier	Dossier
	 * @param	int					$contactid	Contact
	 * @param	string				$role		Code of the type of contact
	 * @return	void
	 */
	private function linkDossierContact($dossier, $contactid, $role)
	{
		$linked = $dossier->liste_contact(-1, 'external');
		foreach (is_array($linked) ? $linked : array() as $c) {
			if ((int) $c['id'] == $contactid && $c['code'] == $role) {
				return;
			}
		}
		if ($dossier->add_contact($contactid, $role, 'external') < 0) {
			$this->warn($dossier->error);
		}
	}

	// ------------------------------------------------------------------------------------------------
	// Land, buildings, livestock, machines, bank
	// ------------------------------------------------------------------------------------------------

	/**
	 * Cadastral parcels of the dossier, as plots
	 *
	 * @param	array<string,array<string,mixed>>	$parcels	Parcels by id
	 * @param	int									$dossierid	Dossier
	 * @return	array<string,int>							Id of the plot by cadastral key
	 */
	private function importPlots(array $parcels, $dossierid)
	{
		global $langs;

		$ids = array();
		foreach ($parcels as $p) {
			$key = self::cadastralKey($p['comune']['name'], $p['section'], $p['sheet'], $p['parcel'], $p['sub']);
			$label = self::cadastralLabel($p['comune']['name'], $p['sheet'], $p['parcel'], $p['sub']);
			$plot = new DoliFarmPlot($this->db);
			$id = $this->findByCadastralKey('dolifarm_plot', $plot->element, $key);
			$new = ($id == 0);
			if (!$new) {
				$plot->fetch($id);
			} else {
				$plot->label = $label;
				$plot->status = 1;
			}

			$plot->fk_soc = $this->socid;
			$plot->fk_dossier = $dossierid;
			$plot->cadastral_comune = $p['comune']['name'];
			$plot->cadastral_section = $p['section'];
			$plot->cadastral_sheet = $p['sheet'];
			$plot->cadastral_parcel = $p['parcel'];
			$plot->cadastral_sub = $p['sub'];
			$plot->fk_ownership = $this->ownershipFromCode($p['conduction']['code'], $p['conduction']['subcode']);
			if ($plot->fk_ownership === null && empty($p['conduction']['code']) && $p['conduction']['label'] !== '') {
				// Only the description (PDF of the SIAN)
				$text = self::ownershipFromText($p['conduction']['label']);
				$plot->fk_ownership = $text === null ? null : $this->dictionaryCode('dolifarm_c_ownership', $text);
			}
			if ($plot->fk_ownership === null) {
				$this->warn($langs->transnoentitiesnoconv('DossierImportConductionUnknown', $label, $p['conduction']['code'].'/'.$p['conduction']['subcode'], $p['conduction']['label']));
			}
			$plot->conduction_protocol = $p['conduction']['protocol'];
			$plot->conduction_partial = $p['partial'] ? 1 : 0;
			$plot->date_conduction_start = self::date($p['conduction']['date_start']);
			$plot->date_conduction_end = self::date($p['conduction']['date_end']);
			$plot->fk_owner = $this->ownerId($p['owners']);
			$plot->size_cadastral = $p['size_cadastral'];
			$plot->size_total = $p['size_graphic'] !== null ? $p['size_graphic'] : $p['size_cadastral'];
			$plot->size_conducted = $p['size_conducted'];
			if ($p['cultivations']) {
				$sau = 0.0;
				foreach ($p['cultivations'] as $c) {
					if (!in_array($c['land_use']['code'], self::NON_SAU_LAND_USES, true)) {
						$sau += $c['size'];
					}
				}
				$plot->size_sau = round($sau, 4);
			}
			$zvn = false;
			$natura = array();
			foreach ($p['constraints'] as $v) {
				$zvn = $zvn || $v['zvn'];
				foreach (array('sic', 'zps') as $k) {
					if ($v[$k] !== '') {
						$natura[] = stripos($v[$k], $k) === 0 ? $v[$k] : strtoupper($k).' '.$v[$k];
					}
				}
			}
			$plot->zone_zvn = $zvn ? 1 : 0;
			$plot->zone_natura2000 = $natura ? dol_trunc(implode(', ', array_unique($natura)), 64, 'right', 'UTF-8', 1) : null;
			$plot->import_key = $this->importKey;

			$res = $new ? $plot->create($this->user) : $plot->update($this->user);
			if ($res < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportPlotError', $label).': '.$plot->error.' '.implode(', ', $plot->errors));
			}
			$this->count('plot', $new);
			$ids[$key] = (int) $plot->id;
		}
		$this->log($langs->transnoentitiesnoconv('DossierImportPlotsDone', count($ids), $this->stats['plot']['created'] ?? 0, $this->stats['plot']['updated'] ?? 0));

		// Plots of the farm missing from the dossier: never deleted, only reported
		$sql = "SELECT t.rowid, t.ref, t.label, t.cadastral_comune, t.cadastral_section, t.cadastral_sheet, t.cadastral_parcel, t.cadastral_sub";
		$sql .= " FROM ".$this->db->prefix()."dolifarm_plot as t";
		$sql .= " WHERE t.fk_soc = ".((int) $this->socid)." AND t.status <> 9 AND t.entity IN (".getEntity('dolifarmplot').")";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$key = self::cadastralKey((string) $obj->cadastral_comune, (string) $obj->cadastral_section, (string) $obj->cadastral_sheet, (string) $obj->cadastral_parcel, (string) $obj->cadastral_sub);
				if (!isset($ids[$key])) {
					$this->warn($langs->transnoentitiesnoconv('DossierImportPlotMissing', $obj->ref, $obj->label));
				}
			}
		}
		return $ids;
	}

	/**
	 * Buildings of the dossier
	 *
	 * @param	array<int,array<string,mixed>>	$buildings	Buildings
	 * @param	int								$dossierid	Dossier
	 * @param	array<string,int>				$plots		Plots by cadastral key
	 * @return	void
	 */
	private function importBuildings(array $buildings, $dossierid, array $plots)
	{
		global $langs;

		foreach ($buildings as $b) {
			$key = self::cadastralKey($b['comune']['name'], $b['section'], $b['sheet'], $b['parcel'], $b['sub']);
			$label = $langs->transnoentitiesnoconv('DossierImportBuildingLabel', self::cadastralLabel($b['comune']['name'], $b['sheet'], $b['parcel'], $b['sub']));
			$building = new DoliFarmBuilding($this->db);
			$id = $this->findByCadastralKey('dolifarm_building', $building->element, $key);
			$new = ($id == 0);
			if (!$new) {
				$building->fetch($id);
			} else {
				$building->label = $label;
				$building->status = 1;
			}
			$building->fk_soc = $this->socid;
			$building->fk_dossier = $dossierid;
			// The building is on the plot with the same cadastral reference, without the sub
			$plotkey = self::cadastralKey($b['comune']['name'], $b['section'], $b['sheet'], $b['parcel'], '');
			if (empty($building->fk_plot) && isset($plots[$plotkey])) {
				$building->fk_plot = $plots[$plotkey];
			}
			if ($b['use'] !== '') {
				$building->building_use = $b['use'];
			}
			$building->fk_ownership = self::ownershipFromText($b['conduction']);
			$building->date_conduction_start = self::date($b['date_start']);
			$building->date_conduction_end = self::date($b['date_end']);
			$building->cadastral_comune = $b['comune']['name'];
			$building->cadastral_section = $b['section'];
			$building->cadastral_sheet = $b['sheet'];
			$building->cadastral_parcel = $b['parcel'];
			$building->cadastral_sub = $b['sub'];
			// The sheet gives the total surface; the covered surface is used when present
			$building->surface_covered = $b['surface_covered'] !== null ? $b['surface_covered'] : $b['surface'];
			$building->surface_uncovered = $b['surface_uncovered'];
			$building->volume = $b['volume'];
			$building->nb_seats = $b['seats'];
			$building->import_key = $this->importKey;
			$res = $new ? $building->create($this->user) : $building->update($this->user);
			if ($res < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportBuildingError', $label).': '.$building->error.' '.implode(', ', $building->errors));
			}
			$this->count('building', $new);
		}
		if ($buildings) {
			$this->log($langs->transnoentitiesnoconv('DossierImportBuildingsDone', count($buildings)));
		}
	}

	/**
	 * Livestock farms of the dossier (BDN)
	 *
	 * @param	array<int,array<string,mixed>>	$farms		Livestock farms
	 * @param	int								$dossierid	Dossier
	 * @return	void
	 */
	private function importLivestock(array $farms, $dossierid)
	{
		global $langs;

		foreach ($farms as $a) {
			$livestock = new DoliFarmLivestock($this->db);
			$sql = "SELECT t.rowid FROM ".$this->db->prefix()."dolifarm_livestock as t";
			$sql .= " WHERE t.fk_soc = ".((int) $this->socid)." AND t.bdn_code = '".$this->db->escape($a['bdn_code'])."'";
			$sql .= " AND t.entity IN (".getEntity($livestock->element).")";
			// Several sites can have the same BDN code (apiaries): the start date tells them apart, as the address
			// is not printed the same way in the XML and in the PDF
			$start = self::date($a['date_start']);
			if ($start) {
				$sql .= " AND t.date_start = '".$this->db->escape(dol_print_date($start, '%Y-%m-%d'))."'";
			} else {
				$sql .= " AND UPPER(COALESCE(t.address, '')) = '".$this->db->escape(strtoupper($a['address']))."'";
			}
			$id = $this->singleId($sql);
			$new = ($id == 0);
			if (!$new) {
				$livestock->fetch($id);
			} else {
				$livestock->label = dol_trunc($a['species'].' '.$a['bdn_code'].($a['address'] !== '' ? ' - '.$a['address'] : ''), 250, 'right', 'UTF-8', 1);
				$livestock->status = 1;
			}
			$livestock->fk_soc = $this->socid;
			$livestock->fk_dossier = $dossierid;
			$livestock->bdn_code = $a['bdn_code'];
			$livestock->fk_species = $this->speciesCode($a['species']);
			if ($a['heads'] !== null) {
				$livestock->nb_heads = $a['heads'];
			}
			$livestock->production_type = $a['production_type'];
			$livestock->breeding_type = $a['breeding_type'];
			$livestock->breeding_mode = $a['breeding_mode'];
			$livestock->milk_authorization = $a['milk'];
			$livestock->organic = $a['organic'] ? 1 : 0;
			$livestock->address = $a['address'];
			if ($a['town']['name'] !== '' || $new) {
				$livestock->town = $a['town']['name'];
			}
			$livestock->holder = $a['holder'] !== '' ? $a['holder'] : $a['owner'];
			$livestock->date_start = self::date($a['date_start']);
			$livestock->date_end = self::date($a['date_end']);
			$livestock->date_bdn_update = self::date($a['date_bdn']);
			$livestock->import_key = $this->importKey;
			$res = $new ? $livestock->create($this->user) : $livestock->update($this->user);
			if ($res < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportLivestockError', $a['bdn_code']).': '.$livestock->error.' '.implode(', ', $livestock->errors));
			}
			$this->count('livestock', $new);
		}
		if ($farms) {
			$this->log($langs->transnoentitiesnoconv('DossierImportLivestockDone', count($farms)));
		}
	}

	/**
	 * Machines of the dossier
	 *
	 * @param	array<int,array<string,mixed>>	$machines	Machines
	 * @param	int								$dossierid	Dossier
	 * @return	void
	 */
	private function importMachines(array $machines, $dossierid)
	{
		global $langs;

		foreach ($machines as $m) {
			$mach = new DoliFarmMachine($this->db);
			$label = trim(implode(' ', array_filter(array($m['type'], $m['brand'], $m['model'], $m['year'] ? (string) $m['year'] : ''))));
			$ref = $m['plate'] !== '' ? $m['plate'] : $m['chassis'];
			$where = $ref !== '' ? "t.ref = '".$this->db->escape($ref)."'" : "UPPER(t.label) = '".$this->db->escape(strtoupper($label))."'";
			$sql = "SELECT t.rowid FROM ".$this->db->prefix()."dolifarm_machine as t";
			$sql .= " WHERE t.fk_soc = ".((int) $this->socid)." AND ".$where." AND t.entity IN (".getEntity($mach->element).")";
			$id = $this->singleId($sql);
			$new = ($id == 0);
			if (!$new) {
				$mach->fetch($id);
			} else {
				$mach->ref = $ref;	// empty: the numbering module gives it
				$mach->label = $label !== '' ? $label : $langs->transnoentitiesnoconv('Machine');
				$mach->status = 1;
				$mach->fk_type = self::machineType($m['type']);
			}
			$mach->fk_soc = $this->socid;
			$mach->fk_dossier = $dossierid;
			if ($m['power_kw'] !== null) {
				$mach->power_kw = $m['power_kw'];
			}
			if ($m['year'] !== null) {
				$mach->year_registration = $m['year'];
			}
			$fuel = self::fuelType($m['fuel']);
			if ($fuel !== null) {
				$mach->fuel_type = $fuel;
			}
			$res = $new ? $mach->create($this->user) : $mach->update($this->user);
			if ($res < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportMachineError', $label).': '.$mach->error.' '.implode(', ', $mach->errors));
			}
			$this->count('machine', $new);
		}
		if ($machines) {
			$this->log($langs->transnoentitiesnoconv('DossierImportMachinesDone', count($machines)));
		}
	}

	/**
	 * Bank accounts of the farm, added when the IBAN is not already known
	 *
	 * @param	string							$owner		Name of the account holder
	 * @param	array<int,array<string,string>>	$accounts	Accounts
	 * @return	void
	 */
	private function importBankAccounts($owner, array $accounts)
	{
		global $langs;

		if (!$accounts) {
			return;
		}
		// IBANs are stored encrypted: compare them after reading
		$known = array();
		$resql = $this->db->query("SELECT rowid FROM ".$this->db->prefix()."societe_rib WHERE fk_soc = ".((int) $this->socid)." AND type = 'ban'");
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$rib = new CompanyBankAccount($this->db);
				if ($rib->fetch((int) $obj->rowid) > 0) {
					$known[] = strtoupper(str_replace(' ', '', (string) $rib->iban));
				}
			}
		}
		foreach ($accounts as $c) {
			$iban = strtoupper(str_replace(' ', '', $c['iban']));
			if (in_array($iban, $known, true)) {
				continue;
			}
			$rib = new CompanyBankAccount($this->db);
			$rib->socid = $this->socid;
			$rib->type = 'ban';
			$rib->label = dol_trunc($c['bank'] !== '' ? $c['bank'] : $iban, 30, 'right', 'UTF-8', 1);
			$rib->bank = $c['bank'];
			$rib->iban = $iban;
			$rib->address = $c['agency'];
			$rib->owner_name = $owner;
			$rib->status = 1;
			$rib->fk_country = $this->countryId(substr($iban, 0, 2));
			if ($rib->create($this->user) < 0 || $rib->update($this->user) <= 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportBankError').': '.$rib->error.' '.implode(', ', $rib->errors));
			}
			$known[] = $iban;
			$this->count('bankaccount', true);
			$this->log($langs->transnoentitiesnoconv('DossierImportBankAdded', substr($iban, 0, 4).'...'.substr($iban, -4)));
		}
	}

	// ------------------------------------------------------------------------------------------------
	// Graphic plan, documents, production plans
	// ------------------------------------------------------------------------------------------------

	/**
	 * Graphic cultivation plan (islands, fields, cultivations): the rows of the campaign are replaced
	 *
	 * @param	array<string,mixed>	$data		Data
	 * @param	int					$dossierid	Dossier
	 * @return	void
	 */
	private function importGraphicPlan(array $data, $dossierid)
	{
		global $langs;

		$plan = $data['isole'];
		if (empty($plan['islands'])) {
			return;
		}
		$campaign = (int) $data['campagna'];
		$table = $this->db->prefix().'dolifarm_dossierpcg';
		$sql = "DELETE FROM ".$table." WHERE fk_dossier = ".((int) $dossierid)." AND (campaign = ".$campaign." OR campaign IS NULL)";
		if (!$this->db->query($sql)) {
			$this->warn($langs->transnoentitiesnoconv('DossierImportReactivate', $this->db->lasterror()));
			return;
		}
		$fields = 0;
		$rows = 0;
		foreach ($plan['islands'] as $island) {
			foreach ($island['fields'] as $field) {
				$fields++;
				foreach ($field['cultivations'] as $c) {
					$start = self::date($c['date_start'] !== '' ? $c['date_start'] : $plan['date_start']);
					$end = self::date($c['date_end'] !== '' ? $c['date_end'] : $plan['date_end']);
					$sql = "INSERT INTO ".$table." (entity, fk_dossier, fk_soc, campaign, island, field_ref, land_use_code, land_use, destination, land_use_detail, quality, variety,";
					$sql .= " size, date_start, date_end, maintenance, production_method, import_key) VALUES (";
					$sql .= ((int) getEntity('dolifarmfarmdossier', 0)).", ".((int) $dossierid).", ".((int) $this->socid).", ".($campaign > 0 ? $campaign : "NULL").", ";
					$sql .= $this->sqlText($island['id'], 32).", ".$this->sqlText($field['id'], 32).", ".$this->sqlText($c['land_use']['code'], 16).", ";
					$sql .= $this->sqlText($c['land_use']['label'], 255).", ".$this->sqlText(self::codedText($c['destination']), 255).", ";
					$sql .= $this->sqlText(self::codedText($c['use']), 255).", ".$this->sqlText(self::codedText($c['quality']), 255).", ";
					$sql .= $this->sqlText(self::codedText($c['variety']), 255).", ".((float) $c['size']).", ";
					$sql .= ($start ? "'".$this->db->idate($start)."'" : "NULL").", ".($end ? "'".$this->db->idate($end)."'" : "NULL").", ";
					$sql .= $this->sqlText((string) ($c['maintenance'] ?? ''), 8).", ".$this->sqlText((string) $c['organic'], 32).", '".$this->db->escape($this->importKey)."')";
					if (!$this->db->query($sql)) {
						throw new Exception($langs->transnoentitiesnoconv('DossierImportGraphicPlanError').': '.$this->db->lasterror());
					}
					$rows++;
				}
			}
		}
		$this->stats['pcg'] = array('created' => $rows, 'updated' => 0);
		$this->log($langs->transnoentitiesnoconv('DossierImportGraphicPlanDone', count($plan['islands']), $fields, $rows));
	}

	/**
	 * Documents of the paper dossier, with their expiry (one per farm, protocol and type)
	 *
	 * @param	array<int,array<string,string>>	$docs		Documents
	 * @param	int								$dossierid	Dossier
	 * @return	void
	 */
	private function importDocuments(array $docs, $dossierid)
	{
		global $langs;

		foreach ($docs as $d) {
			$type = dol_trunc($d['type'] !== '' ? $d['type'] : $d['section'], 255, 'right', 'UTF-8', 1);
			if ($type === '') {
				continue;
			}
			$sql = "SELECT rowid FROM ".$this->db->prefix()."dolifarm_dossierdoc";
			$sql .= " WHERE fk_soc = ".((int) $this->socid)." AND label = '".$this->db->escape($type)."'";
			$sql .= " AND COALESCE(protocol, '') = '".$this->db->escape($d['protocol'])."' AND entity IN (".getEntity('dolifarmfarmdossier').")";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->warn($langs->transnoentitiesnoconv('DossierImportReactivate', $this->db->lasterror()));
				return;
			}
			$obj = $this->db->fetch_object($resql);
			$doc = new DoliFarmDossierDoc($this->db);
			$new = !$obj;
			if (!$new) {
				$doc->fetch((int) $obj->rowid);
			} else {
				$doc->entity = (int) getEntity('dolifarmfarmdossier', 0);
				$doc->status = 1;
				$doc->date_creation = dol_now();
			}
			$doc->ref = $d['protocol'] !== '' ? $d['protocol'] : $type;
			$doc->label = $type;
			$doc->section = $d['section'];
			$doc->fk_dossier = (int) $dossierid;
			$doc->fk_soc = $this->socid;
			$doc->protocol = $d['protocol'];
			$doc->date_signature = self::date($d['date_signature'] ?? '');
			$doc->date_start = self::date($d['date_start'] ?? '');
			$doc->date_end = self::date($d['date_end']);
			$doc->import_key = $this->importKey;
			$res = $new ? $doc->create($this->user) : $doc->update($this->user);
			if ($res < 0) {
				throw new Exception($langs->transnoentitiesnoconv('DossierImportDocumentError', $type).': '.$doc->error);
			}
			if (dolifarmExpirySyncEvents($this->db, $doc, $this->user) < 0) {
				dol_syslog(__METHOD__.' agenda event of the document '.$doc->id, LOG_ERR);
			}
			$this->count('document', $new);
			if ($doc->date_end) {
				$this->log($langs->transnoentitiesnoconv('DossierImportDocumentExpiry', $type, dol_print_date($doc->date_end, 'day')));
			}
		}
	}

	/**
	 * Draft production plans of doliTrace from the cultivations of each parcel: one per plot and crop of the campaign,
	 * only for the AGEA land uses linked to a crop (dictionary of the land uses); never twice
	 *
	 * @param	array<string,mixed>		$data		Data
	 * @param	DoliFarmFarmDossier		$dossier	Dossier
	 * @param	array<string,int>		$plots		Id of the plot by cadastral key
	 * @return	void
	 */
	private function importCropPlans(array $data, $dossier, array $plots)
	{
		global $langs;

		$hascultivations = false;
		foreach ($data['particelle'] as $p) {
			$hascultivations = $hascultivations || !empty($p['cultivations']);
		}
		if (!$hascultivations) {
			return;
		}
		if (!isModEnabled('dolitrace')) {
			$this->warn($langs->transnoentitiesnoconv('DossierImportCropPlansNoTrace'));
			return;
		}
		dol_include_once('/dolitrace/class/dolitracecropplan.class.php');
		$langs->load('dolitrace@dolitrace');
		$campaign = (int) $data['campagna'];
		$unlinked = array();
		$crops = array();
		foreach ($data['particelle'] as $p) {
			$key = self::cadastralKey($p['comune']['name'], $p['section'], $p['sheet'], $p['parcel'], $p['sub']);
			if (empty($plots[$key])) {
				continue;
			}
			$groups = array();
			foreach ($p['cultivations'] as $c) {
				$code = $c['land_use']['code'];
				if ($code === '' || in_array($code, self::NON_SAU_LAND_USES, true)) {
					continue;
				}
				$cropid = dolifarmLandUseCrop($this->db, $langs, $code, $c['land_use']['label']);
				if ($cropid < 0) {
					$this->warn($langs->transnoentitiesnoconv('DossierImportReactivate', $this->db->lasterror()));
					return;
				}
				if ($cropid == 0) {
					$unlinked[$code] = $code.' '.$c['land_use']['label'];
					continue;
				}
				if (!isset($groups[$cropid])) {
					$groups[$cropid] = array('size' => 0.0, 'start' => null, 'end' => null, 'uses' => array());
				}
				$start = self::date($c['date_start'] !== '' ? $c['date_start'] : $data['isole']['date_start']);
				$end = self::date($c['date_end'] !== '' ? $c['date_end'] : $data['isole']['date_end']);
				$g = $groups[$cropid];
				$g['size'] += (float) $c['size'];
				$g['start'] = ($start && (!$g['start'] || $start < $g['start'])) ? $start : $g['start'];
				$g['end'] = ($end && (!$g['end'] || $end > $g['end'])) ? $end : $g['end'];
				$g['uses'][$code] = $code.' '.$c['land_use']['label'];
				$groups[$cropid] = $g;
			}

			$plotlabel = self::cadastralLabel($p['comune']['name'], $p['sheet'], $p['parcel'], $p['sub']);
			foreach ($groups as $cropid => $g) {
				if (!isset($crops[$cropid])) {
					$resql = $this->db->query("SELECT label FROM ".$this->db->prefix()."dolifarm_crops WHERE rowid = ".((int) $cropid));
					$obj = $resql ? $this->db->fetch_object($resql) : null;
					$crops[$cropid] = $obj ? $langs->transnoentitiesnoconv((string) $obj->label) : (string) $cropid;
				}
				$sql = "SELECT rowid FROM ".$this->db->prefix()."dolitrace_cropplan WHERE fk_plot = ".((int) $plots[$key])." AND fk_crop = ".((int) $cropid);
				$sql .= " AND season = ".$campaign." AND entity IN (".getEntity('dolitracecropplan').")";
				if ($this->singleId($sql) > 0) {
					$this->log($langs->transnoentitiesnoconv('DossierImportCropPlanExists', $crops[$cropid], $plotlabel, $campaign));
					continue;
				}
				$plan = new DoliTraceCropPlan($this->db);
				$plan->label = dol_trunc($crops[$cropid].' - '.$plotlabel, 255, 'right', 'UTF-8', 1);
				$plan->fk_soc = $this->socid;
				$plan->fk_dossier = (int) $dossier->id;
				$plan->fk_plot = (int) $plots[$key];
				$plan->fk_crop = (int) $cropid;
				$plan->season = $campaign;
				$plan->date_start = $g['start'];
				$plan->date_end = $g['end'];
				$plan->status = 0;
				$plan->note_public = $langs->transnoentitiesnoconv('DossierImportCropPlanNote', $dossier->sheet_number, implode(', ', $g['uses']), price2num($g['size'], 4));
				$plan->import_key = $this->importKey;
				if ($plan->create($this->user) < 0) {
					throw new Exception($langs->transnoentitiesnoconv('DossierImportCropPlanError', $plan->label).': '.$plan->error.' '.implode(', ', $plan->errors));
				}
				$this->count('cropplan', true);
				$this->log($langs->transnoentitiesnoconv('DossierImportCropPlanCreated', $crops[$cropid], $plotlabel, $campaign));
			}
		}
		if ($unlinked) {
			ksort($unlinked);
			$this->warn($langs->transnoentitiesnoconv('DossierImportLandUseUnlinked', implode('; ', $unlinked)));
		}
	}

	/**
	 * Escaped and quoted text for SQL, NULL when empty
	 *
	 * @param	string	$text	Text
	 * @param	int		$max	Max length
	 * @return	string
	 */
	private function sqlText($text, $max)
	{
		$text = trim((string) $text);
		return $text === '' ? "NULL" : "'".$this->db->escape(dol_trunc($text, $max, 'right', 'UTF-8', 1))."'";
	}

	/**
	 * "code label" of a coded value, '' for the empty code 000
	 *
	 * @param	array{code:string,label:string}	$c	Coded value
	 * @return	string
	 */
	private static function codedText($c)
	{
		if (($c['code'] === '' || $c['code'] === '000') && $c['label'] === '') {
			return '';
		}
		return trim($c['code'].' '.$c['label']);
	}

	/**
	 * Two names of a third party are the same, ignoring case, punctuation and spaces
	 *
	 * @param	string	$a	Name
	 * @param	string	$b	Name
	 * @return	bool
	 */
	private static function sameName($a, $b)
	{
		$n = function ($s) {
			return preg_replace('/[^A-Z0-9]/', '', strtoupper(dol_string_unaccent((string) $s)));
		};
		return $n($a) !== '' && $n($a) === $n($b);
	}

	/**
	 * Report the sections of the file that this version does not import
	 *
	 * @param	array<string,mixed>	$data	Whole dossier
	 * @return	void
	 */
	private function reportNotImported(array $data)
	{
		global $langs;

		if (!empty($data['skipped'])) {
			$this->warn($langs->transnoentitiesnoconv('DossierImportSectionsSkipped', implode(', ', $data['skipped'])));
		}
	}

	// ------------------------------------------------------------------------------------------------
	// Lookups and mappings
	// ------------------------------------------------------------------------------------------------

	/**
	 * Third party of the current entities with this tax code (idprof4) or VAT number
	 *
	 * @param	string	$cf		Tax code, '' if unknown
	 * @param	string	$vat	VAT number (11 digits, with or without IT), '' if unknown
	 * @return	int				Id, 0 if not found
	 */
	private function findThirdparty($cf, $vat)
	{
		$socid = 0;
		if ($cf !== '') {
			$socid = $this->singleId("SELECT s.rowid FROM ".$this->db->prefix()."societe as s WHERE s.idprof4 = '".$this->db->escape($cf)."' AND s.entity IN (".getEntity('societe').")");
		}
		if ($socid == 0 && $vat !== '') {
			$vatnum = preg_replace('/^[A-Z]{2}/', '', strtoupper($vat));
			$sql = "SELECT s.rowid FROM ".$this->db->prefix()."societe as s";
			$sql .= " WHERE s.tva_intra IN ('".$this->db->escape($vatnum)."', '".$this->db->escape('IT'.$vatnum)."') AND s.entity IN (".getEntity('societe').")";
			$socid = $this->singleId($sql);
		}
		return $socid;
	}

	/**
	 * Id returned by a query that must match at most one row
	 *
	 * @param	string	$sql	Query on rowid
	 * @return	int				Id, 0 if no row
	 */
	private function singleId($sql)
	{
		global $langs;

		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new Exception($this->db->lasterror());
		}
		$num = $this->db->num_rows($resql);
		if ($num > 1) {
			throw new Exception($langs->transnoentitiesnoconv('DossierImportAmbiguous'));
		}
		if ($num == 1) {
			return (int) $this->db->fetch_object($resql)->rowid;
		}
		return 0;
	}

	/**
	 * Id of the plot or building of the farm with this cadastral key
	 *
	 * @param	string	$table		Table without prefix
	 * @param	string	$element	Element, for the entity filter
	 * @param	string	$key		Cadastral key (see cadastralKey())
	 * @return	int					Id, 0 if not found
	 */
	private function findByCadastralKey($table, $element, $key)
	{
		$sql = "SELECT t.rowid, t.cadastral_comune, t.cadastral_section, t.cadastral_sheet, t.cadastral_parcel, t.cadastral_sub";
		$sql .= " FROM ".$this->db->prefix().$table." as t";
		$sql .= " WHERE t.fk_soc = ".((int) $this->socid)." AND t.entity IN (".getEntity($element).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new Exception($this->db->lasterror());
		}
		$found = 0;
		while ($obj = $this->db->fetch_object($resql)) {
			if (self::cadastralKey((string) $obj->cadastral_comune, (string) $obj->cadastral_section, (string) $obj->cadastral_sheet, (string) $obj->cadastral_parcel, (string) $obj->cadastral_sub) === $key) {
				if ($found) {
					global $langs;
					throw new Exception($langs->transnoentitiesnoconv('DossierImportAmbiguous'));
				}
				$found = (int) $obj->rowid;
			}
		}
		return $found;
	}

	/**
	 * Comparison key of a cadastral reference: upper case, no leading zeros (00110 = 110)
	 *
	 * @param	string	$comune		Comune
	 * @param	string	$section	Section
	 * @param	string	$sheet		Sheet
	 * @param	string	$parcel		Parcel
	 * @param	string	$sub		Sub
	 * @return	string
	 */
	public static function cadastralKey($comune, $section, $sheet, $parcel, $sub)
	{
		$n = function ($v) {
			$v = strtoupper(trim($v));
			return ltrim($v, '0') === '' && $v !== '' ? '0' : ltrim($v, '0');
		};
		return strtoupper(trim($comune)).'|'.$n($section).'|'.$n($sheet).'|'.$n($parcel).'|'.$n($sub);
	}

	/**
	 * Readable cadastral reference
	 *
	 * @param	string	$comune		Comune
	 * @param	string	$sheet		Sheet
	 * @param	string	$parcel		Parcel
	 * @param	string	$sub		Sub
	 * @return	string
	 */
	private static function cadastralLabel($comune, $sheet, $parcel, $sub)
	{
		global $langs;
		return $langs->transnoentitiesnoconv('DossierImportPlotLabel', $comune, $sheet, $parcel).($sub !== '' ? ' sub '.$sub : '');
	}

	/**
	 * Owner of a plot: the farm itself, or a third party searched by tax code (created when missing)
	 *
	 * @param	array<int,array{cf:string,name:string}>	$owners	Owners
	 * @return	int|null										Id, null if unknown
	 */
	private function ownerId(array $owners)
	{
		if (!$owners) {
			return null;
		}
		$o = $owners[0];
		$cf = self::normalizeCuaa($o['cf']);
		if ($cf === $this->cuaa || ($cf === '' && ($o['name'] === '' || self::sameName($o['name'], $this->farmName)))) {
			return $this->socid;
		}
		return $this->findOrCreateOrganisation($o['name'], $cf, '');
	}

	/**
	 * Ownership (dictionary dolifarm_c_ownership) from the AGEA code of the title of conduction (IO 122/2025 §5.3.3)
	 *
	 * @param	int		$code		Code
	 * @param	int		$subcode	Subcode
	 * @return	string|null			Code of the dictionary, null if no match
	 */
	private function ownershipFromCode($code, $subcode)
	{
		$map = array(
			'5/2' => 'OWNED', '5/17' => 'OWNED', '5/30' => 'OWNED', '5/31' => 'OWNED', '5/10' => 'OWNED', '5/11' => 'OWNED',
			'5/34' => 'OWNED', '5/16' => 'OWNED', '5/21' => 'OWNED', '5/20' => 'OWNED',
			'5/32' => 'USUFRUCT', '5/33' => 'USUFRUCT',
			'6/1' => 'RENTED', '6/2' => 'RENTED', '7/1' => 'RENTED', '6/4' => 'RENTED',
			'5/14' => 'COMODATO', '5/15' => 'COMODATO',
		);
		$k = ((int) $code).'/'.((int) $subcode);
		return isset($map[$k]) ? $this->dictionaryCode('dolifarm_c_ownership', $map[$k]) : null;
	}

	/**
	 * Ownership from the printed text (buildings)
	 *
	 * @param	string	$text	Text, e.g. "PROPRIETA'"
	 * @return	string|null
	 */
	private static function ownershipFromText($text)
	{
		$t = strtoupper($text);
		if (strpos($t, 'AFFITT') !== false) {
			return 'RENTED';
		}
		if (strpos($t, 'COMODAT') !== false) {
			return 'COMODATO';
		}
		if (strpos($t, 'USUFRUTT') !== false) {
			return 'USUFRUCT';
		}
		if (strpos($t, 'PROPRIET') !== false) {
			return 'OWNED';
		}
		return null;
	}

	/**
	 * Species of the dictionary dolifarm_c_livestock_species from the BDN species
	 *
	 * @param	string	$species	Species, e.g. API, BOVINI
	 * @return	string
	 */
	private function speciesCode($species)
	{
		$s = strtoupper(trim($species));
		$aliases = array('BOVINO' => 'BOVINI', 'BUFALINO' => 'BUFALINI', 'OVINO' => 'OVINI', 'CAPRINO' => 'CAPRINI', 'SUINO' => 'SUINI',
			'EQUINO' => 'EQUINI', 'ASININO' => 'ASININI', 'POLLAME' => 'AVICOLI', 'CONIGLI' => 'CUNICOLI', 'APE' => 'API');
		if (isset($aliases[$s])) {
			$s = $aliases[$s];
		}
		$code = $this->dictionaryCode('dolifarm_c_livestock_species', $s);
		return $code !== null ? $code : 'ALTRO';
	}

	/**
	 * Machine type (dictionary dolifarm_c_machinetype) from a free description
	 *
	 * @param	string	$type	Type, e.g. TRATTRICE, MIETITREBBIA
	 * @return	string
	 */
	private static function machineType($type)
	{
		$t = strtoupper($type);
		$map = array('CINGOL' => 'TRACTOR_TRACK', 'TRATT' => 'TRACTOR_WHEEL', 'MIETI' => 'HARVESTER', 'RACCOGL' => 'HARVESTER',
			'ARATR' => 'TOOL_PLOW', 'ERPIC' => 'TOOL_HARROW', 'SEMINA' => 'TOOL_SOWER', 'ATOMIZZ' => 'TOOL_SPRAYER', 'IRRORAT' => 'TOOL_SPRAYER',
			'BOTTE' => 'TOOL_SPRAYER', 'SPANDICONC' => 'TOOL_FERTILIZER', 'RIMORCH' => 'TRAILER', 'IRRIG' => 'IRRIGATION', 'DRONE' => 'DRONE');
		foreach ($map as $needle => $code) {
			if (strpos($t, $needle) !== false) {
				return $code;
			}
		}
		return 'OTHER';
	}

	/**
	 * Fuel type (dictionary dolifarm_c_fueltype) from a free description
	 *
	 * @param	string	$fuel	Fuel, e.g. GASOLIO
	 * @return	string|null
	 */
	private static function fuelType($fuel)
	{
		$f = strtoupper($fuel);
		if ($f === '') {
			return null;
		}
		if (strpos($f, 'GASOLIO') !== false || strpos($f, 'DIESEL') !== false) {
			return 'DIESEL';
		}
		if (strpos($f, 'BENZINA') !== false) {
			return 'GASOLINE';
		}
		if (strpos($f, 'ELETTR') !== false) {
			return 'ELECTRIC';
		}
		return null;
	}

	/**
	 * Legal form (c_forme_juridique of Italy) from the printed description
	 *
	 * @param	string	$text	Description, e.g. "SOCIETA' SEMPLICI, IRREGOLARI O DI FATTO"
	 * @return	string			Code, '' if unknown
	 */
	private static function legalFormCode($text)
	{
		$t = strtoupper($text);
		$map = array('COOPERATIVA AGRICOLA' => '322', 'COOPERATIVA' => '307', 'NOME COLLETTIVO' => '302', 'ACCOMANDITA PER AZIONI' => '306',
			'ACCOMANDITA SEMPLICE' => '303', 'PER AZIONI' => '304', 'RESPONSABILITA' => '305', 'SEMPLIC' => '301', 'DI FATTO' => '313',
			'CONSORTIL' => '308', 'FAMILIARE' => '318', 'CONIUGALE' => '317', 'INDIVIDUAL' => '316', 'DITTA' => '316');
		foreach ($map as $needle => $code) {
			if (strpos($t, $needle) !== false) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Active code of a dictionary of the module, null if not found
	 *
	 * @param	string	$table	Table without prefix
	 * @param	string	$code	Code
	 * @return	string|null
	 */
	private function dictionaryCode($table, $code)
	{
		if ($code === '' || $code === null) {
			return null;
		}
		$resql = $this->db->query("SELECT code FROM ".$this->db->prefix().$table." WHERE code = '".$this->db->escape($code)."' AND active = 1");
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (string) $obj->code;
		}
		return null;
	}

	/**
	 * Id of a country from its ISO code
	 *
	 * @param	string	$code	ISO code
	 * @return	int
	 */
	private function countryId($code)
	{
		return (int) dol_getIdFromCode($this->db, $code, 'c_country', 'code', 'rowid');
	}

	/**
	 * Id of an Italian province from its abbreviation (SR, TO...)
	 *
	 * @param	string	$province	Abbreviation
	 * @return	int|string			Id, '' if not found
	 */
	private function stateId($province)
	{
		if ($province === '') {
			return '';
		}
		$sql = "SELECT d.rowid FROM ".$this->db->prefix()."c_departements as d";
		$sql .= " INNER JOIN ".$this->db->prefix()."c_regions as r ON r.code_region = d.fk_region";
		$sql .= " INNER JOIN ".$this->db->prefix()."c_country as c ON c.rowid = r.fk_pays";
		$sql .= " WHERE c.code = 'IT' AND d.code_departement = '".$this->db->escape($province)."' AND d.active = 1";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (int) $obj->rowid;
		}
		return '';
	}

	/**
	 * Timestamp (noon) of an ISO date, '' if empty
	 *
	 * @param	string	$date	Date YYYY-MM-DD
	 * @return	int|string
	 */
	public static function date($date)
	{
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m)) {
			return '';
		}
		return dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
	}

	/**
	 * CUAA without spaces, in capitals
	 *
	 * @param	string	$value	Value read from the file
	 * @return	string
	 */
	public static function normalizeCuaa($value)
	{
		return strtoupper(preg_replace('/\s+/', '', (string) $value));
	}

	/**
	 * Check a tax code: 11 digits (with the check digit of the VAT number) or 16 characters (with the check character)
	 *
	 * @param	string	$cf		Tax code, normalized
	 * @return	bool
	 */
	public static function isValidTaxCode($cf)
	{
		if (preg_match('/^[0-9]{11}$/', $cf)) {
			return self::isValidVat($cf);
		}
		if (!preg_match('/^[A-Z0-9]{16}$/', $cf)) {
			return false;
		}
		$odd = array(1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23);
		$sum = 0;
		for ($i = 0; $i < 15; $i++) {
			$ch = $cf[$i];
			$v = ctype_digit($ch) ? (int) $ch : ord($ch) - 65;
			$sum += ($i % 2 == 0) ? $odd[$v] : $v;
		}
		return chr(65 + $sum % 26) === $cf[15];
	}

	/**
	 * Check the check digit of an Italian VAT number
	 *
	 * @param	string	$vat	VAT number, 11 digits
	 * @return	bool
	 */
	public static function isValidVat($vat)
	{
		if (!preg_match('/^[0-9]{11}$/', $vat)) {
			return false;
		}
		$sum = 0;
		for ($i = 0; $i < 10; $i++) {
			$d = (int) $vat[$i];
			if ($i % 2) {
				$d *= 2;
				if ($d > 9) {
					$d -= 9;
				}
			}
			$sum += $d;
		}
		return (10 - $sum % 10) % 10 === (int) $vat[10];
	}

	// ------------------------------------------------------------------------------------------------
	// Messages
	// ------------------------------------------------------------------------------------------------

	/**
	 * Count a created or updated object
	 *
	 * @param	string	$kind		Kind of object
	 * @param	bool	$created	true = created, false = updated
	 * @return	void
	 */
	private function count($kind, $created)
	{
		if (!isset($this->stats[$kind])) {
			$this->stats[$kind] = array('created' => 0, 'updated' => 0);
		}
		$this->stats[$kind][$created ? 'created' : 'updated']++;
	}

	/**
	 * Add a message to the log of the import
	 *
	 * @param	string	$msg	Message
	 * @return	void
	 */
	private function log($msg)
	{
		$this->output_log[] = $msg;
		dol_syslog('DoliFarmImporter: '.$msg, LOG_DEBUG);
	}

	/**
	 * Add a warning: data not imported or to check
	 *
	 * @param	string	$msg	Message
	 * @return	void
	 */
	private function warn($msg)
	{
		$this->warnings[] = $msg;
		dol_syslog('DoliFarmImporter: '.$msg, LOG_DEBUG);
	}
}
