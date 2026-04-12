<?php
/* Copyright (C) 2024 DoliFarm Team */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

class dolifarmWidgetTopPlannedProducts extends ModeleBoxes
{
    public $boxcode = "dolifarmboxTopPlannedProducts";
    public $boximg = "product";
    public $boxlabel = 'DolifarmBoxTopPlannedProducts';
    public $lang = 'dolifarm@dolifarm';
    public $depends = array('dolifarm', 'dolitrace', 'product');
    public $widgettype = '';

    public function __construct(DoliDB $db, $param = '')
    {
        global $user;
        parent::__construct($db, $param);
        $this->param = $param;
        $this->enabled = 1;
    }

    public function loadBox($max = 10)
    {
        global $user, $langs, $conf;
        $this->max = $max;

        // --- CARICAMENTO CLASSI ---
        // Usiamo DoliTraceCropplan perché stiamo elencando i Piani, non i Prodotti aggregati
        dol_include_once("/dolitrace/class/dolitracecropplan.class.php");
        $cropplanstatic = new DoliTraceCropplan($this->db);
        
        // Opzionale: se vuoi il link anche sul nome del crop, puoi istanziare Product
        // dol_include_once("/product/class/product.class.php");
        // $productstatic = new Product($this->db);

        $this->info_box_contents = array();

        // HEADER
        $title_text = $langs->trans("TopPlannedProducts");
        if ($title_text == "TopPlannedProducts") $title_text = "Top Produzione Prevista";

        $this->info_box_head = array(
            'text' => $title_text,
            'limit' => 0,
            'graph' => 0,
            'sublink' => DOL_URL_ROOT . '/dolitrace/dolitracecropplan_list.php',
            'subtext' => $langs->trans("ShowList"),
            'subpicto' => 'object_product', 
        );

        // CONFIGURAZIONE TABELLE
        $table_cropplan = MAIN_DB_PREFIX . "dolitrace_cropplan";
        
        // Verifica se usare la tabella standard prodotti o custom
        // Di solito i prodotti sono in llx_product. Se usi dolifarm_crops assicurati che p.rowid corrisponda a fk_output_product
        $table_product = MAIN_DB_PREFIX . "product"; 
        // Se sei sicuro che sia dolifarm_crops scommenta la riga sotto:
        // $table_product = MAIN_DB_PREFIX . "dolifarm_crops";

        // Verifica preliminare
        $res = $this->db->DDLDescTable($table_cropplan);
        if (!$res) {
            $this->info_box_contents[][0] = array('td' => 'class="center"', 'text' => "Tabella $table_cropplan mancante");
            return;
        }

        $field_qty = 'estimated_yield'; 
        
        // QUERY
        // Selezioniamo i dati del Piano (cp) e il nome del Prodotto (p.label)
        $sql = "SELECT cp.rowid, cp.ref, cp.label, cp.batch_output, cp.fk_output_product, p.label as crop_label, cp.".$field_qty." as total_qty";
        $sql .= " FROM " . $table_cropplan . " as cp";
        $sql .= " LEFT JOIN " . $table_product . " as p ON cp.fk_output_product = p.rowid";
        $sql .= " WHERE cp.entity = " . $conf->entity;
        $sql .= " AND cp.status = 1"; 
        $sql .= " AND cp.fk_output_product > 0";
        // Ordiniamo per quantità decrescente
        $sql .= " ORDER BY total_qty DESC";
        $sql .= " LIMIT " . $max;

        $resql = $this->db->query($sql);
        
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                
                // Popoliamo l'oggetto PIANO per il link corretto
                $cropplanstatic->id = $obj->rowid;
                $cropplanstatic->ref = $obj->ref;
                $cropplanstatic->label = $obj->label;
                $cropplanstatic->statut = 1; // Forziamo attivo per visualizzazione

                // COL 1: Riferimento Piano (Link)
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $cropplanstatic->getNomUrl(1),
                    'asis' => 1,
                );

                // COL 2: Label del Piano
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => dol_trunc($obj->label, 20),
                );

                // COL 3: [NUOVA] Nome Coltura (Crop)
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $obj->crop_label ? $langs->trans($obj->crop_label) : '<span class="opacitymedium">'.$langs->trans("Unknown").'</span>',
                );

                // COL 4: Quantità (Badge)
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="right"',
                    'text' => '<span class="badge badge-status4 badge-pill">' . price($obj->total_qty, 0, '', 0, 0) . '</span>',
                    'asis' => 1,
                );
                $i++;
            }
            if ($num == 0) {
                 $this->info_box_contents[$i][] = array(
                    'td' => 'class="center opacitymedium"',
                    'text' => $langs->trans("NoData"),
                );
            }
        } else {
            $this->info_box_contents[][0] = array(
                'td' => 'class="error"',
                'text' => "SQL Error: " . $this->db->lasterror()
            );
        }
    }

    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
?>