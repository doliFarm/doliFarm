<?php
/* Copyright (C) 2026       Luigi Grillo - doliFarm.com	<info@dolifarm.com> */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// INCLUDE SINGOLARI (Assicurarsi che i percorsi siano corretti nel sistema finale)
dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
dol_include_once('/dolifarm/class/dolifarmplot.class.php');
dol_include_once('/dolifarm/class/dolifarmmachine.class.php');
dol_include_once('/dolifarm/lib/dolifarm.lib.php');

class DoliFarmImporter
{
    private $db;
    private $user;
    public $error;
    public $errors = array();
    public $output_log = array();
    public $picto;

    public function __construct($db, $user)
    {
        $this->db = $db;
        $this->user = $user;

        dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');
		$this->picto = dolifarm_get_fa_icon('importer');
    }

    public function importFromXML($filepath)
    {
        global $conf, $langs;

        if (!file_exists($filepath)) {
            $this->error = "File non trovato: " . $filepath;
            dol_syslog("DoliFarmImporter::importFromXML Error: " . $this->error, LOG_ERR);
            return -1;
        }

        $xml = simplexml_load_file($filepath);
        if ($xml === false) {
            $this->error = "XML non valido o malformato";
            dol_syslog("DoliFarmImporter::importFromXML Error: " . $this->error, LOG_ERR);
            return -1;
        }

        $this->db->begin();

        try {
            // 1. Azienda (Soggetto)
            $socId = $this->findOrCreateThirdparty($xml->Soggetto);
            if ($socId < 0) throw new Exception("Errore gestione Azienda (CUAA: " . self::normalizeCuaa((string) $xml->Soggetto->CUAA) . ", P.IVA: " . (string) $xml->Soggetto->PartitaIVA . "): " . $this->error);
            $this->log("Azienda ID processata: $socId");

            // 2. Fascicolo (Dossier)
            $dossierId = $this->processDossier($xml->DatiFascicolo, $socId);
            if ($dossierId < 0) throw new Exception("Errore gestione Fascicolo: " . $this->error);
            $this->log("Fascicolo ID processato: $dossierId");

            // 3. Terreni (Plots)
            if (isset($xml->Terreni->Appezzamento)) {
                foreach ($xml->Terreni->Appezzamento as $plotNode) {
                    $res = $this->processPlot($plotNode, $dossierId, $socId);
                    if ($res < 0) {
                        // Non blocchiamo tutto per un singolo terreno errato, ma logghiamo l'errore
                        $msg = "Errore Import Terreno " . $plotNode->CodiceInterno . ": " . $this->error;
                        $this->log($msg);
                        dol_syslog("DoliFarmImporter: " . $msg, LOG_WARNING);
                        // throw new Exception($msg); // Decommentare per strict mode
                    }
                }
            }

            // 4. Macchine (Machines)
            if (isset($xml->Macchine->Macchina)) {
                foreach ($xml->Macchine->Macchina as $machNode) {
                    $res = $this->processMachine($machNode, $dossierId, $socId);
                    if ($res < 0) {
                         $msg = "Errore Import Macchina " . $machNode->Targa . ": " . $this->error;
                         $this->log($msg);
                         dol_syslog("DoliFarmImporter: " . $msg, LOG_WARNING);
                    }
                }
            }

            $this->db->commit();
            return $dossierId;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->error = $e->getMessage();
            dol_syslog("DoliFarmImporter::importFromXML Exception: " . $this->error, LOG_ERR);
            return -1;
        }
    }

    // --- HELPER METHODS ---

    /**
     * Find the farm by CUAA (stored in idprof4, "Cod. Fisc." for Italy), then by VAT number; create it if not found
     *
     * @param   SimpleXMLElement    $node   Node Soggetto: CUAA (or CodiceFiscale), PartitaIVA, RagioneSociale, optional Nazione (ISO code)
     * @return  int                         Id of the third party, <0 if KO
     */
    private function findOrCreateThirdparty($node)
    {
        $cuaa = self::normalizeCuaa((string) $node->CUAA);
        if ($cuaa === '') {
            $cuaa = self::normalizeCuaa((string) $node->CodiceFiscale);
        }
        $vat = strtoupper(preg_replace('/\s+/', '', (string) $node->PartitaIVA));
        $name = trim((string) $node->RagioneSociale);

        if ($cuaa === '' && $vat === '') {
            $this->error = "CUAA e partita IVA mancanti nel nodo Soggetto";
            return -1;
        }
        // CUAA: tax code of a person (16 characters) or of a company (11 digits)
        if ($cuaa !== '' && !preg_match('/^([A-Z0-9]{16}|[0-9]{11})$/', $cuaa)) {
            $this->error = "CUAA non valido: ".$cuaa;
            return -1;
        }

        $socid = 0;
        if ($cuaa !== '') {
            $socid = $this->findThirdpartyBy("s.idprof4 = '".$this->db->escape($cuaa)."'");
        }
        if (!$socid && $vat !== '') {
            // VAT stored with or without the country prefix
            $vatnum = preg_replace('/^[A-Z]{2}/', '', $vat);
            $socid = $this->findThirdpartyBy("s.tva_intra IN ('".$this->db->escape($vatnum)."', '".$this->db->escape('IT'.$vatnum)."')");
        }
        if ($socid < 0) {
            return -1;
        }

        if ($socid > 0) {
            // Found by VAT number: complete the CUAA if it was missing
            $soc = new Societe($this->db);
            if ($cuaa !== '' && $soc->fetch($socid) > 0 && empty($soc->idprof4)) {
                $soc->idprof4 = $cuaa;
                if ($soc->update($socid, $this->user) < 0) {
                    $this->error = $soc->error." ".implode(',', $soc->errors);
                    return -1;
                }
                $this->log("CUAA ".$cuaa." aggiunto al soggetto terzo ".$soc->name);
            }
            return $socid;
        }

        if ($name === '') {
            $this->error = "Ragione sociale mancante per il nuovo soggetto terzo";
            return -1;
        }

        $countrycode = strtoupper(trim((string) $node->Nazione));
        if (!preg_match('/^[A-Z]{2}$/', $countrycode)) {
            $countrycode = 'IT';
        }

        $newSoc = new Societe($this->db);
        $newSoc->name = $name;
        $newSoc->idprof4 = $cuaa;
        $newSoc->tva_intra = $vat;
        $newSoc->client = 1; // Customer
        $newSoc->code_client = -1; // Auto generate code
        $newSoc->country_id = (int) dol_getIdFromCode($this->db, $countrycode, 'c_country', 'code', 'rowid');
        $typentid = dolifarmGetTypentId($this->db, 'TE_FARM');
        if ($typentid > 0) {
            $newSoc->typent_id = $typentid;
        }

        $id = $newSoc->create($this->user);
        if ($id < 0) {
            $this->error = $newSoc->error . " " . implode(',', $newSoc->errors);
            return -1;
        }
        $this->log("Creato il soggetto terzo ".$name.($cuaa !== '' ? " (CUAA ".$cuaa.")" : ""));
        return $id;
    }

    /**
     * Id of the third party of the current entities matching a condition
     *
     * @param   string  $where  SQL condition on the table societe (alias s), already escaped
     * @return  int             Id, 0 if not found, <0 if KO or more than one match
     */
    private function findThirdpartyBy($where)
    {
        $sql = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe as s";
        $sql .= " WHERE ".$where." AND s.entity IN (".getEntity('societe').")";
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        $num = $this->db->num_rows($resql);
        if ($num > 1) {
            $this->error = "Più soggetti terzi corrispondono allo stesso CUAA o partita IVA: correggere l'anagrafica prima di importare";
            return -1;
        }
        if ($num == 1) {
            $obj = $this->db->fetch_object($resql);
            return (int) $obj->rowid;
        }
        return 0;
    }

    /**
     * CUAA without spaces, in capitals
     *
     * @param   string  $value  Value read from the file
     * @return  string
     */
    public static function normalizeCuaa($value)
    {
        return strtoupper(preg_replace('/\s+/', '', $value));
    }

    private function processDossier($node, $socId)
    {
        $dossier = new DoliFarmFarmDossier($this->db);
        $ref = trim((string)$node->Identificativo);
        $year = (int)$node->AnnoCampagna;

        if (empty($ref)) {
            $ref = 'DOSS-' . $year . '-' . $socId; // Fallback ref generation
        }

        // Fetch by Ref (using 0 as ID to force ref search)
        // Nota: Il metodo fetch standard di Dolibarr spesso richiede ID, usiamo fetchCommon o query diretta se necessario
        $res = $dossier->fetch(0, $ref); 

        if ($res > 0) {
            // Update existing
            $dossier->year_validity = $year;
            if ($year > 0) {
                $dossier->date_validity_end = dol_mktime(12, 0, 0, 12, 31, $year);
            }
            $dossier->fk_soc = $socId; // Ensure link is correct
            if ($dossier->update($this->user) < 0) { 
                $this->error = $dossier->error; 
                return -1; 
            }
            return $dossier->id;
        }

        // Create new
        $dossier->ref = $ref;
        $dossier->fk_soc = $socId;
        $dossier->year_validity = $year;
        $dossier->status = 0; // DRAFT
        $dossier->date_import = dol_now();
        // Signature date of the validation sheet (required): optional node DataValidazione (YYYY-MM-DD)
        $dateSignature = trim((string) $node->DataValidazione);
        if ($dateSignature !== '' && strtotime($dateSignature) !== false) {
            $dossier->date_signature = dol_stringtotime($dateSignature);
        } else {
            $dossier->date_signature = $dossier->date_import;
            $this->log("Data di validazione assente nel file: usata la data di importazione");
        }
        if ($year > 0) {
            $dossier->date_validity_end = dol_mktime(12, 0, 0, 12, 31, $year);
        }
        
        $id = $dossier->create($this->user);
        if ($id < 0) {
            $this->error = $dossier->error . " " . implode(',', $dossier->errors);
            return -1;
        }
        return $id;
    }

    private function processPlot($node, $dossierId, $socId)
    {
        $plot = new DoliFarmPlot($this->db);
        
        // Costruzione REF univoco robusto
        $codice_interno = trim((string)$node->CodiceInterno);
        if (empty($codice_interno)) $codice_interno = uniqid(); 
        
        $unique_ref = "PL-" . $dossierId . "-" . $codice_interno;

        // Controllo esistenza SQL diretto (usando il nome tabella corretto del nuovo schema)
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "dolifarm_plot WHERE ref = '" . $this->db->escape($unique_ref) . "'";
        $sql .= " AND entity IN (" . getEntity($plot->element) . ")";
        $res = $this->db->query($sql);
        
        if ($this->db->num_rows($res) > 0) {
            $obj = $this->db->fetch_object($res);
            // Opzionale: Update logic here if data changed
            return $obj->rowid; 
        }

        $plot->ref = $unique_ref;
        $plot->label = (string)$node->Descrizione;
        if (empty($plot->label)) $plot->label = "Appezzamento " . $codice_interno;
        
        $plot->fk_soc = $socId;
        $plot->fk_dossier = $dossierId;
        $plot->status = 1;
        $plot->size_sau = (float)$node->SuperficieSAU;
        $plot->fk_owner = $socId; // Default owner is the company
        
        // Mapping ownership semplice basato sul dizionario
        $tipo = strtoupper(trim((string)$node->TitoloPossesso));
        if ($tipo == 'AFFITTO') $plot->fk_ownership = 'RENTED';
        elseif ($tipo == 'PROPRIETA' || $tipo == 'PROPRIETÀ') $plot->fk_ownership = 'OWNED';
        elseif ($tipo == 'COMODATO') $plot->fk_ownership = 'COMODATO';
        else $plot->fk_ownership = 'OWNED'; // Default fallback

        // Dati Catastali
        $plot->cadastral_comune = (string)$node->ComuneCatastale;
        $plot->cadastral_sheet = (string)$node->Foglio;
        $plot->cadastral_parcel = (string)$node->Particella;
        $plot->cadastral_sub = (string)$node->Subalterno;

        $id = $plot->create($this->user);
        if ($id < 0) {
            $this->error = "Plot Create Error ($unique_ref): " . $plot->error . " " . implode(',', $plot->errors);
            return -1;
        }
        return $id;
    }

    private function processMachine($node, $dossierId, $socId)
    {
        $mach = new DoliFarmMachine($this->db);
        $ref = trim((string)$node->Targa);
        
        // Se non c'è targa, usiamo il numero di telaio o un ID univoco
        if (empty($ref)) {
             $ref = trim((string)$node->Telaio);
             if (empty($ref)) $ref = "MACH-" . uniqid();
        }

        // Controllo SQL diretto (usando il nome tabella corretto del nuovo schema)
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "dolifarm_machine WHERE ref = '" . $this->db->escape($ref) . "'";
        $sql .= " AND entity IN (" . getEntity($mach->element) . ")";
        $res = $this->db->query($sql);
        
        if ($this->db->num_rows($res) > 0) {
            // Esiste già! Recuperiamo ID e aggiorniamo il link dossier
            $obj = $this->db->fetch_object($res);
            $mach->fetch($obj->rowid);
            
            $mach->fk_dossier = $dossierId; // Aggiorna contesto
            if ($mach->update($this->user) < 0) {
                $this->error = $mach->error;
                return -1;
            }
            return $mach->id;
        }

        // Creazione Nuova Macchina
        $mach->ref = $ref;
        $mach->label = (string)$node->Descrizione;
        if (empty($mach->label)) $mach->label = "Macchina " . $ref;

        $mach->fk_soc = $socId;
        $mach->fk_dossier = $dossierId;
        $mach->status = 1;
        
        $mach->power_kw = (float)$node->PotenzaKW;
        
        // Mapping Fuel Type
        $alim = strtoupper(trim((string)$node->Alimentazione));
        if (strpos($alim, 'GASOLIO') !== false) $mach->fuel_type = 'DIESEL';
        elseif (strpos($alim, 'BENZINA') !== false) $mach->fuel_type = 'GASOLINE';
        elseif (strpos($alim, 'ELETTR') !== false) $mach->fuel_type = 'ELECTRIC';
        else $mach->fuel_type = 'DIESEL'; // Default agricolo
        
        // Mapping Machine Type
        $tipo = strtoupper(trim((string)$node->Tipo));
        if (strpos($tipo, 'TRATTORE') !== false || strpos($tipo, 'TRATTRICE') !== false) {
             $mach->fk_type = 'TRACTOR_WHEEL'; 
             // Raffinamento Cingolato
             if (strpos(strtoupper($mach->label), 'CINGOL') !== false) $mach->fk_type = 'TRACTOR_TRACK';
        } elseif (strpos($tipo, 'RIMORCHIO') !== false) {
             $mach->fk_type = 'TRAILER';
        } else {
             $mach->fk_type = 'TOOL_PLOW'; // Generico attrezzo, TODO: Logica più complessa per i vari tool
        }

        $id = $mach->create($this->user);
        
        if ($id < 0) {
            $this->error = "Machine Create Error ($ref): " . $mach->error . " " . implode(',', $mach->errors);
            return -1;
        }
        return $id;
    }

    private function log($msg) {
        $this->output_log[] = date('Y-m-d H:i:s') . " - " . $msg;
        // Dolibarr system log for persistent debugging
        dol_syslog("DoliFarmImporter: " . $msg, LOG_INFO);
    }
}
?>