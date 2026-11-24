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
 * \file        class/dolifarmfascicoloxmlreader.class.php
 * \ingroup     dolifarm
 * \brief       Reader of the doliFarm XML format of the farm dossier (schema/fascicolo-1.0.xsd)
 */

/**
 * Read a farm dossier file in the doliFarm XML format and return it as a neutral array.
 *
 * The array is the input of DoliFarmImporter::import(): other readers (e.g. the PDF of the SIAN validation sheet)
 * must return the same structure.
 */
class DoliFarmFascicoloXmlReader
{
	/** Namespace of the format */
	const NS = 'urn:dolifarm:fascicolo:1';

	/** Max number of schema errors returned */
	const MAX_ERRORS = 20;

	/**
	 * @var string	Error message
	 */
	public $error = '';

	/**
	 * @var string[]	Detailed errors (schema validation)
	 */
	public $errors = array();

	/** @var array<int,string> Warnings on data of the file not read */
	public $warnings = array();

	/**
	 * Path of the XSD of the format
	 *
	 * @return string
	 */
	public static function schemaPath()
	{
		return dol_buildpath('/dolifarm/schema/fascicolo-1.0.xsd', 0);
	}

	/**
	 * Read and validate a file
	 *
	 * @param	string	$filepath	Path of the XML file
	 * @return	array<string,mixed>|null	Neutral structure of the dossier, null if KO (see error/errors)
	 */
	public function read($filepath)
	{
		global $langs;

		$langs->load('dolifarm@dolifarm');
		$this->error = '';
		$this->errors = array();

		$content = @file_get_contents($filepath);
		if ($content === false || $content === '') {
			$this->error = $langs->transnoentitiesnoconv('ErrorFileNotFound', basename($filepath));
			return null;
		}

		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();
		$dom = new DOMDocument();
		// LIBXML_NONET: never load external resources
		$loaded = $dom->loadXML($content, LIBXML_NONET);
		if (!$loaded) {
			$this->error = $langs->transnoentitiesnoconv('DossierImportXmlMalformed');
			$this->collectLibxmlErrors();
			libxml_use_internal_errors($previous);
			return null;
		}
		if ($dom->documentElement === null || $dom->documentElement->namespaceURI !== self::NS || $dom->documentElement->localName !== 'fascicolo') {
			$this->error = $langs->transnoentitiesnoconv('DossierImportXmlWrongFormat', self::NS);
			libxml_use_internal_errors($previous);
			return null;
		}
		if (!$dom->schemaValidate(self::schemaPath())) {
			$this->error = $langs->transnoentitiesnoconv('DossierImportXmlInvalid');
			$this->collectLibxmlErrors();
			libxml_use_internal_errors($previous);
			return null;
		}
		libxml_use_internal_errors($previous);

		$xml = simplexml_import_dom($dom);
		if ($xml === null) {
			$this->error = $langs->transnoentitiesnoconv('DossierImportXmlMalformed');
			return null;
		}
		$f = $xml->children(self::NS);

		return array(
			'format' => 'xml',
			'campagna' => (int) self::a($xml, 'campagna'),
			'scheda' => $this->readScheda($f->scheda),
			'anagrafica' => $this->readAnagrafica($f->anagrafica),
			'ote' => $this->readOte($f->ote),
			'particelle' => $this->readParticelle($f),
			'isole' => $this->readIsole($f->pianoColtivazione),
			'allevamenti' => $this->readAllevamenti($f->zootecnia),
			'fabbricati' => $this->readFabbricati($f->fabbricati),
			'macchine' => $this->readMacchine($f->mezziProduzione),
			'organismi' => $this->readOrganismi($f->legamiAssociativi),
			'documenti' => $this->readDocumenti($f->documenti),
			'conti' => $this->readConti($f->coordinateBancarie),
			'skipped' => array(),
		);
	}

	/**
	 * Copy the libxml errors into $this->errors
	 *
	 * @return void
	 */
	private function collectLibxmlErrors()
	{
		foreach (libxml_get_errors() as $e) {
			if (count($this->errors) >= self::MAX_ERRORS) {
				$this->errors[] = '...';
				break;
			}
			// Remove the namespace from the element names, to keep the messages readable
			$this->errors[] = 'Riga '.$e->line.': '.str_replace('{'.self::NS.'}', '', trim($e->message));
		}
		libxml_clear_errors();
	}

	/**
	 * Text of an element, '' if missing
	 *
	 * @param	SimpleXMLElement|null	$node	Element
	 * @return	string
	 */
	private static function t($node)
	{
		return ($node === null || !isset($node[0])) ? '' : trim((string) $node);
	}

	/**
	 * Attribute without namespace of an element (also when the element was read with children(NS))
	 *
	 * @param	SimpleXMLElement|null	$node	Element
	 * @param	string					$name	Attribute
	 * @return	string						Value, '' if missing
	 */
	private static function a($node, $name)
	{
		if ($node === null || !isset($node[0])) {
			return '';
		}
		$attrs = $node->attributes();
		return isset($attrs[$name]) ? trim((string) $attrs[$name]) : '';
	}

	/**
	 * Children of an element in the namespace of the format
	 *
	 * @param	SimpleXMLElement|null	$node	Element
	 * @return	SimpleXMLElement|null
	 */
	private static function c($node)
	{
		return ($node === null || !isset($node[0])) ? null : $node->children(self::NS);
	}

	/**
	 * Coded value: array(code, label)
	 *
	 * @param	SimpleXMLElement|null	$node	Element with attribute "codice"
	 * @return	array{code:string,label:string}
	 */
	private static function coded($node)
	{
		if ($node === null || !isset($node[0])) {
			return array('code' => '', 'label' => '');
		}
		return array('code' => trim((string) self::a($node, 'codice')), 'label' => trim((string) $node));
	}

	/**
	 * Comune: name, province and codes
	 *
	 * @param	SimpleXMLElement|null	$node	Element of type ComuneType
	 * @return	array<string,string>
	 */
	private static function comune($node)
	{
		return array(
			'name' => self::t($node),
			'province' => ($node !== null && isset($node[0])) ? trim((string) self::a($node, 'provincia')) : '',
			'belfiore' => ($node !== null && isset($node[0])) ? trim((string) self::a($node, 'codiceCatastale')) : '',
			'istat' => ($node !== null && isset($node[0])) ? trim((string) self::a($node, 'istat')) : '',
		);
	}

	/**
	 * @param	SimpleXMLElement	$node	Element scheda
	 * @return	array<string,mixed>
	 */
	private function readScheda($node)
	{
		$s = self::c($node);
		$m = self::c($s->mandato);
		return array(
			'number' => self::t($s->numero),
			'protocol' => self::t($s->protocollo),
			'act_type' => self::t($s->tipoAtto),
			'paying_agency' => self::t($s->organismoPagatore),
			'date_signature' => self::t($s->dataSottoscrizione),
			'date_reference' => self::t($s->dataRiferimentoConsistenza),
			'date_print' => self::t($s->dataStampa),
			'caa' => $m === null ? '' : self::t($m->caa),
			'caa_office' => $m === null ? '' : self::t($m->ufficio),
			'caa_operator' => $m === null ? '' : self::t($m->operatore),
			'date_mandate' => $m === null ? '' : self::t($m->dataSottoscrizione),
		);
	}

	/**
	 * @param	SimpleXMLElement	$node	Element anagrafica
	 * @return	array<string,mixed>
	 */
	private function readAnagrafica($node)
	{
		$a = self::c($node);
		$vat = array();
		foreach ($a->partitaIva as $p) {
			$vat[] = self::t($p);
		}
		$representatives = array();
		foreach ($a->rappresentanteLegale as $r) {
			$rc = self::c($r);
			$representatives[] = array(
				'cf' => self::t($rc->codiceFiscale),
				'lastname' => self::t($rc->cognome),
				'firstname' => self::t($rc->nome),
				'birthday' => self::t($rc->dataNascita),
				'birthplace' => self::comune($rc->comuneNascita),
				'role' => self::t($rc->carica),
			);
		}
		$rec = self::c($a->recapito);
		$ri = self::c($a->registroImprese);
		$activity = $a->attivitaAgricola;
		return array(
			'cuaa' => self::t($a->cuaa),
			'vat' => $vat,
			'name' => self::t($a->denominazione),
			'legal_form' => self::t($a->formaGiuridica),
			'ateco' => isset($a->ateco[0]) ? trim((string) self::a($a->ateco[0], 'codice')) : '',
			'representatives' => $representatives,
			'address' => $rec === null ? '' : self::t($rec->indirizzo),
			'zip' => $rec === null ? '' : self::t($rec->cap),
			'town' => $rec === null ? array('name' => '', 'province' => '', 'belfiore' => '', 'istat' => '') : self::comune($rec->comune),
			'pec' => $rec === null ? '' : self::t($rec->pec),
			'email' => $rec === null ? '' : self::t($rec->email),
			'phone' => $rec === null ? '' : self::t($rec->telefono),
			'rea' => $ri === null ? '' : self::t($ri->rea),
			'date_registry' => $ri === null ? '' : self::t($ri->dataIscrizione),
			'date_activity' => isset($activity[0]) ? trim((string) self::a($activity, 'dataInizio')) : '',
		);
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element ote
	 * @return	array<string,mixed>
	 */
	private function readOte($node)
	{
		$o = self::c($node);
		if ($o === null) {
			return array('code' => '', 'label' => '', 'economic_size' => null);
		}
		$size = self::t($o->dimensioneEconomica);
		return array('code' => self::t($o->codice), 'label' => self::t($o->descrizione), 'economic_size' => $size === '' ? null : (float) $size);
	}

	/**
	 * Cadastral parcels, with their constraints and their share of the cultivation plan
	 *
	 * @param	SimpleXMLElement	$f	Children of the root
	 * @return	array<string,array<string,mixed>>	Parcels by id
	 */
	private function readParticelle($f)
	{
		$parcels = array();
		foreach (self::c($f->consistenzaTerreni)->particella as $p) {
			$pc = self::c($p);
			$cond = self::c($pc->conduzione);
			$owners = array();
			foreach ($pc->proprietario as $o) {
				$oc = self::c($o);
				$owners[] = array('cf' => self::t($oc->codiceFiscale), 'name' => self::t($oc->denominazione));
			}
			$id = trim((string) self::a($p, 'id'));
			$parcels[$id] = array(
				'id' => $id,
				'comune' => self::comune($pc->comune),
				'section' => self::t($pc->sezione),
				'sheet' => self::t($pc->foglio),
				'parcel' => self::t($pc->particella),
				'sub' => self::t($pc->subalterno),
				'conduction' => array(
					'code' => (int) self::a($pc->conduzione, 'codice'),
					'subcode' => (int) self::a($pc->conduzione, 'sottocodice'),
					'label' => self::t($cond->descrizione),
					'protocol' => self::t($cond->protocollo),
					'date_start' => self::t($cond->dataInizio),
					'date_end' => self::t($cond->dataFine),
				),
				'owners' => $owners,
				'partial' => self::t($pc->conduzioneParziale) === 'SI',
				'size_cadastral' => (float) self::t($pc->superficieCatastale),
				'size_graphic' => self::t($pc->superficieGrafica) === '' ? null : (float) self::t($pc->superficieGrafica),
				'size_conducted' => (float) self::t($pc->superficieCondotta),
				'constraints' => array(),
				'cultivations' => array(),
			);
		}

		$vincoli = self::c($f->vincoliTerreni);
		if ($vincoli !== null) {
			foreach ($vincoli->vincolo as $v) {
				$ref = trim((string) self::a($v, 'particellaRef'));
				$vc = self::c($v);
				$parcels[$ref]['constraints'][] = array(
					'sic' => self::t($vc->sic),
					'zps' => self::t($vc->zps),
					'zvn' => self::t($vc->zvn) === 'SI',
					'zone_psr' => self::t($vc->zonaPsr),
					'disadvantaged' => self::t($vc->zonaSvantaggiata),
				);
			}
		}

		$plan = self::c($f->pianoColtivazione);
		if ($plan !== null && isset($plan->particelle[0])) {
			foreach (self::c($plan->particelle)->particella as $p) {
				$ref = trim((string) self::a($p, 'particellaRef'));
				foreach (self::c($p)->coltivazione as $col) {
					$parcels[$ref]['cultivations'][] = $this->readColtivazione($col);
				}
			}
		}
		return $parcels;
	}

	/**
	 * @param	SimpleXMLElement	$node	Element coltivazione
	 * @return	array<string,mixed>
	 */
	private function readColtivazione($node)
	{
		$c = self::c($node);
		return array(
			'land_use' => self::coded($c->occupazione),
			'destination' => self::coded($c->destinazione),
			'use' => self::coded($c->uso),
			'quality' => self::coded($c->qualita),
			'variety' => self::coded($c->varieta),
			'size' => (float) self::t($c->superficie),
			'date_start' => self::t($c->dataInizio),
			'date_end' => self::t($c->dataFine),
			'organic' => self::t($c->metodoProduzione),
			'maintenance' => self::t($c->criterioMantenimento),
		);
	}

	/**
	 * Graphic cultivation plan: islands, their fields ("appezzamenti" of the SIAN) and the cultivations of each field
	 *
	 * @param	SimpleXMLElement|null	$node	Element pianoColtivazione
	 * @return	array{date_start:string,date_end:string,islands:array<int,array<string,mixed>>}
	 */
	private function readIsole($node)
	{
		$res = array('date_start' => '', 'date_end' => '', 'islands' => array());
		$plan = self::c($node);
		if ($plan === null) {
			return $res;
		}
		$res['date_start'] = trim((string) self::a($node, 'dataInizio'));
		$res['date_end'] = trim((string) self::a($node, 'dataFine'));
		if (!isset($plan->isole[0])) {
			return $res;
		}
		foreach (self::c($plan->isole)->isola as $isola) {
			$island = array('id' => trim((string) self::a($isola, 'id')), 'fields' => array());
			foreach (self::c($isola)->appezzamento as $app) {
				$field = array('id' => trim((string) self::a($app, 'id')), 'cultivations' => array());
				foreach (self::c($app)->coltivazione as $col) {
					$field['cultivations'][] = $this->readColtivazione($col);
				}
				$island['fields'][] = $field;
			}
			$res['islands'][] = $island;
		}
		return $res;
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element zootecnia
	 * @return	array<int,array<string,mixed>>
	 */
	private function readAllevamenti($node)
	{
		$res = array();
		$z = self::c($node);
		if ($z === null) {
			return $res;
		}
		foreach ($z->allevamento as $a) {
			$ac = self::c($a);
			$heads = null;
			foreach ($ac->capi as $capi) {
				$heads = (int) $heads + (int) $capi;
			}
			$owner = self::c($ac->proprietario);
			$res[] = array(
				'bdn_code' => self::t($ac->codiceAsl),
				'address' => self::t($ac->indirizzo),
				'town' => self::comune($ac->comune),
				'species' => self::t($ac->specie),
				'breeding_type' => self::t($ac->tipologia),
				'breeding_mode' => self::t($ac->modalita),
				'production_type' => self::t($ac->tipoProduzione),
				'milk' => self::t($ac->autoconsumoLatte),
				'date_start' => self::t($ac->dataInizio),
				'date_end' => self::t($ac->dataFine),
				'holder' => self::t($ac->conduzione),
				'owner' => $owner === null ? '' : self::t($owner->denominazione),
				'date_bdn' => self::t($ac->dataAggiornamentoBdn),
				'organic' => self::t($ac->metodoProduzione) !== '',
				'heads' => $heads,
			);
		}
		return $res;
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element fabbricati
	 * @return	array<int,array<string,mixed>>
	 */
	private function readFabbricati($node)
	{
		$res = array();
		$b = self::c($node);
		if ($b === null) {
			return $res;
		}
		foreach ($b->fabbricato as $fab) {
			$fc = self::c($fab);
			$num = function ($n) {
				$v = self::t($n);
				return $v === '' ? null : (float) $v;
			};
			$res[] = array(
				'comune' => self::comune($fc->comune),
				'section' => self::t($fc->sezione),
				'sheet' => self::t($fc->foglio),
				'parcel' => self::t($fc->particella),
				'sub' => self::t($fc->subalterno),
				'use' => self::t($fc->tipologia),
				'conduction' => self::t($fc->conduzione),
				'date_start' => self::t($fc->dataInizio),
				'date_end' => self::t($fc->dataFine),
				'surface' => $num($fc->superficieMq),
				'surface_covered' => $num($fc->superficieCopertaMq),
				'surface_uncovered' => $num($fc->superficieScopertaMq),
				'volume' => $num($fc->volumeMc),
				'seats' => self::t($fc->posti) === '' ? null : (int) self::t($fc->posti),
			);
		}
		return $res;
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element mezziProduzione
	 * @return	array<int,array<string,mixed>>
	 */
	private function readMacchine($node)
	{
		$res = array();
		$m = self::c($node);
		if ($m === null) {
			return $res;
		}
		foreach ($m->macchina as $mac) {
			$mc = self::c($mac);
			$res[] = array(
				'type' => self::t($mc->tipo),
				'brand' => self::t($mc->marca),
				'model' => self::t($mc->modello),
				'plate' => self::t($mc->targa),
				'chassis' => self::t($mc->telaio),
				'year' => self::t($mc->annoCostruzione) === '' ? null : (int) self::t($mc->annoCostruzione),
				'power_kw' => self::t($mc->potenzaKw) === '' ? null : (float) self::t($mc->potenzaKw),
				'fuel' => self::t($mc->alimentazione),
			);
		}
		return $res;
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element legamiAssociativi
	 * @return	array<int,array<string,string>>
	 */
	private function readOrganismi($node)
	{
		$res = array();
		$l = self::c($node);
		if ($l === null) {
			return $res;
		}
		foreach ($l->organismo as $o) {
			$oc = self::c($o);
			$res[] = array(
				'cf' => self::t($oc->codiceFiscale),
				'name' => self::t($oc->denominazione),
				'type' => self::t($oc->tipologia),
				'date_start' => self::t($oc->dataInizio),
			);
		}
		return $res;
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element documenti
	 * @return	array<int,array<string,string>>
	 */
	private function readDocumenti($node)
	{
		$res = array();
		$d = self::c($node);
		if ($d === null) {
			return $res;
		}
		foreach ($d->documento as $doc) {
			$dc = self::c($doc);
			$res[] = array(
				'section' => self::t($dc->sezione),
				'type' => self::t($dc->tipologia),
				'protocol' => self::t($dc->protocollo),
				'date_signature' => self::t($dc->dataSottoscrizione),
				'date_start' => self::t($dc->dataDecorrenza),
				'date_end' => self::t($dc->dataScadenza),
			);
		}
		return $res;
	}

	/**
	 * @param	SimpleXMLElement|null	$node	Element coordinateBancarie
	 * @return	array<int,array<string,string>>
	 */
	private function readConti($node)
	{
		$res = array();
		$b = self::c($node);
		if ($b === null) {
			return $res;
		}
		foreach ($b->conto as $c) {
			$cc = self::c($c);
			$res[] = array(
				'iban' => self::t($cc->iban),
				'bank' => self::t($cc->istituto),
				'agency' => self::t($cc->agenzia),
			);
		}
		return $res;
	}
}
