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
 * \file    htdocs/dolifarm/core/boxes/dolifarmwidgettotmachines.php
 * \ingroup dolifarm
 * \brief   Widget to monitor active Machines
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Class to manage the box
 */
class dolifarmWidgetTotMachines extends ModeleBoxes
{
    public $boxcode = "dolifarmboxTotMachines";
    public $boximg = "dolifarm@dolifarm";
    public $boxlabel = 'DolifarmBoxTotMachines';
    public $lang = 'dolifarm@dolifarm';
    public $depends = array('dolifarm');
    public $widgettype = '';

    /**
     * Constructor
     */
    public function __construct(DoliDB $db, $param = '')
    {
        global $user;
        parent::__construct($db, $param);
        $this->param = $param;
        $this->enabled = 1;
    }

    /**
     * Load data
     */
    public function loadBox($max = 5)
    {
        global $user, $langs, $conf;

        $this->max = $max;

        // --- CARICAMENTO CLASSI ---
        // TODO: Verifica percorso e nome file corretti
        dol_include_once("/dolifarm/class/dolifarmmachine.class.php");
        dol_include_once("/societe/class/societe.class.php"); // Per il proprietario

        // TODO: Verifica nome classe corretto
        $machinestatic = new DolifarmMachine($this->db);
        $societestatic = new Societe($this->db);
        
        // TODO: Verifica nome tabella
        $table_name = MAIN_DB_PREFIX . "dolifarm_machine"; 
        
        $this->info_box_contents = array();

        // 1. CONTEGGIO TOTALE MACCHINE ATTIVE
        $sql_count = "SELECT count(*) as nb FROM " . $table_name . " WHERE entity = " . $conf->entity . " AND status = 1";
        
        $total_active = 0;
        $res_count = $this->db->query($sql_count);
        if ($res_count) {
            $obj_count = $this->db->fetch_object($res_count);
            $total_active = $obj_count->nb;
        }

        // 2. CONFIGURAZIONE HEADER
        // Titolo: "Machines (10)" o "Macchine (10)"
        $label = $langs->trans("Machines");
        if ($label == "Machines") $label = "Macchine"; // Fallback se manca traduzione
        
        $title_text = $label . " (" . $total_active . ")";

        $this->info_box_head = array(
            'text' => $title_text,
            'limit' => 0,
            'graph' => 0,
            // Link alla lista completa
            'sublink' => DOL_URL_ROOT . '/dolifarm/dolifarmmachine_list.php',
            'subtext' => $langs->trans("ShowList"),
            // Icona specifica oggetto
            'subpicto' => 'object_dolifarmmachine@dolifarm', 
        );

        // 3. LISTA MACCHINE (Con fk_soc per il proprietario)
        $sql = "SELECT t.rowid, t.ref, t.label, t.datec, t.status, t.fk_soc";
        $sql .= " FROM " . $table_name . " as t";
        $sql .= " WHERE t.entity = " . $conf->entity;
        $sql .= " AND t.status = 1"; // Solo attive
        $sql .= " ORDER BY t.datec DESC";
        $sql .= " LIMIT " . $max;

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;

            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                
                // Popoliamo oggetto Machine
                $machinestatic->id = $obj->rowid;
                $machinestatic->ref = $obj->ref;
                $machinestatic->label = $obj->label;
                $machinestatic->datec = $this->db->jdate($obj->datec);
                $machinestatic->status = $obj->status;
                $machinestatic->statut = $obj->status;

                // Gestione Proprietario (Azienda)
                $owner_html = '';
                if ($obj->fk_soc > 0) {
                    $societestatic->fetch($obj->fk_soc);
                    // getNomUrl(1) restituisce icona + nome linkato
                    $owner_html = $societestatic->getNomUrl(1, 'customer', 24); 
                } else {
                    $owner_html = '<span class="opacitymedium">'.$langs->trans("Unknown").'</span>';
                }

                // COL 1: Link e Icona Macchina
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $machinestatic->getNomUrl(1),
                    'asis' => 1,
                );

                // COL 2: Etichetta / Nome Macchina
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => dol_trunc($obj->label, 20),
                );

                // COL 3: In carico a (Azienda)
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="tdoverflowmax150"',
                    'text' => $owner_html,
                    'asis' => 1,
                );

                // COL 4: Data Creazione
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="center"',
                    'text' => dol_print_date($this->db->jdate($obj->datec), 'day'),
                );

                // COL 5: Stato (Badge)
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="right" width="18px"',
                    'text' => $machinestatic->getLibStatut(5),
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