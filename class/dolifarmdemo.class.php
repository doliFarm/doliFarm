<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    class/dolifarmdemo.class.php
 * \ingroup dolifarm
 * \brief   Demo data of the doliFarm suite: two farms in Sicily and an olive mill, three seasons
 *
 * Only for a demo instance, never in production: it sets company, country, language, modules and the multi-farm mode.
 * The data are made with the classes of the modules (validations, stock movements, register of the treatments), so they
 * behave like the data typed in the pages. It can be run again: what is already there (same ref) is kept.
 * Used by scripts/demo_data.php (command line) and admin/demo.php (setup, with DOLIFARM_DEMO_TOOLS).
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';

/**
 * Demo data builder
 */
class DoliFarmDemo
{
	/** @var DoliDB */
	public $db;
	/** @var User */
	public $user;
	/** @var int Errors */
	public $nberrors = 0;
	/** @var array<string,int> Ids by key (farms, plots, products...) */
	public $ids = array();
	/** @var bool Print the lines (command line) */
	public $echo = true;
	/** @var string[] Lines of the log */
	public $log = array();

	/**
	 * @param DoliDB $db   Database
	 * @param User   $user User
	 */
	public function __construct($db, $user)
	{
		$this->db = $db;
		$this->user = $user;
	}

	/**
	 * Print a line
	 *
	 * @param string $text Text
	 * @return void
	 */
	public function out($text)
	{
		$this->log[] = $text;
		if ($this->echo) {
			echo $text."\n";
		}
	}

	/**
	 * Print an error and count it
	 *
	 * @param string $what   What was done
	 * @param object|string $source Object with error(s), or message
	 * @return void
	 */
	public function fail($what, $source)
	{
		$this->nberrors++;
		if (is_object($source)) {
			$msg = trim(($source->error ?? '').' '.implode(' ', (array) ($source->errors ?? array())));
		} else {
			$msg = (string) $source;
		}
		$this->out("  ERROR ".$what.": ".($msg !== '' ? $msg : $this->db->lasterror()));
	}

	/**
	 * First value of a query
	 *
	 * @param string $sql SQL
	 * @return mixed|null
	 */
	public function value($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$row = $this->db->fetch_row($resql);
		$this->db->free($resql);
		return ($row ? $row[0] : null);
	}

	/**
	 * Id of a row by its ref
	 *
	 * @param string $table Table without prefix
	 * @param string $ref   Ref
	 * @param string $field Field of the ref
	 * @return int
	 */
	public function idByRef($table, $ref, $field = 'ref')
	{
		return (int) $this->value("SELECT rowid FROM ".$this->db->prefix().$table." WHERE ".$field." = '".$this->db->escape($ref)."' ORDER BY rowid LIMIT 1");
	}

	/*
	 * Instance
	 */

	/**
	 * Company, country, language, modules
	 *
	 * @return void
	 */
	public function setupInstance()
	{
		global $conf, $mysoc, $langs;

		$this->out('Instance');
		$db = $this->db;
		$entity = $conf->entity;
		if (getDolGlobalString('MAIN_INFO_SOCIETE_NOM') === '' || getDolGlobalString('MAIN_INFO_SOCIETE_NOM') === 'Demo Farm') {
			dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_NOM', 'doliFarm Demo - Servizi agricoli', 'chaine', 0, '', $entity);
			dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_ADDRESS', 'Via Etnea 100', 'chaine', 0, '', $entity);
			dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_ZIP', '95131', 'chaine', 0, '', $entity);
			dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_TOWN', 'Catania', 'chaine', 0, '', $entity);
		}
		$countryid = (int) $this->value("SELECT rowid FROM ".$db->prefix()."c_country WHERE code = 'IT'");
		if (getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY') === '' || getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY') === '1:FR:France') {
			dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_COUNTRY', $countryid.':IT:Italia', 'chaine', 0, '', $entity);
			$stateid = (int) $this->value("SELECT d.rowid FROM ".$db->prefix()."c_departements as d INNER JOIN ".$db->prefix()."c_regions as r ON r.code_region = d.fk_region WHERE d.code_departement = 'CT' AND r.fk_pays = ".$countryid);
			if ($stateid > 0) {
				dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_STATE', $stateid.':CT:Catania', 'chaine', 0, '', $entity);
			}
		}
		if (getDolGlobalString('MAIN_MONNAIE') === '') {
			dolibarr_set_const($db, 'MAIN_MONNAIE', 'EUR', 'chaine', 0, '', $entity);
		}
		dolibarr_set_const($db, 'MAIN_LANG_DEFAULT', 'it_IT', 'chaine', 0, '', $entity);
		$mysoc->setMysoc($conf);

		// Core modules first, then the suite (doliTrace needs doliFarm, doliField needs doliFarm)
		$modules = array('modSociete', 'modFournisseur', 'modProduct', 'modStock', 'modProductBatch', 'modProjet', 'modCron', 'modCategorie',
			'modDolifarm', 'modDoliTrace', 'modDoliField');
		foreach ($modules as $module) {
			$const = 'MAIN_MODULE_'.strtoupper(preg_replace('/^mod/', '', $module));
			if (getDolGlobalString($const)) {
				continue;
			}
			$result = activateModule($module);
			if (!empty($result['errors'])) {
				$this->fail('module '.$module, implode(' ', $result['errors']));
			} else {
				$this->out('  module '.$module.' enabled');
			}
		}
		// Modules write their constants: reload them
		$conf->setValues($db);
		$this->user->getrights();
		$langs->setDefaultLang('it_IT');
		$langs->loadLangs(array('main', 'companies', 'products', 'stocks', 'dolifarm@dolifarm', 'dolitrace@dolitrace'));

		// Several managed farms (multi-farm mode)
		if (!getDolGlobalInt('DOLIFARM_MULTIFARM')) {
			dolibarr_set_const($db, 'DOLIFARM_MULTIFARM', '1', 'chaine', 0, '', $entity);
			$conf->global->DOLIFARM_MULTIFARM = 1;
		}
		dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');
		dol_include_once('/dolifarm/lib/dolifarm_affiliated.lib.php');
		dol_include_once('/dolifarm/lib/dolifarm_bio.lib.php');
		dol_include_once('/dolifarm/lib/dolifarm_biobody.lib.php');
		dol_include_once('/dolifarm/lib/dolifarm_warehouse.lib.php');
		foreach (array('farmdossier', 'plot', 'machine', 'building', 'operator', 'crops', 'agrodrug', 'agrodruguse', 'fertilizer', 'estimatecosts') as $name) {
			dol_include_once('/dolifarm/class/dolifarm'.$name.'.class.php');
		}
		foreach (array('cropplan', 'operation', 'treatment', 'treatmentimport', 'harvest', 'bioinspection') as $name) {
			dol_include_once('/dolitrace/class/dolitrace'.$name.'.class.php');
		}
	}

	/*
	 * Farms
	 */

	/**
	 * Third party of a managed farm or processor, affiliated: dossier and warehouses are made by doliFarm
	 *
	 * @param string              $key  Key in $this->ids
	 * @param array<string,mixed> $data Data
	 * @return int                      Id
	 */
	public function thirdparty($key, $data)
	{
		$db = $this->db;
		$id = (int) $this->value("SELECT rowid FROM ".$db->prefix()."societe WHERE code_client = '".$db->escape($data['code'])."' OR code_fournisseur = '".$db->escape($data['code'])."' ORDER BY rowid LIMIT 1");
		$soc = new Societe($db);
		if ($id > 0) {
			$soc->fetch($id);
		} else {
			$soc->name = $data['name'];
			$soc->name_alias = $data['alias'] ?? '';
			$soc->client = $data['client'] ?? 1;
			$soc->fournisseur = $data['supplier'] ?? 0;
			$soc->code_client = $data['code'];
			if (!empty($data['supplier'])) {
				$soc->code_fournisseur = $data['code'];
			}
			$soc->status = 1;
			$soc->address = $data['address'] ?? '';
			$soc->zip = $data['zip'] ?? '';
			$soc->town = $data['town'] ?? '';
			$soc->country_id = (int) $this->value("SELECT rowid FROM ".$db->prefix()."c_country WHERE code = 'IT'");
			$soc->state_id = (int) $this->value("SELECT d.rowid FROM ".$db->prefix()."c_departements as d INNER JOIN ".$db->prefix()."c_regions as r ON r.code_region = d.fk_region WHERE d.code_departement = '".$db->escape($data['province'] ?? '')."' AND r.fk_pays = ".((int) $soc->country_id));
			$soc->phone = $data['phone'] ?? '';
			$soc->email = $data['email'] ?? '';
			$soc->tva_intra = $data['vat'] ?? '';
			$soc->idprof4 = $data['cuaa'] ?? '';
			$soc->typent_id = (int) $this->value("SELECT id FROM ".$db->prefix()."c_typent WHERE code = '".$db->escape($data['type'])."'");
			$soc->array_options = $data['extrafields'] ?? array();
			if ($soc->create($this->user) <= 0) {
				$this->fail('third party '.$data['name'], $soc);
				return 0;
			}
			$soc->fetch($soc->id);
			$this->out('  third party '.$data['name']);
		}
		if (!empty($data['extrafields']['options_dolifarm_affiliated'])) {
			$messages = array();
			$soc->fetch_optionals();
			if (dolifarmSyncAffiliatedFarm($db, $soc, $this->user, $messages) < 0) {
				$this->fail('affiliation '.$data['name'], implode(' ', $messages));
			}
			dolifarmManagedFarmIds(true);
		}
		$this->ids[$key] = (int) $soc->id;
		return (int) $soc->id;
	}

	/**
	 * Contact of a third party
	 *
	 * @param int    $socid     Third party
	 * @param string $firstname First name
	 * @param string $lastname  Last name
	 * @param string $poste     Job
	 * @return int
	 */
	public function contact($socid, $firstname, $lastname, $poste)
	{
		$id = (int) $this->value("SELECT rowid FROM ".$this->db->prefix()."socpeople WHERE fk_soc = ".((int) $socid)." AND lastname = '".$this->db->escape($lastname)."' AND firstname = '".$this->db->escape($firstname)."'");
		if ($id > 0) {
			return $id;
		}
		$contact = new Contact($this->db);
		$contact->socid = $socid;
		$contact->firstname = $firstname;
		$contact->lastname = $lastname;
		$contact->poste = $poste;
		$contact->statut = 1;
		if ($contact->create($this->user) <= 0) {
			$this->fail('contact '.$lastname, $contact);
			return 0;
		}
		return (int) $contact->id;
	}

	/**
	 * Dossier of a farm: the draft made by doliFarm, completed and validated
	 *
	 * @param int                 $socid Farm
	 * @param array<string,mixed> $data  Fields
	 * @return int                       Id
	 */
	public function dossier($socid, $data)
	{
		$dossier = new DoliFarmFarmDossier($this->db);
		$id = (int) $this->value("SELECT rowid FROM ".$this->db->prefix()."dolifarm_farmdossier WHERE fk_soc = ".((int) $socid)." ORDER BY rowid LIMIT 1");
		if ($id > 0) {
			$dossier->fetch($id);
		} else {
			$dossier->fk_soc = $socid;
			$dossier->ref = $data['ref'];
		}
		if ((int) $dossier->status === DoliFarmFarmDossier::STATUS_VALIDATED) {
			return (int) $dossier->id;
		}
		foreach ($data as $field => $value) {
			$dossier->$field = $value;
		}
		$result = ($dossier->id > 0 ? $dossier->update($this->user) : $dossier->create($this->user));
		if ($result <= 0) {
			$this->fail('dossier '.$data['ref'], $dossier);
			return 0;
		}
		$dossier->fetch($dossier->id);
		if ($dossier->validate($this->user) < 0) {
			$this->fail('validation of the dossier '.$dossier->ref, $dossier);
		}
		$this->out('  dossier '.$dossier->ref);
		return (int) $dossier->id;
	}

	/**
	 * Generic object of a module, found by ref or created, then validated when asked
	 *
	 * @param string              $class    Class
	 * @param string              $table    Table without prefix
	 * @param array<string,mixed> $data     Fields (ref required)
	 * @param bool                $validate Validate after the creation
	 * @return int                          Id
	 */
	public function object($class, $table, $data, $validate = false)
	{
		$id = $this->idByRef($table, $data['ref']);
		if ($id > 0) {
			return $id;
		}
		$object = new $class($this->db);
		foreach ($data as $field => $value) {
			$object->$field = $value;
		}
		if ($object->create($this->user) <= 0) {
			$this->fail($class.' '.$data['ref'], $object);
			return 0;
		}
		if ($validate) {
			$object->fetch($object->id);
			if ($object->validate($this->user) < 0) {
				$this->fail('validation '.$class.' '.$data['ref'], $object);
			}
		}
		return (int) $object->id;
	}

	/**
	 * Plot with its organic periods
	 *
	 * @param string              $key   Key
	 * @param int                 $socid Farm
	 * @param int                 $dossierid Dossier
	 * @param array<string,mixed> $data  Fields, and 'bio' => periods (status, date, years, reason, reference)
	 * @return int
	 */
	public function plot($key, $socid, $dossierid, $data)
	{
		$bio = $data['bio'] ?? array();
		unset($data['bio']);
		$exists = $this->idByRef('dolifarm_plot', $data['ref']);
		$data += array('fk_soc' => $socid, 'fk_owner' => $socid, 'fk_dossier' => $dossierid, 'status' => 0);
		$id = $this->object('DoliFarmPlot', 'dolifarm_plot', $data, true);
		$this->ids[$key] = $id;
		if ($exists || !$id) {
			return $id;
		}
		foreach ($bio as $period) {
			$error = '';
			if (dolifarmBioAddPeriod($this->db, $this->user, $id, $period[0], $this->ts($period[1]), $period[2] ?? 0, $period[3] ?? 'NOTIFICATION', $period[4] ?? '', '', $error) < 0) {
				$this->fail('organic period of '.$data['ref'], $error);
			}
		}
		$this->out('  plot '.$data['ref'].' '.$data['label']);
		return $id;
	}

	/**
	 * Timestamp of a date YYYY-MM-DD [HH:MM]
	 *
	 * @param string $date Date
	 * @return int
	 */
	public function ts($date)
	{
		return (int) dol_stringtotime(strlen($date) == 10 ? $date.' 00:00:00' : $date.(strlen($date) == 16 ? ':00' : ''), 0);
	}

	/*
	 * Products and stock
	 */

	/**
	 * Product of the stock
	 *
	 * @param string              $ref   Ref
	 * @param string              $label Label
	 * @param array<string,mixed> $more  Other fields (status_batch, price, weight_units...)
	 * @return int
	 */
	public function product($ref, $label, $more = array())
	{
		$id = $this->idByRef('product', $ref);
		if ($id > 0) {
			return $id;
		}
		$product = new Product($this->db);
		$product->ref = $ref;
		$product->label = $label;
		$product->type = Product::TYPE_PRODUCT;
		$product->status = 1;
		$product->status_buy = 1;
		$product->status_batch = 0;
		foreach ($more as $field => $value) {
			$product->$field = $value;
		}
		if ($product->create($this->user) <= 0) {
			$this->fail('product '.$ref, $product);
			return 0;
		}
		return (int) $product->id;
	}

	/**
	 * Purchase of an input from a supplier: supplier order validated, approved and sent at the date, then received in the
	 * inputs warehouse of the farm with the lot (stock movement of the order, dated at the delivery). Once by supplier ref.
	 *
	 * @param int    $socid      Farm
	 * @param int    $supplierid Supplier
	 * @param int    $productid  Product
	 * @param float  $qty        Quantity
	 * @param float  $price      Unit price
	 * @param float  $vat        VAT rate
	 * @param string $date       Date of the order
	 * @param string $batch      Lot ('' if the product has no lot)
	 * @param string $label      Supplier ref of the order
	 * @return void
	 */
	public function purchase($socid, $supplierid, $productid, $qty, $price, $vat, $date, $batch, $label)
	{
		global $conf;

		$db = $this->db;
		$warehouse = dolifarmGetFarmWarehouse($db, $socid, 'inputs');
		if (!$warehouse || !($productid > 0) || !($supplierid > 0)) {
			$this->fail('purchase '.$label, 'no inputs warehouse for the farm '.$socid.' or no supplier');
			return;
		}
		if ((int) $this->value("SELECT COUNT(*) FROM ".$db->prefix()."commande_fournisseur WHERE fk_soc = ".((int) $supplierid)." AND ref_supplier = '".$db->escape($label)."'") > 0) {
			return;
		}
		$ordered = $this->ts($date.' 09:00');
		$delivered = dol_time_plus_duree($ordered, 3, 'd');
		$farm = new Societe($db);
		$farm->fetch($socid);

		$order = new CommandeFournisseur($db);
		$order->socid = $supplierid;
		$order->ref_supplier = $label;
		$order->date_commande = $ordered;
		$order->delivery_date = $delivered;
		$order->note_public = 'Consegna: '.$farm->name.', magazzino mezzi tecnici';
		if ($order->create($this->user) <= 0) {
			$this->fail('order '.$label, $order);
			return;
		}
		$lineid = $order->addline('', $price, $qty, $vat, 0, 0, $productid);
		if ($lineid <= 0 || $order->fetch($order->id) <= 0 || $order->valid($this->user) < 0 || $order->approve($this->user) < 0) {
			$this->fail('order '.$label, $order);
			return;
		}
		$method = (int) $this->value("SELECT rowid FROM ".$db->prefix()."c_input_method WHERE code = 'OrderByWWW'");
		if ($order->commande($this->user, $ordered, $method) < 0) {
			$this->fail('order '.$label, $order);
			return;
		}
		// Line received with the lot; the stock movement is made below, at the date of the delivery (the core dates it today)
		$eatby = ($batch !== '' ? dol_time_plus_duree($ordered, 3, 'y') : '');
		$saved = $conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER ?? null;
		$conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER = 0;
		$result = $order->dispatchProduct($this->user, $productid, $qty, (int) $warehouse, $price, $label, $eatby, '', $batch, $lineid, 1);
		if ($saved === null) {
			unset($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER);
		} else {
			$conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER = $saved;
		}
		if ($result < 0) {
			$this->fail('reception '.$label, $order);
			return;
		}
		$movement = new MouvementStock($db);
		$movement->origin = $order;
		$movement->setOrigin($order->element, $order->id);
		if ($movement->reception($this->user, $productid, (int) $warehouse, $qty, $price, $order->ref.' '.$label, $eatby, '', $batch, $delivered) < 0) {
			$this->fail('reception '.$label, $movement);
			return;
		}
		if ($order->Livraison($this->user, $delivered, 'tot', '') < 0) {
			$this->fail('reception '.$label, $order);
		}
		// Dates of the order steps at the date of the order (the core writes today): only the order, not the stock
		$db->query("UPDATE ".$db->prefix()."commande_fournisseur SET date_creation = '".$db->idate($ordered)."', date_valid = '".$db->idate($ordered)."', date_approve = '".$db->idate($ordered)."' WHERE rowid = ".((int) $order->id));
		$db->query("UPDATE ".$db->prefix()."receptiondet_batch SET datec = '".$db->idate($delivered)."' WHERE fk_element = ".((int) $order->id));
	}

	/**
	 * Organic status of the reproductive material on the product (extrafield of doliFarm), read by sowings and plantings
	 *
	 * @param int    $productid Product
	 * @param string $status    BIO, INCONV or NONBIO
	 * @return void
	 */
	public function seedStatus($productid, $status)
	{
		$product = new Product($this->db);
		if ($productid <= 0 || $product->fetch($productid) <= 0) {
			return;
		}
		$product->array_options['options_dolifarm_seed_status'] = $status;
		if ($product->insertExtraFields() < 0) {
			$this->fail('seed status '.$product->ref, $product);
		}
	}

	/*
	 * Scenario: master data
	 */

	/**
	 * Farms, processor, dossiers, plots, machines, buildings, operators, crops, inputs, purchases, standard costs
	 *
	 * @return void
	 */
	public function buildMasterData()
	{
		$db = $this->db;
		$this->out('Farms');

		// Farm 1: citrus and olives on the slopes of Etna, organic (olive grove in conversion), durum wheat in the plain
		$etna = $this->thirdparty('etna', array(
			'code' => 'DEMO-ETNA01', 'type' => 'TE_FARM', 'name' => 'Azienda Agricola Agrumi dell\'Etna (demo)', 'alias' => 'Agrumi dell\'Etna',
			'address' => 'Contrada Ciappe Bianche 12', 'zip' => '95047', 'town' => 'Paternò', 'province' => 'CT',
			'phone' => '+39 095 000 0001', 'email' => 'demo-etna@example.com', 'cuaa' => 'DEMOETNA00000001',
			'extrafields' => array('options_dolifarm_affiliated' => 1, 'options_dolifarm_bio_body' => 'IT-BIO-006',
				'options_dolifarm_bio_cert' => 'DEMO-ICEA-2026-0412', 'options_dolifarm_bio_cert_end' => $this->ts('2027-03-31')),
		));
		// Farm 2: olives, vineyards and arable land in the Belice valley, integrated (not organic)
		$belice = $this->thirdparty('belice', array(
			'code' => 'DEMO-BELICE', 'type' => 'TE_FARM', 'name' => 'Società Agricola Valle del Belice s.s. (demo)', 'alias' => 'Valle del Belice',
			'address' => 'Contrada Seggio 4', 'zip' => '91022', 'town' => 'Castelvetrano', 'province' => 'TP',
			'phone' => '+39 0924 000 002', 'email' => 'demo-belice@example.com', 'vat' => 'IT00000000002', 'cuaa' => '00000000002',
			'extrafields' => array('options_dolifarm_affiliated' => 1, 'options_dolifarm_processing' => 0),
		));
		// Processor: olive mill that presses the olives of both farms
		$mill = $this->thirdparty('mill', array(
			'code' => 'DEMO-FRANTOIO', 'type' => 'TE_PROCESSOR', 'name' => 'Frantoio Oleario del Belice (demo)', 'alias' => 'Frantoio del Belice',
			'address' => 'Via dell\'Olio 3', 'zip' => '91028', 'town' => 'Partanna', 'province' => 'TP', 'supplier' => 1,
			'phone' => '+39 0924 000 003', 'email' => 'demo-frantoio@example.com', 'vat' => 'IT00000000003',
			'extrafields' => array('options_dolifarm_affiliated' => 1),
		));
		// Suppliers: organic seeds (certified), technical inputs
		$this->ids['seedsupplier'] = $this->thirdparty('seedsupplier', array(
			'code' => 'DEMO-SEMENTI', 'type' => 'TE_SMALL', 'name' => 'Sementi Biologiche di Sicilia (demo)', 'client' => 0, 'supplier' => 1,
			'address' => 'Zona Industriale, Blocco 7', 'zip' => '95121', 'town' => 'Catania', 'province' => 'CT', 'vat' => 'IT00000000005',
			'extrafields' => array('options_dolifarm_bio_body' => 'IT-BIO-006', 'options_dolifarm_bio_cert' => 'DEMO-ICEA-2026-0907', 'options_dolifarm_bio_cert_end' => $this->ts('2027-06-30')),
		));
		$this->ids['inputsupplier'] = $this->thirdparty('inputsupplier', array(
			'code' => 'DEMO-AGROFORN', 'type' => 'TE_SMALL', 'name' => 'Agroforniture del Sud (demo)', 'client' => 0, 'supplier' => 1,
			'address' => 'Via Palermo 88', 'zip' => '91022', 'town' => 'Castelvetrano', 'province' => 'TP', 'vat' => 'IT00000000006',
		));
		// Contractor for the harvest of the wheat, technical advisor
		$this->ids['contractor'] = $this->thirdparty('contractor', array(
			'code' => 'DEMO-CONTOTERZI', 'type' => 'TE_SMALL', 'name' => 'Contoterzi Agricoli Siciliani (demo)', 'client' => 0, 'supplier' => 1,
			'address' => 'Via Catania 20', 'zip' => '96016', 'town' => 'Lentini', 'province' => 'SR', 'vat' => 'IT00000000004',
		));
		if (!$etna || !$belice || !$mill) {
			return;
		}
		$this->contact($etna, 'Giuseppe', 'Russo', 'Titolare');
		$this->contact($etna, 'Carmela', 'Russo', 'Responsabile qualità');
		$this->contact($belice, 'Vito', 'Accardi', 'Legale rappresentante');
		$this->contact($belice, 'Rosa', 'Ingoglia', 'Agronoma');
		$this->contact($mill, 'Salvatore', 'Bono', 'Capo frantoiano');

		$this->ids['dossier_etna'] = $this->dossier($etna, array(
			'ref' => 'FASC-2026-ETNA', 'year_validity' => 2026, 'paying_agency' => 'AGEA', 'act_type' => 1, 'sheet_number' => '2026/0412',
			'protocol' => 'DEMO-CAA-2026-1187', 'date_signature' => $this->ts('2026-02-10'), 'date_validity_end' => $this->ts('2027-02-09'),
			'date_reference' => $this->ts('2026-01-31'), 'ote_code' => '3610', 'economic_size' => 98000, 'date_bio_membership' => $this->ts('2012-03-01'),
			'note_public' => 'Fascicolo di esempio della demo doliFarm.',
		));
		$this->ids['dossier_belice'] = $this->dossier($belice, array(
			'ref' => 'FASC-2026-BELICE', 'year_validity' => 2026, 'paying_agency' => 'AGEA', 'act_type' => 1, 'sheet_number' => '2026/0977',
			'protocol' => 'DEMO-CAA-2026-2290', 'date_signature' => $this->ts('2026-03-02'), 'date_validity_end' => $this->ts('2027-03-01'),
			'date_reference' => $this->ts('2026-02-28'), 'ote_code' => '3700', 'economic_size' => 165000,
			'note_public' => 'Fascicolo di esempio della demo doliFarm.',
		));

		$this->out('Plots');
		$de = $this->ids['dossier_etna'];
		$db2 = $this->ids['dossier_belice'];
		$this->plot('etna1', $etna, $de, array('ref' => 'ETNA-01', 'label' => 'Agrumeto Tarocco - Ciappe Bianche', 'fk_ownership' => 'OWNED',
			'cadastral_comune' => 'G371', 'cadastral_sheet' => '41', 'cadastral_parcel' => '118', 'size_cadastral' => 4.3510, 'size_total' => 4.3510, 'size_conducted' => 4.3510, 'size_sau' => 4.20,
			'geo_lat' => 37.5532, 'geo_lon' => 14.8891, 'iacs_parcel' => 'IT-G371-41-118', 'cultivation_type' => 1,
			'bio' => array(array('CONVERSION', '2012-03-01', 3, 'NOTIFICATION', 'DEMO-NOT-2012-055'), array('BIO', '2015-03-01', 0, 'END_CONVERSION'))));
		$this->plot('etna2', $etna, $de, array('ref' => 'ETNA-02', 'label' => 'Agrumeto Moro - Sciddicuni', 'fk_ownership' => 'OWNED',
			'cadastral_comune' => 'G371', 'cadastral_sheet' => '43', 'cadastral_parcel' => '27', 'size_cadastral' => 2.9020, 'size_total' => 2.9020, 'size_conducted' => 2.9020, 'size_sau' => 2.80,
			'geo_lat' => 37.5614, 'geo_lon' => 14.8743, 'iacs_parcel' => 'IT-G371-43-27', 'cultivation_type' => 1,
			'bio' => array(array('CONVERSION', '2012-03-01', 3, 'NOTIFICATION', 'DEMO-NOT-2012-055'), array('BIO', '2015-03-01', 0, 'END_CONVERSION'))));
		$this->plot('etna3', $etna, $de, array('ref' => 'ETNA-03', 'label' => 'Uliveto Nocellara etnea - Poggio Rosso', 'fk_ownership' => 'OWNED',
			'cadastral_comune' => 'A841', 'cadastral_sheet' => '12', 'cadastral_parcel' => '301', 'size_cadastral' => 3.6200, 'size_total' => 3.6200, 'size_conducted' => 3.6200, 'size_sau' => 3.50,
			'geo_lat' => 37.6012, 'geo_lon' => 14.8597, 'iacs_parcel' => 'IT-A841-12-301', 'cultivation_type' => 1,
			'bio' => array(array('CONVERSION', '2024-03-01', 3, 'NOTIFICATION', 'DEMO-NOT-2024-118'))));
		$this->plot('etna4', $etna, $de, array('ref' => 'ETNA-04', 'label' => 'Seminativo Piana di Lentini', 'fk_ownership' => 'RENTED',
			'conduction_protocol' => 'Contratto di affitto 2023/17', 'date_conduction_start' => $this->ts('2023-09-01'), 'date_conduction_end' => $this->ts('2029-08-31'),
			'cadastral_comune' => 'E532', 'cadastral_sheet' => '88', 'cadastral_parcel' => '15', 'size_cadastral' => 6.1500, 'size_total' => 6.1500, 'size_conducted' => 6.1500, 'size_sau' => 6.00,
			'geo_lat' => 37.3410, 'geo_lon' => 14.9180, 'iacs_parcel' => 'IT-E532-88-15', 'cultivation_type' => 1, 'zone_zvn' => 1));
		$this->plot('bel1', $belice, $db2, array('ref' => 'BEL-01', 'label' => 'Uliveto Nocellara del Belice - Seggio', 'fk_ownership' => 'OWNED',
			'cadastral_comune' => 'C286', 'cadastral_sheet' => '57', 'cadastral_parcel' => '210', 'size_cadastral' => 8.2300, 'size_total' => 8.2300, 'size_conducted' => 8.2300, 'size_sau' => 8.00,
			'geo_lat' => 37.6655, 'geo_lon' => 12.8012, 'iacs_parcel' => 'IT-C286-57-210', 'cultivation_type' => 1));
		$this->plot('bel2', $belice, $db2, array('ref' => 'BEL-02', 'label' => 'Vigneto Grillo - Contrada Magaggiari', 'fk_ownership' => 'OWNED',
			'cadastral_comune' => 'G347', 'cadastral_sheet' => '19', 'cadastral_parcel' => '44', 'size_cadastral' => 5.1200, 'size_total' => 5.1200, 'size_conducted' => 5.1200, 'size_sau' => 5.00,
			'geo_lat' => 37.7203, 'geo_lon' => 12.8811, 'iacs_parcel' => 'IT-G347-19-44', 'cultivation_type' => 1));
		$this->plot('bel3', $belice, $db2, array('ref' => 'BEL-03', 'label' => 'Vigneto Nero d\'Avola - Contrada Magaggiari', 'fk_ownership' => 'RENTED',
			'conduction_protocol' => 'Contratto di affitto 2021/04', 'date_conduction_start' => $this->ts('2021-11-11'), 'date_conduction_end' => $this->ts('2030-11-10'),
			'cadastral_comune' => 'G347', 'cadastral_sheet' => '19', 'cadastral_parcel' => '52', 'size_cadastral' => 3.6000, 'size_total' => 3.6000, 'size_conducted' => 3.6000, 'size_sau' => 3.50,
			'geo_lat' => 37.7121, 'geo_lon' => 12.8650, 'iacs_parcel' => 'IT-G347-19-52', 'cultivation_type' => 1));
		$this->plot('bel4', $belice, $db2, array('ref' => 'BEL-04', 'label' => 'Seminativo Timpone', 'fk_ownership' => 'OWNED',
			'cadastral_comune' => 'C286', 'cadastral_sheet' => '61', 'cadastral_parcel' => '9', 'size_cadastral' => 10.4000, 'size_total' => 10.4000, 'size_conducted' => 10.4000, 'size_sau' => 10.00,
			'geo_lat' => 37.6402, 'geo_lon' => 12.8305, 'iacs_parcel' => 'IT-C286-61-9', 'cultivation_type' => 1));

		$this->out('Machines, buildings, operators');
		$machines = array(
			array('etna', $de, 'MAC-ETNA-01', 'Trattore frutteto Landini Rex 4-100', 'TRACTOR_WHEEL', 72, 'DIESEL', 2019, '', ''),
			array('etna', $de, 'MAC-ETNA-02', 'Atomizzatore trainato 1000 L', 'TOOL_SPRAYER', 0, 'NONE', 2018, 'DEMO-CF-CT-2024-0311', '2024-04-15'),
			array('etna', $de, 'MAC-ETNA-03', 'Trincia sarmenti', 'OTHER', 0, 'NONE', 2016, '', ''),
			array('etna', $de, 'MAC-ETNA-04', 'Spandiconcime centrifugo', 'TOOL_FERTILIZER', 0, 'NONE', 2015, '', ''),
			array('belice', $db2, 'MAC-BEL-01', 'Trattore New Holland T5.110', 'TRACTOR_WHEEL', 81, 'DIESEL', 2021, '', ''),
			array('belice', $db2, 'MAC-BEL-02', 'Trattore vigneto Antonio Carraro TTR 4400', 'TRACTOR_WHEEL', 30, 'DIESEL', 2017, '', ''),
			array('belice', $db2, 'MAC-BEL-03', 'Atomizzatore a tunnel 600 L', 'TOOL_SPRAYER', 0, 'NONE', 2020, 'DEMO-CF-TP-2023-0907', '2023-09-20'),
			array('belice', $db2, 'MAC-BEL-04', 'Erpice a dischi', 'TOOL_HARROW', 0, 'NONE', 2014, '', ''),
			array('belice', $db2, 'MAC-BEL-05', 'Seminatrice di precisione 3 m', 'TOOL_SOWER', 0, 'NONE', 2018, '', ''),
			array('belice', $db2, 'MAC-BEL-06', 'Barra irroratrice 12 m', 'TOOL_SPRAYER', 0, 'NONE', 2016, 'DEMO-CF-TP-2024-0412', '2024-05-06'),
		);
		foreach ($machines as $m) {
			$data = array('ref' => $m[2], 'label' => $m[3], 'fk_soc' => $this->ids[$m[0]], 'fk_dossier' => $m[1], 'fk_type' => $m[4], 'fuel_type' => $m[6],
				'year_registration' => $m[7], 'status' => 0, 'date_revision_next' => $this->ts('2027-05-31'), 'date_insurance_end' => $this->ts('2027-01-31'));
			if ($m[5] > 0) {
				$data['power_kw'] = $m[5];
			}
			if ($m[8] !== '') {
				$data += array('sprayer_cert_ref' => $m[8], 'date_sprayer_check' => $this->ts($m[9]), 'date_sprayer_check_end' => dol_time_plus_duree($this->ts($m[9]), 3, 'y'));
			}
			$this->ids[$m[2]] = $this->object('DoliFarmMachine', 'dolifarm_machine', $data, true);
		}
		$whetna = dolifarmGetFarmWarehouse($db, $etna, 'inputs');
		$whbelice = dolifarmGetFarmWarehouse($db, $belice, 'inputs');
		$this->object('DoliFarmBuilding', 'dolifarm_building', array('ref' => 'FAB-ETNA-01', 'label' => 'Magazzino e locale fitofarmaci', 'fk_soc' => $etna, 'fk_dossier' => $de,
			'fk_plot' => $this->ids['etna1'], 'building_use' => 'Magazzino', 'fk_ownership' => 'OWNED', 'fk_warehouse' => $whetna, 'address' => 'Contrada Ciappe Bianche 12, Paternò',
			'cadastral_comune' => 'G371', 'cadastral_sheet' => '41', 'cadastral_parcel' => '119', 'surface_covered' => 180, 'volume' => 900));
		$this->object('DoliFarmBuilding', 'dolifarm_building', array('ref' => 'FAB-BEL-01', 'label' => 'Capannone attrezzi e magazzino', 'fk_soc' => $belice, 'fk_dossier' => $db2,
			'fk_plot' => $this->ids['bel1'], 'building_use' => 'Magazzino', 'fk_ownership' => 'OWNED', 'fk_warehouse' => $whbelice, 'address' => 'Contrada Seggio 4, Castelvetrano',
			'cadastral_comune' => 'C286', 'cadastral_sheet' => '57', 'cadastral_parcel' => '211', 'surface_covered' => 420, 'volume' => 2500));
		$operators = array(
			array('etna', 'OP-ETNA-01', 'Giuseppe Russo', 'DEMOOPER00000001', 'DEMO-PAN-CT-2022-0815', '2022-06-20'),
			array('etna', 'OP-ETNA-02', 'Alfio Scuderi', 'DEMOOPER00000002', 'DEMO-PAN-CT-2024-1102', '2024-03-12'),
			array('belice', 'OP-BEL-01', 'Vito Accardi', 'DEMOOPER00000003', 'DEMO-PAN-TP-2021-0399', '2021-11-08'),
			array('belice', 'OP-BEL-02', 'Nicola Ferro', 'DEMOOPER00000004', 'DEMO-PAN-TP-2023-0750', '2023-05-17'),
		);
		foreach ($operators as $o) {
			$this->ids[$o[1]] = $this->object('DoliFarmOperator', 'dolifarm_operator', array('ref' => $o[1], 'label' => $o[2], 'fk_soc' => $this->ids[$o[0]],
				'tax_code' => $o[3], 'license_type' => 1, 'license_number' => $o[4], 'license_issuer' => 'Regione Siciliana',
				'license_date' => $this->ts($o[5]), 'license_end' => dol_time_plus_duree($this->ts($o[5]), 5, 'y')));
		}

		$this->out('Crops');
		$crops = array('CROP_CITRUS_ORANGE' => 'CIDSI', 'CROP_OLIVE_OIL' => 'OLVEU', 'CROP_VINE_WINE' => 'VITVI', 'CROP_WHEAT_DURUM' => 'TRZDU',
			'CROP_FAVA_BEAN' => 'VICFX', 'CROP_CHICKPEA' => 'CIEAR');
		foreach ($crops as $ref => $eppo) {
			$id = $this->idByRef('dolifarm_crops', $ref);
			$this->ids[$ref] = $id;
			if ($id > 0) {
				$db->query("UPDATE ".$db->prefix()."dolifarm_crops SET eppo_code = '".$db->escape($eppo)."', status = 1 WHERE rowid = ".$id." AND (eppo_code IS NULL OR eppo_code = '')");
			} else {
				$this->fail('crop '.$ref, 'not in the dictionary of the crops');
			}
		}

		$this->out('Inputs');
		// Plant protection products: names and registration numbers of the previous demo data, with lots. Uses (crop, adversity,
		// dose) are only indicative for the demo: not checked on the labels
		$agrodrugs = array(
			// ref, label, reg number, manufacturer, active substance, %, formulation, hazard, safety days, max dose/ha, cu %, uses: crop eppo, adversity, dose min, dose max, unit
			array('AGRO-IT-001', 'POLTIGLIA 20 WG', '016457', 'MANICA SPA', 'Rame metallo (da poltiglia bordolese)', '20%', 'WG', 'H410', 20, 4.0, 20,
				array(array('CIDSI', 'PHYTCC', 2.5, 4.0, 'KG_HA'), array('OLVEU', 'CYCLOL', 2.5, 4.0, 'KG_HA'), array('VITVI', 'PLASVI', 2.0, 4.0, 'KG_HA'))),
			array('AGRO-IT-002', 'TIOVIT JET', '002923', 'SYNGENTA ITALIA', 'Zolfo', '80%', 'WG', 'H315', 5, 8.0, 0,
				array(array('VITVI', 'UNCINE', 2.0, 8.0, 'KG_HA'))),
			array('AGRO-IT-005', 'SPINTOR FLY', '012768', 'CORTEVA AGRISCIENCE', 'Spinosad', '0.024%', 'CB', 'NC', 7, 1.2, 0,
				array(array('OLVEU', 'DACUOL', 1.0, 1.2, 'L_HA'), array('CIDSI', 'CERTCA', 1.0, 1.2, 'L_HA'))),
			array('AGRO-IT-006', 'LASER', '011693', 'CORTEVA AGRISCIENCE', 'Spinosad', '44.2%', 'SC', 'H410', 14, 0.25, 0,
				array(array('VITVI', 'POLYBO', 0.15, 0.25, 'L_HA'))),
			array('AGRO-IT-008', 'DIPEL DF', '013062', 'SUMITOMO CHEMICAL', 'Bacillus thuringiensis subsp. kurstaki', '54%', 'WG', 'NC', 0, 1.0, 0,
				array(array('OLVEU', 'PRAYOL', 0.75, 1.0, 'KG_HA'), array('VITVI', 'POLYBO', 0.75, 1.0, 'KG_HA'))),
			array('AGRO-IT-009', 'ROUNDUP PLATINUM', '014737', 'BAYER', 'Glifosate (sale potassico)', '48%', 'SL', 'H411', 0, 3.0, 0,
				array(array('TRZDU', 'CYNDA', 1.5, 3.0, 'L_HA'))),
			array('AGRO-IT-010', 'NEEMAZAL T/S', '011561', 'ADAMA ITALIA', 'Azadiractina A (da olio di neem)', '1%', 'EC', 'H411', 3, 3.0, 0,
				array(array('CIDSI', 'PSECCI', 2.0, 3.0, 'L_HA'))),
		);
		foreach ($agrodrugs as $a) {
			$liquid = in_array($a[6], array('SC', 'SL', 'EC', 'CB'));
			$productid = $this->product($a[0], $a[1], array('status_batch' => 1, 'fk_unit' => (int) $this->value("SELECT rowid FROM ".$db->prefix()."c_units WHERE code = '".($liquid ? 'L' : 'KG')."'")));
			$this->ids[$a[0]] = $productid;
			$id = $this->idByRef('dolifarm_agrodrug', $a[0]);
			if (!$id && $productid) {
				$id = $this->object('DoliFarmAgroDrug', 'dolifarm_agrodrug', array('ref' => $a[0], 'label' => $a[1], 'fk_product' => $productid, 'reg_number' => $a[2],
					'manufacturer' => $a[3], 'active_substance' => $a[4], 'content_percentage' => $a[5], 'formulation' => $a[6], 'hazard_classification' => $a[7],
					'safety_days_default' => $a[8], 'max_dose_ha' => $a[9], 'cu_content' => ($a[10] ?: null), 'status' => 1));
				foreach ($a[11] as $use) {
					$u = new DoliFarmAgroDrugUse($db);
					$u->fk_agrodrug = $id;
					$u->crop_eppo = $use[0];
					$u->adversity_code = $use[1];
					$u->adversity_label = (string) $this->value("SELECT label FROM ".$db->prefix()."dolitrace_c_adversity WHERE code = '".$db->escape($use[1])."'");
					$u->dose_min = $use[2];
					$u->dose_max = $use[3];
					$u->dose_unit = $use[4];
					$u->safety_days = $a[8];
					if ($u->create($this->user) <= 0) {
						$this->fail('use of '.$a[1], $u);
					}
				}
			}
			$this->ids['agro_'.$a[0]] = $id;
		}
		// Fertilizers
		$fertilizers = array(
			array('FERT-01', 'Pollina pellettata 4-3-3', 'ORG', 4.0, 3.0, 3.0, 1, 'FERTILIZER', 0.38),
			array('FERT-02', 'Letame bovino maturo', 'MANURE', 0.5, 0.25, 0.6, 1, 'FERTILIZER', 0.04),
			array('FERT-03', 'Urea 46', 'MIN_N', 46.0, 0, 0, 0, 'FERTILIZER', 0.62),
			array('FERT-04', 'Concime NPK 15-15-15', 'MIN_NPK', 15.0, 15.0, 15.0, 0, 'FERTILIZER', 0.70),
			array('FERT-05', 'Solfato potassico 50', 'MIN_K', 0, 0, 50.0, 0, 'FERTILIZER', 0.85),
		);
		foreach ($fertilizers as $f) {
			$productid = $this->product('PROD-'.$f[0], $f[1], array('fk_unit' => (int) $this->value("SELECT rowid FROM ".$db->prefix()."c_units WHERE code = 'KG'")));
			$this->ids['PROD-'.$f[0]] = $productid;
			$this->ids[$f[0]] = $this->object('DoliFarmFertilizer', 'dolifarm_fertilizer', array('ref' => $f[0], 'label' => $f[1], 'fk_product' => $productid, 'fk_fert_type' => $f[2],
				'n_content' => $f[3], 'p2o5_content' => $f[4], 'k2o_content' => $f[5], 'organic' => $f[6], 'product_kind' => $f[7], 'manufacturer' => 'Fornitore demo'));
			$this->ids['price_'.$f[0]] = $f[8];
		}
		// Seeds
		$this->ids['SEED-WHEAT'] = $this->product('SEME-GRANO-SIMETO', 'Grano duro Simeto - seme certificato', array('status_batch' => 1));
		$this->ids['SEED-WHEAT-BIO'] = $this->product('SEME-GRANO-CAPPELLI-BIO', 'Grano duro Senatore Cappelli - seme biologico', array('status_batch' => 1));
		$this->ids['SEED-FAVA'] = $this->product('SEME-FAVA-BIO', 'Fava da granella - seme biologico', array('status_batch' => 1));
		$this->ids['SEED-CHICKPEA'] = $this->product('SEME-CECE', 'Cece Pascià - seme certificato', array('status_batch' => 1));
		$this->seedStatus($this->ids['SEED-WHEAT'], 'NONBIO');
		$this->seedStatus($this->ids['SEED-WHEAT-BIO'], 'BIO');
		$this->seedStatus($this->ids['SEED-FAVA'], 'BIO');
		$this->seedStatus($this->ids['SEED-CHICKPEA'], 'NONBIO');
		// Harvested products, with lots
		$outputs = array('OUT-ARANCIA-TAROCCO' => 'Arance Tarocco', 'OUT-ARANCIA-MORO' => 'Arance Moro', 'OUT-OLIVE-ETNEA' => 'Olive Nocellara etnea da olio',
			'OUT-OLIVE-BELICE' => 'Olive Nocellara del Belice da olio', 'OUT-UVA-GRILLO' => 'Uva Grillo', 'OUT-UVA-NERODAVOLA' => 'Uva Nero d\'Avola',
			'OUT-GRANO-DURO' => 'Grano duro', 'OUT-FAVA' => 'Fava secca da granella', 'OUT-CECI' => 'Ceci secchi', 'OUT-OLIO-EVO' => 'Olio extravergine di oliva');
		foreach ($outputs as $ref => $label) {
			$this->ids[$ref] = $this->product($ref, $label, array('status_batch' => 1, 'status' => 1, 'status_buy' => 0,
				'fk_unit' => (int) $this->value("SELECT rowid FROM ".$db->prefix()."c_units WHERE code = '".($ref == 'OUT-OLIO-EVO' ? 'L' : 'KG')."'")));
		}

		$this->out('Purchases');
		$prices = array('AGRO-IT-001' => 9.80, 'AGRO-IT-002' => 2.40, 'AGRO-IT-005' => 31.50, 'AGRO-IT-006' => 410.00, 'AGRO-IT-008' => 58.00, 'AGRO-IT-009' => 9.20, 'AGRO-IT-010' => 64.00);
		$buys = array(
			array('etna', 'AGRO-IT-001', 60, '2024-02-05', 'L-PO2401'), array('etna', 'AGRO-IT-005', 40, '2024-05-20', 'L-SF2405'), array('etna', 'AGRO-IT-008', 10, '2024-03-10', 'L-DP2403'),
			array('etna', 'AGRO-IT-010', 20, '2024-04-02', 'L-NZ2404'), array('etna', 'AGRO-IT-001', 60, '2025-02-03', 'L-PO2501'), array('etna', 'AGRO-IT-005', 40, '2025-05-12', 'L-SF2505'),
			array('etna', 'AGRO-IT-008', 10, '2025-03-14', 'L-DP2503'), array('etna', 'AGRO-IT-010', 20, '2025-04-08', 'L-NZ2504'), array('etna', 'AGRO-IT-001', 60, '2026-02-09', 'L-PO2601'),
			array('etna', 'AGRO-IT-005', 40, '2026-05-11', 'L-SF2605'), array('etna', 'AGRO-IT-010', 20, '2026-04-07', 'L-NZ2604'),
			array('belice', 'AGRO-IT-001', 100, '2024-03-01', 'L-PO2402'), array('belice', 'AGRO-IT-002', 200, '2024-03-01', 'L-TJ2401'), array('belice', 'AGRO-IT-006', 3, '2024-04-15', 'L-LS2404'),
			array('belice', 'AGRO-IT-005', 40, '2024-06-10', 'L-SF2406'), array('belice', 'AGRO-IT-009', 30, '2023-10-20', 'L-RU2310'),
			array('belice', 'AGRO-IT-001', 100, '2025-03-03', 'L-PO2502'), array('belice', 'AGRO-IT-002', 200, '2025-03-03', 'L-TJ2501'), array('belice', 'AGRO-IT-006', 3, '2025-04-11', 'L-LS2504'),
			array('belice', 'AGRO-IT-005', 40, '2025-06-09', 'L-SF2506'), array('belice', 'AGRO-IT-009', 30, '2025-10-14', 'L-RU2510'),
			array('belice', 'AGRO-IT-001', 100, '2026-03-02', 'L-PO2603'), array('belice', 'AGRO-IT-002', 200, '2026-03-02', 'L-TJ2601'), array('belice', 'AGRO-IT-006', 3, '2026-04-13', 'L-LS2604'),
			array('belice', 'AGRO-IT-005', 40, '2026-06-08', 'L-SF2606'),
		);
		// Supplier orders: seeds from the organic seed supplier (conventional seeds from the input supplier), the rest from the input supplier
		$inputs = $this->ids['inputsupplier'];
		$seeds = $this->ids['seedsupplier'];
		foreach ($buys as $b) {
			$this->purchase($this->ids[$b[0]], $inputs, $this->ids[$b[1]], $b[2], $prices[$b[1]], 22, $b[3], $b[4], 'Acquisto demo '.$b[1].' '.$b[4]);
		}
		foreach (array(2024, 2025, 2026) as $year) {
			$this->purchase($etna, $inputs, $this->ids['PROD-FERT-01'], 9000, $this->ids['price_FERT-01'], 4, $year.'-01-20', '', 'Acquisto demo pollina '.$year);
			$this->purchase($etna, $inputs, $this->ids['PROD-FERT-02'], 30000, $this->ids['price_FERT-02'], 4, ($year - 1).'-11-05', '', 'Acquisto demo letame '.$year);
			$this->purchase($belice, $inputs, $this->ids['PROD-FERT-03'], 3000, $this->ids['price_FERT-03'], 4, $year.'-01-25', '', 'Acquisto demo urea '.$year);
			$this->purchase($belice, $inputs, $this->ids['PROD-FERT-04'], 5000, $this->ids['price_FERT-04'], 4, ($year - 1).'-10-25', '', 'Acquisto demo NPK '.$year);
			$this->purchase($belice, $inputs, $this->ids['PROD-FERT-05'], 2500, $this->ids['price_FERT-05'], 4, $year.'-02-15', '', 'Acquisto demo solfato potassico '.$year);
		}
		$this->purchase($etna, $seeds, $this->ids['SEED-WHEAT-BIO'], 1300, 1.10, 4, '2023-11-02', 'L-SC2311', 'Acquisto demo seme Cappelli 2023');
		$this->purchase($etna, $seeds, $this->ids['SEED-FAVA'], 1100, 1.60, 4, '2024-11-04', 'L-SFV2411', 'Acquisto demo seme fava 2024');
		$this->purchase($etna, $seeds, $this->ids['SEED-WHEAT-BIO'], 1300, 1.15, 4, '2025-11-03', 'L-SC2511', 'Acquisto demo seme Cappelli 2025');
		$this->purchase($belice, $inputs, $this->ids['SEED-WHEAT'], 2300, 0.95, 4, '2023-11-06', 'L-SS2311', 'Acquisto demo seme Simeto 2023');
		$this->purchase($belice, $inputs, $this->ids['SEED-CHICKPEA'], 1300, 1.40, 4, '2025-01-20', 'L-SCC2501', 'Acquisto demo seme cece 2025');
		$this->purchase($belice, $inputs, $this->ids['SEED-WHEAT'], 2300, 0.98, 4, '2025-11-04', 'L-SS2511', 'Acquisto demo seme Simeto 2025');

		$this->out('Standard costs');
		$costs = array(
			array('SOIL_PLOWING', 'Aratura', 150, 45, 70, 0), array('SOIL_HARROWING', 'Erpicatura', 60, 18, 30, 0), array('SOWING_DIRECT', 'Semina', 50, 15, 35, 0),
			array('TT_Pesticide', 'Trattamento fitosanitario', 45, 14, 22, 0), array('TT_Fertiliser', 'Concimazione', 30, 10, 15, 0), array('PRUNING', 'Potatura', 1200, 360, 20, 0),
			array('GREEN_PRUNING', 'Potatura verde', 600, 180, 10, 0), array('SHREDDING', 'Trinciatura', 60, 18, 28, 0), array('IRRIGATION', 'Irrigazione', 30, 9, 12, 0),
			array('HARVEST', 'Raccolta', 0, 0, 0, 450), array('SCOUTING', 'Monitoraggio', 20, 6, 0, 0),
		);
		foreach ($costs as $c) {
			if ((int) $this->value("SELECT COUNT(*) FROM ".$db->prefix()."dolifarm_dolifarmestimatecosts WHERE fk_operation_type = '".$c[0]."'") > 0) {
				continue;
			}
			$cost = new DoliFarmEstimateCosts($db);
			$cost->label = $c[1];
			$cost->fk_operation_type = $c[0];
			$cost->date_start = $this->ts('2024-01-01');
			$cost->duration_min_ha = $c[2];
			$cost->cost_manpower_ha = $c[3];
			$cost->cost_machine_ha = $c[4];
			$cost->cost_service_ha = $c[5];
			$cost->cost_std_ha = $c[3] + $c[4] + $c[5];
			if ($cost->create($this->user) <= 0) {
				$this->fail('standard cost '.$c[0], $cost);
			}
		}
	}

	/*
	 * Scenario: seasons
	 */

	/**
	 * Production plan of a plot for a season, found by plot, season and crop
	 *
	 * @param string $plotkey  Key of the plot
	 * @param int    $season   Season
	 * @param string $crop     Ref of the crop
	 * @param string $output   Ref of the harvested product
	 * @param string $label    Label
	 * @param string $start    Start
	 * @param string $end      End
	 * @param float  $yield    Estimated yield (kg)
	 * @param int    $status   0 draft, 1 in progress, 2 closed
	 * @return int
	 */
	public function plan($plotkey, $season, $crop, $output, $label, $start, $end, $yield, $status)
	{
		$db = $this->db;
		$plotid = $this->ids[$plotkey];
		$id = (int) $this->value("SELECT rowid FROM ".$db->prefix()."dolitrace_cropplan WHERE fk_plot = ".((int) $plotid)." AND season = ".((int) $season)." AND fk_crop = ".((int) $this->ids[$crop]));
		if ($id > 0) {
			$this->ids['planstatus_'.$id] = $status;
			return $id;
		}
		$plot = new DoliFarmPlot($db);
		$plot->fetch($plotid);
		$plan = new DoliTraceCropPlan($db);
		$plan->label = $label;
		$plan->fk_soc = (int) $plot->fk_soc;
		$plan->fk_dossier = (int) $plot->fk_dossier;
		$plan->fk_plot = $plotid;
		$plan->fk_crop = $this->ids[$crop];
		$plan->fk_output_product = $this->ids[$output];
		$plan->season = $season;
		$plan->date_start = $this->ts($start);
		$plan->date_end = $this->ts($end);
		$plan->estimated_yield = $yield;
		$plan->status = 0;
		if ($plan->create($this->user) <= 0) {
			$this->fail('production plan '.$label, $plan);
			return 0;
		}
		if ($status >= 1) {
			$plan->fetch($plan->id);
			if ($plan->validate($this->user) < 0) {
				$this->fail('validation of the production plan '.$label, $plan);
			}
		}
		// Closed at the end, when its operations, treatments and harvests are there
		$this->ids['planstatus_'.$plan->id] = $status;
		$this->out('  production plan '.$label);
		return (int) $plan->id;
	}

	/**
	 * Close the production plans of the past seasons
	 *
	 * @return void
	 */
	public function closePlans()
	{
		foreach ($this->ids as $key => $status) {
			if (strpos($key, 'planstatus_') !== 0 || $status != 2) {
				continue;
			}
			$plan = new DoliTraceCropPlan($this->db);
			if ($plan->fetch((int) substr($key, 11)) > 0 && (int) $plan->status == DoliTraceCropPlan::STATUS_VALIDATED) {
				if ($plan->setStatut(DoliTraceCropPlan::STATUS_CLOSED, null, '', 'DOLITRACE_CROPPLAN_CLOSE') < 0) {
					$this->fail('closing of the production plan '.$plan->label, $plan);
				}
			}
		}
	}

	/**
	 * Operations of a production plan (only when it has none)
	 *
	 * @param int                            $planid Plan
	 * @param array<int,array<string,mixed>> $ops    Operations: type, date, label, and other fields
	 * @return void
	 */
	public function operations($planid, $ops)
	{
		if (!$planid || (int) $this->value("SELECT COUNT(*) FROM ".$this->db->prefix()."dolitrace_operation WHERE fk_cropplan = ".((int) $planid)) > 0) {
			return;
		}
		foreach ($ops as $data) {
			$op = new DoliTraceOperation($this->db);
			$op->fk_cropplan = $planid;
			$op->fk_operation_type = $data['type'];
			$op->date_op = $this->ts($data['date']);
			$op->label = $data['label'];
			$op->fk_user_operator = $this->user->id;
			$op->status = 0;
			foreach ($data as $field => $value) {
				if (!in_array($field, array('type', 'date', 'label'))) {
					$op->$field = $value;
				}
			}
			if ($op->create($this->user) <= 0) {
				$this->fail('operation '.$data['label'].' '.$data['date'], $op);
				continue;
			}
			$op->fetch($op->id);
			if ($op->validate($this->user) < 0) {
				$this->fail('validation of the operation '.$data['label'].' '.$data['date'], $op);
			}
		}
	}

	/**
	 * Harvests of a production plan (only when it has none), validated: the product goes in the warehouse of the farm
	 *
	 * @param int                                          $planid   Plan
	 * @param string                                       $output   Ref of the product
	 * @param array<int,array{0:string,1:float,2:string}> $harvests Date, kg, label
	 * @return void
	 */
	public function harvests($planid, $output, $harvests)
	{
		if (!$planid || (int) $this->value("SELECT COUNT(*) FROM ".$this->db->prefix()."dolitrace_harvest WHERE fk_cropplan = ".((int) $planid)) > 0) {
			return;
		}
		foreach ($harvests as $h) {
			$harvest = new DoliTraceHarvest($this->db);
			$harvest->fk_cropplan = $planid;
			$harvest->label = $h[2];
			$harvest->date_harvest = $this->ts($h[0]);
			$harvest->fk_product_out = $this->ids[$output];
			$harvest->qty_harvested = round($h[1]);
			$harvest->qty_unit = 'kg';
			$harvest->status = 0;
			if ($harvest->create($this->user) <= 0) {
				$this->fail('harvest '.$h[2], $harvest);
				continue;
			}
			$harvest->fetch($harvest->id);
			if ($harvest->validate($this->user) < 0) {
				$this->fail('validation of the harvest '.$h[2], $harvest);
			}
		}
	}

	/**
	 * Treatments of a farm through the import of the register (as the import page), then validated
	 *
	 * @param string                          $farmkey Key of the farm
	 * @param array<int,array<string,string>> $lines   Lines (columns of the export), 'record' groups the lines of a record
	 * @return void
	 */
	public function treatments($farmkey, $lines)
	{
		$db = $this->db;
		$socid = $this->ids[$farmkey];
		$todo = array();
		foreach ($lines as $line) {
			// A record already made has the same farm and date
			$exists = (int) $this->value("SELECT COUNT(*) FROM ".$db->prefix()."dolitrace_treatment WHERE fk_soc = ".((int) $socid)." AND date_start = '".$db->idate($this->ts($line['date_start']))."'");
			if (!$exists) {
				$todo[] = $line;
			}
		}
		if (count($todo)) {
			$out = fopen('php://temp', 'r+');
			fputcsv($out, DoliTraceTreatmentExport::$csvColumns, ';', '"', '\\');
			foreach ($todo as $line) {
				$row = array();
				foreach (DoliTraceTreatmentExport::$csvColumns as $column) {
					$row[] = $line[$column] ?? '';
				}
				fputcsv($out, $row, ';', '"', '\\');
			}
			rewind($out);
			$csv = stream_get_contents($out);
			fclose($out);

			$import = new DoliTraceTreatmentImport($db);
			if ($import->parse($csv) < 0) {
				$this->fail('treatments of '.$farmkey, $import);
				return;
			}
			if ($import->import($this->user, $socid, false) < 0) {
				$this->fail('import of the treatments of '.$farmkey, $import);
				return;
			}
			foreach ($import->results as $result) {
				if (count($result['errors']) || !$result['id']) {
					$this->fail('treatment '.$result['record'].' of '.$farmkey, implode(' ', $result['errors']));
				}
			}
		}

		// Drafts of the farm, from the oldest: validated as the user does after the import
		$nb = 0;
		$resql = $db->query("SELECT rowid FROM ".$db->prefix()."dolitrace_treatment WHERE fk_soc = ".((int) $socid)." AND status = 0 ORDER BY date_start, rowid");
		$ids = array();
		while ($resql && ($obj = $db->fetch_object($resql))) {
			$ids[] = (int) $obj->rowid;
		}
		foreach ($ids as $id) {
			$treatment = new DoliTraceTreatment($db);
			$treatment->fetch($id);
			if ($treatment->validate($this->user) < 0) {
				$this->fail('validation of the treatment of '.dol_print_date($treatment->date_start, 'dayhour').' of '.$farmkey, $treatment);
				continue;
			}
			$nb++;
		}
		$this->out('  '.$nb.' treatments of '.$farmkey.' validated');
	}

	/**
	 * Line of a treatment for the import
	 *
	 * @param string               $record    Record key
	 * @param string               $kind      plant_protection or fertilization
	 * @param string               $date      Date and time
	 * @param string               $plot      Ref of the plot
	 * @param float                $ha        Treated surface
	 * @param string               $product   Reg number (plant protection) or ref of the fertilizer
	 * @param string               $adversity EPPO code of the adversity ('' for fertilizers)
	 * @param float                $dose      Dose
	 * @param string               $unit      Dose unit
	 * @param string               $batch     Lot
	 * @param array<string,string> $more      Other columns
	 * @return array<string,string>
	 */
	public function tline($record, $kind, $date, $plot, $ha, $product, $adversity, $dose, $unit, $batch, $more)
	{
		$line = array('record' => $record, 'kind' => $kind, 'technical_type' => 'surfaces', 'date_start' => $date, 'target_type' => 'plot', 'plot_ref' => $plot,
			'treated_qty' => (string) $ha, 'treated_unit' => 'HA', 'dose' => (string) $dose, 'dose_unit' => $unit, 'batch' => $batch);
		if ($kind == 'plant_protection') {
			$line += array('product_type' => 'plant_protection_product', 'reg_number' => $product, 'adversity_eppo' => $adversity);
		} else {
			$line += array('product_type' => 'fertilizer', 'product_ref' => $product);
		}
		return $line + $more;
	}

	/**
	 * Three seasons of both farms and the next season planned
	 *
	 * @return void
	 */
	public function buildSeasons()
	{
		$this->out('Seasons');
		$factor = array(2024 => 0.7, 2025 => 1.0, 2026 => 1.05);		// 2024: drought in Sicily
		$etnaops = array('operator_license' => 'DEMO-PAN-CT-2022-0815', 'machine' => 'MAC-ETNA-02', 'weather' => 'Sereno, vento debole', 'application_method' => 'PPP_ATOMIZER');
		$etnaspread = array('operator_license' => 'DEMO-PAN-CT-2024-1102', 'machine' => 'MAC-ETNA-04', 'weather' => 'Sereno', 'application_method' => 'MIN_SOIL');
		$belops = array('operator_license' => 'DEMO-PAN-TP-2021-0399', 'machine' => 'MAC-BEL-03', 'weather' => 'Sereno, vento debole', 'application_method' => 'PPP_ATOMIZER');
		$belbar = array('operator_license' => 'DEMO-PAN-TP-2023-0750', 'machine' => 'MAC-BEL-06', 'weather' => 'Coperto, assenza di vento', 'application_method' => 'PPP_SPRAYER');
		$belspread = array('operator_license' => 'DEMO-PAN-TP-2023-0750', 'machine' => 'MAC-BEL-04', 'weather' => 'Sereno', 'application_method' => 'MIN_SOIL');
		$etnalines = array();
		$bellines = array();

		foreach (array(2024, 2025, 2026) as $y) {
			$f = $factor[$y];
			$closed = ($y < 2026 ? 2 : 1);
			$yy = substr((string) $y, 2);

			// Etna: oranges
			foreach (array(array('etna1', 'ETNA-01', 'OUT-ARANCIA-TAROCCO', 'Arance Tarocco', 4.2, 25000), array('etna2', 'ETNA-02', 'OUT-ARANCIA-MORO', 'Arance Moro', 2.8, 22000)) as $o) {
				$plan = $this->plan($o[0], $y, 'CROP_CITRUS_ORANGE', $o[2], $o[3].' '.$y.' - '.$o[1], $y.'-01-01', $y.'-12-31', $o[4] * $o[5], $closed);
				$this->operations($plan, array(
					array('type' => 'PRUNING', 'date' => $y.'-03-12 07:30', 'label' => 'Potatura di produzione', 'duration_min' => (int) (1200 * $o[4])),
					array('type' => 'SHREDDING', 'date' => $y.'-03-28 08:00', 'label' => 'Trinciatura dei residui di potatura', 'fk_machine' => $this->ids['MAC-ETNA-03'], 'duration_min' => (int) (60 * $o[4])),
					array('type' => 'SCOUTING', 'date' => $y.'-04-14 09:00', 'label' => 'Monitoraggio cotonello e mosca della frutta (trappole)', 'note_public' => 'Catture sotto soglia, presenza di cotonello sui frutticini.'),
					array('type' => 'IRRIGATION', 'date' => $y.'-06-15 06:00', 'label' => 'Irrigazione a goccia', 'irrigation_method' => 'DRIP', 'water_volume' => 400 * $o[4], 'irrigated_area' => $o[4], 'duration_min' => 360),
					array('type' => 'IRRIGATION', 'date' => $y.'-07-15 06:00', 'label' => 'Irrigazione a goccia', 'irrigation_method' => 'DRIP', 'water_volume' => 550 * $o[4], 'irrigated_area' => $o[4], 'duration_min' => 480),
					array('type' => 'IRRIGATION', 'date' => $y.'-08-14 06:00', 'label' => 'Irrigazione a goccia', 'irrigation_method' => 'DRIP', 'water_volume' => 550 * $o[4], 'irrigated_area' => $o[4], 'duration_min' => 480),
					array('type' => 'IRRIGATION', 'date' => $y.'-09-12 06:00', 'label' => 'Irrigazione a goccia', 'irrigation_method' => 'DRIP', 'water_volume' => 350 * $o[4], 'irrigated_area' => $o[4], 'duration_min' => 300),
				));
				$kg = $o[4] * $o[5] * $f;
				$this->harvests($plan, $o[2], array(array($y.'-02-10', $kg * 0.55, 'Raccolta '.$o[3].' '.$y.' - primo stacco'), array($y.'-03-06', $kg * 0.45, 'Raccolta '.$o[3].' '.$y.' - secondo stacco')));
				$etnalines[] = $this->tline('E'.$y.$o[1].'F', 'fertilization', $y.'-02-24 08:00', $o[1], $o[4], 'FERT-01', '', 500, 'KG_HA', '', $etnaspread);
				$etnalines[] = $this->tline('E'.$y.$o[1].'N', 'plant_protection', $y.'-04-16 07:00', $o[1], $o[4], '011561', 'PSECCI', 2.5, 'L_HA', 'L-NZ'.$yy.'04', array('water_volume' => (string) (1500 * $o[4]), 'justification_type' => 'TRAP_CATCHES', 'justification_ref' => 'Monitoraggio del 14/04', 'bbch' => '61') + $etnaops);
				$etnalines[] = $this->tline('E'.$y.$o[1].'S', 'plant_protection', $y.'-09-22 07:00', $o[1], $o[4], '012768', 'CERTCA', 1.2, 'L_HA', 'L-SF'.$yy.'05', array('water_volume' => (string) (20 * $o[4]), 'application_method' => 'PPP_SPRAYER', 'bbch' => '79') + $etnaops);
				$etnalines[] = $this->tline('E'.$y.$o[1].'C', 'plant_protection', $y.'-10-14 07:30', $o[1], $o[4], '016457', 'PHYTCC', 3, 'KG_HA', 'L-PO'.$yy.'01', array('water_volume' => (string) (1200 * $o[4]), 'bbch' => '81') + $etnaops);
			}
			// Etna: olives, in conversion from 2024
			$plan = $this->plan('etna3', $y, 'CROP_OLIVE_OIL', 'OUT-OLIVE-ETNEA', 'Olive Nocellara etnea '.$y.' - ETNA-03', $y.'-01-01', $y.'-12-31', 3.5 * 6000, $closed);
			$this->operations($plan, array(
				array('type' => 'PRUNING', 'date' => $y.'-02-18 07:30', 'label' => 'Potatura', 'duration_min' => 2800),
				array('type' => 'SHREDDING', 'date' => $y.'-03-04 08:00', 'label' => 'Trinciatura ramaglia', 'fk_machine' => $this->ids['MAC-ETNA-03'], 'duration_min' => 210),
				array('type' => 'SCOUTING', 'date' => $y.'-07-10 09:00', 'label' => 'Monitoraggio mosca dell\'olivo (trappole cromotropiche)', 'note_public' => 'Prime catture di mosca, punture sotto soglia.'),
			));
			$this->harvests($plan, 'OUT-OLIVE-ETNEA', array(array($y.'-10-20', 3.5 * 6000 * $f * 0.6, 'Raccolta olive '.$y.' - Poggio Rosso'), array($y.'-10-29', 3.5 * 6000 * $f * 0.4, 'Raccolta olive '.$y.' - Poggio Rosso (fine)')));
			$etnalines[] = $this->tline('E'.$y.'O3F', 'fertilization', $y.'-02-26 08:00', 'ETNA-03', 3.5, 'FERT-01', '', 400, 'KG_HA', '', $etnaspread);
			$etnalines[] = $this->tline('E'.$y.'O3C', 'plant_protection', $y.'-03-14 07:30', 'ETNA-03', 3.5, '016457', 'CYCLOL', 3, 'KG_HA', 'L-PO'.$yy.'01', array('water_volume' => '3500', 'bbch' => '09') + $etnaops);
			$etnalines[] = $this->tline('E'.$y.'O3D', 'plant_protection', $y.'-04-22 07:00', 'ETNA-03', 3.5, '013062', 'PRAYOL', 1, 'KG_HA', ($y == 2026 ? 'L-DP2503' : 'L-DP'.$yy.'03'), array('water_volume' => '3500', 'bbch' => '55') + $etnaops);
			$etnalines[] = $this->tline('E'.$y.'O3S1', 'plant_protection', $y.'-07-21 06:30', 'ETNA-03', 3.5, '012768', 'DACUOL', 1, 'L_HA', 'L-SF'.$yy.'05', array('water_volume' => '70', 'application_method' => 'PPP_SPRAYER', 'justification_type' => 'TRAP_CATCHES', 'justification_ref' => 'Catture del 10/07', 'bbch' => '75') + $etnaops);
			$etnalines[] = $this->tline('E'.$y.'O3S2', 'plant_protection', $y.'-08-26 06:30', 'ETNA-03', 3.5, '012768', 'DACUOL', 1, 'L_HA', 'L-SF'.$yy.'05', array('water_volume' => '70', 'application_method' => 'PPP_SPRAYER', 'bbch' => '79') + $etnaops);

			// Belice: olives
			$plan = $this->plan('bel1', $y, 'CROP_OLIVE_OIL', 'OUT-OLIVE-BELICE', 'Olive Nocellara del Belice '.$y.' - BEL-01', $y.'-01-01', $y.'-12-31', 8 * 7000, $closed);
			$this->operations($plan, array(
				array('type' => 'PRUNING', 'date' => $y.'-02-05 07:30', 'label' => 'Potatura', 'duration_min' => 6400),
				array('type' => 'SHREDDING', 'date' => $y.'-02-25 08:00', 'label' => 'Trinciatura ramaglia', 'fk_machine' => $this->ids['MAC-BEL-01'], 'duration_min' => 480),
				array('type' => 'SOIL_HARROWING', 'date' => $y.'-04-08 08:00', 'label' => 'Erpicatura superficiale', 'fk_machine' => $this->ids['MAC-BEL-04'], 'duration_min' => 480),
				array('type' => 'SCOUTING', 'date' => $y.'-07-12 09:00', 'label' => 'Monitoraggio mosca dell\'olivo', 'note_public' => 'Catture in aumento dalla seconda decade di luglio.'),
			));
			$this->harvests($plan, 'OUT-OLIVE-BELICE', array(array($y.'-10-08', 8 * 7000 * $f * 0.5, 'Raccolta olive '.$y.' - Seggio'), array($y.'-10-16', 8 * 7000 * $f * 0.5, 'Raccolta olive '.$y.' - Seggio (fine)')));
			$bellines[] = $this->tline('B'.$y.'O1F', 'fertilization', $y.'-02-12 08:00', 'BEL-01', 8, 'FERT-04', '', 300, 'KG_HA', '', $belspread);
			$bellines[] = $this->tline('B'.$y.'O1C', 'plant_protection', $y.'-03-20 07:30', 'BEL-01', 8, '016457', 'CYCLOL', 3, 'KG_HA', 'L-PO'.$yy.($y == 2026 ? '03' : '02'), array('water_volume' => '8000', 'bbch' => '09') + $belops);
			$bellines[] = $this->tline('B'.$y.'O1S1', 'plant_protection', $y.'-07-24 06:30', 'BEL-01', 8, '012768', 'DACUOL', 1, 'L_HA', 'L-SF'.$yy.'06', array('water_volume' => '160', 'application_method' => 'PPP_SPRAYER', 'justification_type' => 'TRAP_CATCHES', 'justification_ref' => 'Catture del 12/07', 'bbch' => '75') + $belops);
			$bellines[] = $this->tline('B'.$y.'O1S2', 'plant_protection', $y.'-09-02 06:30', 'BEL-01', 8, '012768', 'DACUOL', 1, 'L_HA', 'L-SF'.$yy.'06', array('water_volume' => '160', 'application_method' => 'PPP_SPRAYER', 'bbch' => '81') + $belops);

			// Belice: vineyards
			foreach (array(array('bel2', 'BEL-02', 'OUT-UVA-GRILLO', 'Uva Grillo', 5.0, 11000, '-08-27', '-09-04'), array('bel3', 'BEL-03', 'OUT-UVA-NERODAVOLA', 'Uva Nero d\'Avola', 3.5, 9000, '-09-15', '-09-19')) as $v) {
				$plan = $this->plan($v[0], $y, 'CROP_VINE_WINE', $v[2], $v[3].' '.$y.' - '.$v[1], $y.'-01-01', $y.'-12-31', $v[4] * $v[5], $closed);
				$this->operations($plan, array(
					array('type' => 'PRUNING', 'date' => $y.'-01-22 07:30', 'label' => 'Potatura secca', 'duration_min' => (int) (1500 * $v[4])),
					array('type' => 'SHREDDING', 'date' => $y.'-02-14 08:00', 'label' => 'Trinciatura sarmenti', 'fk_machine' => $this->ids['MAC-BEL-02'], 'duration_min' => (int) (70 * $v[4])),
					array('type' => 'GREEN_PRUNING', 'date' => $y.'-05-08 07:00', 'label' => 'Spollonatura e scacchiatura', 'duration_min' => (int) (700 * $v[4])),
					array('type' => 'SCOUTING', 'date' => $y.'-06-05 09:00', 'label' => 'Monitoraggio tignoletta (trappole a feromoni)', 'note_public' => 'Volo della seconda generazione in corso.'),
					array('type' => 'VERAISON', 'date' => $y.'-07-20 09:00', 'label' => 'Invaiatura'),
				));
				$this->harvests($plan, $v[2], array(array($y.$v[6], $v[4] * $v[5] * $f * 0.6, 'Vendemmia '.$v[3].' '.$y), array($y.$v[7], $v[4] * $v[5] * $f * 0.4, 'Vendemmia '.$v[3].' '.$y.' (fine)')));
				$lot = ($y == 2026 ? '03' : '02');
				$bellines[] = $this->tline('B'.$y.$v[1].'F', 'fertilization', $y.'-03-03 '.($v[1] == 'BEL-02' ? '08:00' : '11:00'), $v[1], $v[4], 'FERT-05', '', 250, 'KG_HA', '', $belspread);
				foreach (array(array('-05-06', '016457', 'PLASVI', 2.5, 'KG_HA', 'L-PO'.$yy.$lot, '57'), array('-05-12', '002923', 'UNCINE', 4, 'KG_HA', 'L-TJ'.$yy.'01', '61'),
					array('-05-27', '016457', 'PLASVI', 2.5, 'KG_HA', 'L-PO'.$yy.$lot, '68'), array('-06-03', '002923', 'UNCINE', 4, 'KG_HA', 'L-TJ'.$yy.'01', '71'),
					array('-06-17', '016457', 'PLASVI', 2.5, 'KG_HA', 'L-PO'.$yy.$lot, '75'), array('-06-21', '011693', 'POLYBO', 0.2, 'L_HA', 'L-LS'.$yy.'04', '75'),
					array('-06-26', '002923', 'UNCINE', 4, 'KG_HA', 'L-TJ'.$yy.'01', '77')) as $i => $t) {
					$more = array('water_volume' => (string) (600 * $v[4]), 'bbch' => $t[6]) + $belops;
					if ($t[2] == 'POLYBO') {
						$more += array('justification_type' => 'TRAP_CATCHES', 'justification_ref' => 'Catture del 05/06');
					}
					$bellines[] = $this->tline('B'.$y.$v[1].'T'.$i, 'plant_protection', $y.$t[0].' '.($v[1] == 'BEL-02' ? '06:30' : '10:00'), $v[1], $v[4], $t[1], $t[2], $t[3], $t[4], $t[5], $more);
				}
			}

			// Arable land: Etna durum wheat / faba bean rotation, Belice durum wheat / chickpea rotation
			if ($y != 2025) {
				$plan = $this->plan('etna4', $y, 'CROP_WHEAT_DURUM', 'OUT-GRANO-DURO', 'Grano duro Senatore Cappelli '.$y.' - ETNA-04', ($y - 1).'-10-20', $y.'-07-15', 6 * 3000, 2);
				$this->operations($plan, array(
					array('type' => 'SOIL_HARROWING', 'date' => ($y - 1).'-10-28 08:00', 'label' => 'Erpicatura', 'duration_min' => 360),
					array('type' => 'SOWING_DIRECT', 'date' => ($y - 1).'-11-21 08:00', 'label' => 'Semina grano duro Senatore Cappelli', 'fk_product' => $this->ids['SEED-WHEAT-BIO'], 'qty_used' => 1200, 'batch_input' => 'L-SC'.substr((string) ($y - 1), 2).'11', 'seed_status' => 'BIO', 'duration_min' => 300),
					array('type' => 'SCOUTING', 'date' => $y.'-03-25 09:00', 'label' => 'Controllo ruggini e septoria', 'note_public' => 'Coltura sana.'),
				));
				$this->harvests($plan, 'OUT-GRANO-DURO', array(array($y.'-06-18', 6 * 3000 * $f, 'Trebbiatura grano duro '.$y.' - Piana di Lentini')));
				$etnalines[] = $this->tline('E'.$y.'W4F', 'fertilization', ($y - 1).'-11-15 08:00', 'ETNA-04', 6, 'FERT-01', '', 500, 'KG_HA', '', $etnaspread);
			} else {
				$plan = $this->plan('etna4', $y, 'CROP_FAVA_BEAN', 'OUT-FAVA', 'Fava da granella '.$y.' - ETNA-04', '2024-11-05', '2025-07-10', 6 * 2000, 2);
				$this->operations($plan, array(
					array('type' => 'SOIL_HARROWING', 'date' => '2024-11-12 08:00', 'label' => 'Erpicatura', 'duration_min' => 360),
					array('type' => 'SOWING_DIRECT', 'date' => '2024-11-26 08:00', 'label' => 'Semina fava', 'fk_product' => $this->ids['SEED-FAVA'], 'qty_used' => 1080, 'batch_input' => 'L-SFV2411', 'seed_status' => 'BIO', 'duration_min' => 300),
				));
				$this->harvests($plan, 'OUT-FAVA', array(array('2025-06-24', 6 * 2000, 'Trebbiatura fava 2025 - Piana di Lentini')));
			}
			if ($y != 2025) {
				$plan = $this->plan('bel4', $y, 'CROP_WHEAT_DURUM', 'OUT-GRANO-DURO', 'Grano duro Simeto '.$y.' - BEL-04', ($y - 1).'-10-15', $y.'-07-10', 10 * 3800, 2);
				$this->operations($plan, array(
					array('type' => 'SOIL_PLOWING', 'date' => ($y - 1).'-10-22 08:00', 'label' => 'Aratura', 'fk_machine' => $this->ids['MAC-BEL-01'], 'duration_min' => 1500, 'depth_cm' => 30),
					array('type' => 'SOIL_HARROWING', 'date' => ($y - 1).'-11-08 08:00', 'label' => 'Erpicatura', 'fk_machine' => $this->ids['MAC-BEL-04'], 'duration_min' => 600),
					array('type' => 'SOWING_DIRECT', 'date' => ($y - 1).'-11-18 08:00', 'label' => 'Semina grano duro Simeto', 'fk_machine' => $this->ids['MAC-BEL-05'], 'fk_product' => $this->ids['SEED-WHEAT'], 'qty_used' => 2200, 'batch_input' => 'L-SS'.substr((string) ($y - 1), 2).'11', 'seed_status' => 'NONBIO', 'duration_min' => 500),
					array('type' => 'SCOUTING', 'date' => $y.'-03-20 09:00', 'label' => 'Controllo ruggini e septoria', 'note_public' => 'Lievi sintomi di septoria sulle foglie basali, sotto soglia.'),
				));
				$this->harvests($plan, 'OUT-GRANO-DURO', array(array($y.'-06-12', 10 * 3800 * $f, 'Trebbiatura grano duro '.$y.' - Timpone')));
				$yp = substr((string) ($y - 1), 2);
				$bellines[] = $this->tline('B'.$y.'W4R', 'plant_protection', ($y - 1).'-11-05 08:00', 'BEL-04', 10, '014737', 'CYNDA', 2.5, 'L_HA', 'L-RU'.$yp.'10', array('water_volume' => '2000', 'bbch' => '00') + $belbar);
				$bellines[] = $this->tline('B'.$y.'W4F1', 'fertilization', ($y - 1).'-11-16 08:00', 'BEL-04', 10, 'FERT-04', '', 200, 'KG_HA', '', $belspread);
				$bellines[] = $this->tline('B'.$y.'W4F2', 'fertilization', $y.'-02-20 08:00', 'BEL-04', 10, 'FERT-03', '', 150, 'KG_HA', '', $belspread);
				$bellines[] = $this->tline('B'.$y.'W4F3', 'fertilization', $y.'-03-16 08:00', 'BEL-04', 10, 'FERT-03', '', 100, 'KG_HA', '', $belspread);
			} else {
				$plan = $this->plan('bel4', $y, 'CROP_CHICKPEA', 'OUT-CECI', 'Cece '.$y.' - BEL-04', '2025-01-15', '2025-07-31', 10 * 1500, 2);
				$this->operations($plan, array(
					array('type' => 'SOIL_HARROWING', 'date' => '2025-01-28 08:00', 'label' => 'Erpicatura', 'fk_machine' => $this->ids['MAC-BEL-04'], 'duration_min' => 600),
					array('type' => 'SOWING_DIRECT', 'date' => '2025-02-10 08:00', 'label' => 'Semina cece', 'fk_machine' => $this->ids['MAC-BEL-05'], 'fk_product' => $this->ids['SEED-CHICKPEA'], 'qty_used' => 1200, 'batch_input' => 'L-SCC2501', 'seed_status' => 'NONBIO', 'duration_min' => 500),
				));
				$this->harvests($plan, 'OUT-CECI', array(array('2025-07-08', 10 * 1500, 'Trebbiatura ceci 2025 - Timpone')));
			}
			// Olives of the season to the mill the day after the harvest: the warehouse of the farm never holds lots of two seasons
			// with a different organic status (first year of conversion, then in conversion)
			$this->buildMill();
		}
		// Next season, planned: faba bean after wheat on ETNA-04, wheat again on BEL-04 (the rotation warning shows)
		$this->plan('etna4', 2027, 'CROP_FAVA_BEAN', 'OUT-FAVA', 'Fava da granella 2027 - ETNA-04', '2026-11-10', '2027-07-10', 6 * 2000, 0);
		$this->plan('bel4', 2027, 'CROP_WHEAT_DURUM', 'OUT-GRANO-DURO', 'Grano duro Simeto 2027 - BEL-04', '2026-10-15', '2027-07-10', 10 * 3800, 0);

		$this->out('Treatments');
		$this->treatments('etna', $etnalines);
		$this->treatments('belice', $bellines);
		$this->closePlans();
	}

	/*
	 * Scenario: olive mill, organic inspection
	 */

	/**
	 * Olives delivered to the mill (lot by lot) and pressed: oil in the warehouse of the mill, one lot of oil by farm and season
	 *
	 * @return void
	 */
	public function buildMill()
	{
		$db = $this->db;
		$this->out('Olive mill');
		$millin = (int) $this->value("SELECT rowid FROM ".$db->prefix()."entrepot WHERE ref = 'AZ-DEMO-FRANTOIO-IN'");
		$millprod = (int) $this->value("SELECT rowid FROM ".$db->prefix()."entrepot WHERE ref = 'AZ-DEMO-FRANTOIO-PROD'");
		if (!$millin || !$millprod) {
			$this->fail('olive mill', 'warehouses of the mill not found');
			return;
		}
		// Oil yield (kg of oil by kg of olives); density of the oil 0.916 kg/L
		$farms = array('etna' => array('OUT-OLIVE-ETNEA', 0.14, 'ETNA'), 'belice' => array('OUT-OLIVE-BELICE', 0.17, 'BELICE'));
		foreach ($farms as $farmkey => $f) {
			$farmprod = dolifarmGetFarmWarehouse($db, $this->ids[$farmkey], 'products');
			$productid = $this->ids[$f[0]];
			// Lots of olives of the harvests, with the date of the harvest
			$sql = "SELECT h.batch_number, h.date_harvest, h.qty_harvested FROM ".$db->prefix()."dolitrace_harvest as h";
			$sql .= " WHERE h.fk_product_out = ".((int) $productid)." AND h.status = 1 ORDER BY h.date_harvest";
			$resql = $db->query($sql);
			$lots = array();
			while ($resql && ($obj = $db->fetch_object($resql))) {
				$lots[] = $obj;
			}
			$oil = array();
			foreach ($lots as $lot) {
				$date = $db->jdate($lot->date_harvest);
				$label = 'Conferimento al frantoio '.$lot->batch_number;
				if ((int) $this->value("SELECT COUNT(*) FROM ".$db->prefix()."stock_mouvement WHERE label = '".$db->escape($label)."'") > 0) {
					continue;
				}
				$day = dol_time_plus_duree($date, 1, 'd') + 8 * 3600;
				$qty = (float) $lot->qty_harvested;
				$out = new MouvementStock($db);
				$in = new MouvementStock($db);
				if ($out->livraison($this->user, $productid, $farmprod, $qty, 0, $label, $day, '', '', $lot->batch_number) < 0
					|| $in->reception($this->user, $productid, $millin, $qty, 0, $label, '', '', $lot->batch_number, $day) < 0) {
					$this->fail($label, $out->error ? $out : $in);
					continue;
				}
				// Pressed the same day
				$press = new MouvementStock($db);
				if ($press->livraison($this->user, $productid, $millin, $qty, 0, 'Molitura '.$lot->batch_number, $day + 4 * 3600, '', '', $lot->batch_number) < 0) {
					$this->fail('Molitura '.$lot->batch_number, $press);
					continue;
				}
				$season = dol_print_date($date, '%Y');
				$key = $season.'-'.$f[2].(str_ends_with((string) $lot->batch_number, '-INCONV') ? '-INCONV' : '');
				$oil[$key] = ($oil[$key] ?? 0) + $qty * $f[1] / 0.916;
				$oil[$key.'#date'] = $day + 6 * 3600;
			}
			foreach ($oil as $key => $liters) {
				if (str_contains($key, '#')) {
					continue;
				}
				$lot = 'OLIO-'.$key;
				$mv = new MouvementStock($db);
				if ($mv->reception($this->user, $this->ids['OUT-OLIO-EVO'], $millprod, round($liters), 0, 'Produzione olio '.$lot, dol_time_plus_duree($oil[$key.'#date'], 18, 'm'), '', $lot, $oil[$key.'#date']) < 0) {
					$this->fail('oil '.$lot, $mv);
				} else {
					$this->out('  oil '.$lot.': '.round($liters).' L');
				}
			}
		}
	}

	/**
	 * Organic inspections of the Etna farm: the one of 2025 closed (data frozen up to its date), the one of 2026 to do
	 *
	 * @return void
	 */
	public function buildInspections()
	{
		global $langs;

		$db = $this->db;
		$this->out('Organic inspections');
		$error = '';
		$bodyid = dolifarmBioBodyThirdparty($db, $this->user, 'IT-BIO-006', $error);
		if ($bodyid <= 0) {
			$this->fail('control body', $error);
			return;
		}
		$list = array(
			array('ISP-ETNA-2025', '2025-09-18', 'ANNUAL', 1, 'COMPLIANT', 'DEMO-VERB-2025-0412', true),
			array('ISP-ETNA-2026', '2026-09-24', 'ANNUAL', 0, 'PENDING', '', false),
		);
		foreach ($list as $i) {
			if ($this->idByRef('dolitrace_bioinspection', $i[0]) > 0) {
				continue;
			}
			$insp = new DoliTraceBioInspection($db);
			$insp->ref = $i[0];
			$insp->fk_soc = $this->ids['etna'];
			$insp->fk_soc_body = $bodyid;
			$insp->date_inspection = $this->ts($i[1]);
			$insp->inspector = 'Ispettore demo';
			$insp->inspection_type = $i[2];
			$insp->samples = $i[3];
			$insp->outcome = $i[4];
			$insp->report_ref = $i[5];
			$insp->note_public = ($i[6] ? 'Visita annuale: verificati registri, magazzino dei mezzi tecnici, separazione dei prodotti in conversione. Prelevato un campione di foglie sull\'uliveto in conversione.' : '');
			if ($insp->create($this->user) <= 0) {
				$this->fail('inspection '.$i[0], $insp);
				continue;
			}
			if ($i[6]) {
				$insp->fetch($insp->id);
				if ($insp->close($this->user, $langs) < 0) {
					$this->fail('closing of the inspection '.$i[0], $insp);
				}
			}
			$this->out('  inspection '.$i[0]);
		}
	}

	/**
	 * Whole demo: instance, master data, seasons, olive mill, inspections, weather points of doliField
	 *
	 * @return int	Number of errors
	 */
	public function run()
	{
		$this->setupInstance();
		$this->buildMasterData();
		$this->buildSeasons();
		$this->buildMill();
		$this->buildInspections();
		// Weather points of the plots (doliField): the history is loaded by the scheduled job once the key of the Climate Data Store is in the setup
		if (isModEnabled('dolifield') && dol_include_once('/dolifield/class/dolifieldweather.class.php')) {
			$weather = new DoliFieldWeather($this->db);
			$nb = $weather->syncPlots();
			if ($nb < 0) {
				$this->fail('weather points', $weather->error);
			} else {
				$this->out('Weather: '.$nb.' plots linked to the points of ERA5-Land');
			}
		}
		$this->out($this->nberrors ? $this->nberrors.' error(s)' : 'Done');
		return $this->nberrors;
	}
}
