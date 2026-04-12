<?php
/* Copyright (C) 2026       SuperAdmin */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// INCLUDE SINGOLARI (Assicurarsi che i percorsi siano corretti nel sistema finale)
dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
dol_include_once('/dolifarm/class/dolifarmplot.class.php');
dol_include_once('/dolifarm/class/dolifarmmachine.class.php');

class DoliFarmImporter
{
    private $db;
    private $user;
    public $error;
    public $errors = array();
    public $output_log = array();

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
            if ($socId < 0) throw new Exception("Errore gestione Azienda (VAT: " . (string)$xml->Soggetto->PartitaIVA . "): " . $this->error);
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

    private function findOrCreateThirdparty($node)
    {
        $vat = $this->db->escape(trim((string)$node->PartitaIVA));
        $name = trim((string)$node->RagioneSociale);

        if (empty($vat)) {
             $this->error = "Partita IVA mancante nel nodo XML";
             return -1;
        }

        // Cerca per Partita IVA
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "societe WHERE tva_intra = '" . $vat . "'";
        $resql = $this->db->query($sql);
        if ($resql && $obj = $this->db->fetch_object($resql)) {
            return $obj->rowid;
        }

        // Se non trova, crea
        $newSoc = new Societe($this->db);
        $newSoc->name = $name;
        $newSoc->tva_intra = $vat;
        $newSoc->client = 1; // Prospect/Customer
        $newSoc->code_client = -1; // Auto generate code
        $newSoc->country_id = 10; // Default Italy (ID 10) - TO IMPROVE: Logic based on country code in XML
        
        // Imposta tipo azienda agricola se presente nel dizionario (opzionale)
        // $newSoc->typent_id = ...

        $id = $newSoc->create($this->user);
        if ($id < 0) {
            $this->error = $newSoc->error . " " . implode(',', $newSoc->errors);
            return -1;
        }
        return $id;
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