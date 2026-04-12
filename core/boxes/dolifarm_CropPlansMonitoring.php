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
 * \file    htdocs/dolifarm/core/boxes/dolifarmwidgetcropplans.php
 * \ingroup dolifarm
 * \brief   Widget to monitor Crop Plans for Dolifarm
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Class to manage the box
 */
class dolifarmWidgetCropPlansMonitoring extends ModeleBoxes
{
    /**
     * @var string Alphanumeric ID. Populated by the constructor.
     */
    public $boxcode = "dolifarmboxcropplans";

    /**
     * @var string Box icon (in configuration page)
     */
    public $boximg = "dolifarm@dolifarm";

    /**
     * @var string Box label (in configuration page)
     */
    public $boxlabel = 'DolifarmBoxCropPlansMonitoring';

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
    public $widgettype = ''; // Lasciamo vuoto per una lista standard, 'graph' serve per i grafici


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
        $this->enabled = 1; // Abilita il widget di default
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

        // --- CONFIGURAZIONE ---
        // Include la classe corretta
        dol_include_once("/dolitrace/class/dolitracecropplan.class.php");
        
        // Istanzia l'oggetto
        $cropplanstatic = new DoliTraceCropplan($this->db);
        
        // Definisce la tabella
        $table_name = MAIN_DB_PREFIX . "dolitrace_cropplan"; 
        
        $this->info_box_contents = array();

        // 1. OTTENERE IL NUMERO TOTALE DI PIANI ATTIVI
        // Filtra per entity e status = 1 (Attivo)
        $sql_count = "SELECT count(*) as nb FROM " . $table_name . " WHERE entity = " . $conf->entity . " AND status = 1";
        
        $total_active = 0;
        $res_count = $this->db->query($sql_count);
        if ($res_count) {
            $obj_count = $this->db->fetch_object($res_count);
            $total_active = $obj_count->nb;
        }

        // Configurazione Intestazione Widget
        // Esempio Titolo: "Monitoraggio Piani Colturali (12)"
        $text = $langs->trans("DolifarmCropPlans") . " (" . $total_active . ")";
        
        // HEADER DEL WIDGET (Modificato per icona e link a destra)
        $this->info_box_head = array(
            'text' => $text,
            'limit' => 0,
            'graph' => 0,
            // Link alla lista completa (cliccando sull'icona a destra)
            'sublink' => DOL_URL_ROOT . '/dolitrace/dolitracecropplan_list.php', 
            // Testo tooltip che appare al passaggio del mouse sull'icona
            'subtext' => $langs->trans("ShowList"), 
            // Icona da mostrare a destra (formato: nome_icona@nome_modulo)
            // Assicurati che esista 'object_dolitracecropplan.png' nella cartella img di dolitrace
            'subpicto' => 'object_dolitracecropplan@dolitrace', 
        );

        // 2. OTTENERE LA LISTA DEGLI ULTIMI 5 PIANI
        $sql = "SELECT rowid, ref, label, datec, status";
        $sql .= " FROM " . $table_name;
        $sql .= " WHERE entity = " . $conf->entity . " AND status = 1";
        $sql .= " ORDER BY datec DESC";
        $sql .= " LIMIT " . $max;

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;

            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                
                // Carichiamo i dati nell'oggetto per sfruttare i metodi nativi (getNomUrl, getLibStatut)
                $cropplanstatic->id = $obj->rowid;
                $cropplanstatic->ref = $obj->ref;
                $cropplanstatic->label = $obj->label;
                $cropplanstatic->datec = $this->db->jdate($obj->datec);
                $cropplanstatic->statut = $obj->status;
                $cropplanstatic->status = $obj->status;

                // Prima colonna: Link all'oggetto (Rif + Icona)
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $cropplanstatic->getNomUrl(1), // 1 = mostra icona e testo
                    'asis' => 1,
                );

                // Seconda colonna: Etichetta / Descrizione
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $obj->label,
                );

                // Terza colonna: Data Creazione
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="center"',
                    'text' => dol_print_date($this->db->jdate($obj->datec), 'day'),
                );

                // Quarta colonna: Stato (Badge)
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="right" width="18px"',
                    'text' => $cropplanstatic->getLibStatut(5), // 5 = mostra solo il badge status
                    'asis' => 1,
                );

                $i++;
            }
        } else {
            dol_print_error($this->db);
        }
    }

    /**
     * Method to show box.
     *
     * @param   array   $head       Array with properties of box title
     * @param   array   $contents   Array with properties of box lines
     * @param   int     $nooutput   No print, only return string
     * @return  string
     */
    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
?>