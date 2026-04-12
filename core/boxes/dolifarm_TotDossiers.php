<?php
/* Copyright (C) 2004-2017  Laurent Destailleur         <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2024  Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024       DoliFarm Team
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/dolifarm/core/boxes/dolifarmwidgettotdossiers.php
 * \ingroup dolifarm
 * \brief   Widget to monitor active Farm Dossiers (Fascicoli Aziendali)
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Class to manage the box
 */
class dolifarmWidgetTotDossiers extends ModeleBoxes
{
    /**
     * @var string Alphanumeric ID. Populated by the constructor.
     */
    public $boxcode = "dolifarmbox_totdossiers";

    /**
     * @var string Box icon (in configuration page)
     */
    public $boximg = "dolifarm@dolifarm";

    /**
     * @var string Box label (in configuration page)
     */
    public $boxlabel = 'DolifarmBoxTotDossiers';

    /**
     * @var string Box language file if it needs a specific language file.
     */
    public $lang = 'dolifarm@dolifarm';

    /**
     * @var string[] Module dependencies
     */
    public $depends = array('dolifarm');

    /**
     * @var string  Widget type ('graph' means the widget is a graph widget)
     */
    public $widgettype = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     * @param string $param More parameters
     */
    public function __construct(DoliDB $db, $param = '')
    {
        global $user;

        parent::__construct($db, $param);

        $this->param = $param;
        $this->enabled = 1;
    }

    /**
     * Load data into info_box_contents array to show array later. Called by Dolibarr before displaying the box.
     *
     * @param   int<0,max>  $max    Maximum number of records to load
     * @return  void
     */
    public function loadBox($max = 5)
    {
        global $user, $langs, $conf;

        $this->max = $max;

        // --- CARICAMENTO CLASSE FARM DOSSIER ---
        // Assumiamo che il file della classe segua lo standard Dolibarr
        dol_include_once("/dolifarm/class/dolifarmfarmdossier.class.php");
        
        // Assumiamo che la classe si chiami DolifarmFarmDossier
        $dossierstatic = new DolifarmFarmDossier($this->db);
        
        // Tabella dei fascicoli
        $table_name = MAIN_DB_PREFIX . "dolifarm_farmdossier"; 
        
        $this->info_box_contents = array();

        // ---------------------------------------------------------
        // 1. CONTEGGIO TOTALE FASCICOLI ATTIVI
        // Assumiamo status = 1 per "Attivo"
        // ---------------------------------------------------------
        $sql_count = "SELECT count(*) as nb FROM " . $table_name . " WHERE entity = " . $conf->entity . " AND status = 1";
        
        $total_active = 0;
        $res_count = $this->db->query($sql_count);
        if ($res_count) {
            $obj_count = $this->db->fetch_object($res_count);
            $total_active = $obj_count->nb;
        }

        // ---------------------------------------------------------
        // 2. CONFIGURAZIONE HEADER
        // ---------------------------------------------------------
        // Titolo: "Fascicoli Aziendali (12)"
        // Se non hai la traduzione 'FarmDossiers', userà la stringa di fallback
        $label = $langs->trans("FarmDossiersActive");
        if ($label == "FarmDossiers") $label = "Fascicoli Aziendali"; // Fallback manuale se manca traduzione
        
        $title_text = $label . " (" . $total_active . ")";

        $this->info_box_head = array(
            'text' => $title_text,
            'limit' => 0,
            'graph' => 0,
            // Link alla lista completa specificata da te
            'sublink' => DOL_URL_ROOT . '/dolifarm/dolifarmfarmdossier_list.php',
            'subtext' => $langs->trans("ShowList"),
            // Icona dell'oggetto (assicurati che esista in /dolifarm/img/object_dolifarmfarmdossier.png)
            'subpicto' => 'object_dolifarmfarmdossier@dolifarm', 
        );

        // ---------------------------------------------------------
        // 3. LISTA ULTIMI 5 FASCICOLI ATTIVI
        // ---------------------------------------------------------
        $sql = "SELECT rowid, ref, date_creation, status";
        $sql .= " FROM " . $table_name;
        $sql .= " WHERE entity = " . $conf->entity;
        $sql .= " AND status = 1"; // Solo attivi
        $sql .= " ORDER BY date_creation DESC";
        $sql .= " LIMIT " . $max;

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;

            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                
                // Popoliamo l'oggetto statico
                $dossierstatic->id = $obj->rowid;
                $dossierstatic->ref = $obj->ref;
                $dossierstatic->date_creation = $this->db->jdate($obj->date_creation);
                $dossierstatic->status = $obj->status;
                $dossierstatic->statut = $obj->status; // Per compatibilità

                // Colonna 1: Link e Icona (Ref)
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $dossierstatic->getNomUrl(1), // Mostra icona + ref
                    'asis' => 1,
                );


                // Colonna 3: Data Creazione
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="center"',
                    'text' => dol_print_date($this->db->jdate($obj->date_creation), 'day'),
                );

                // Colonna 4: Stato (Badge)
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="right" width="18px"',
                    'text' => $dossierstatic->getLibStatut(5), // 5 = Solo badge stato
                    'asis' => 1,
                );

                $i++;
            }
        } else {
            dol_print_error($this->db);
        }
    }

    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
?>