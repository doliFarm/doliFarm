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
 * \file    htdocs/dolifarm/core/boxes/dolifarmwidgettotplots.php
 * \ingroup dolifarm
 * \brief   Widget to monitor active Plots (Appezzamenti)
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Class to manage the box
 */
class dolifarmWidgetTotPlots extends ModeleBoxes
{
    public $boxcode = "dolifarmboxTotPlots";
    public $boximg = "dolifarm@dolifarm";
    public $boxlabel = 'DolifarmBoxTotPlots';
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

        // --- CLASSI NECESSARIE ---
        dol_include_once("/dolifarm/class/dolifarmplot.class.php");
        dol_include_once("/societe/class/societe.class.php"); // Per gestire l'azienda proprietaria

        $plotstatic = new DolifarmPlot($this->db);
        $societestatic = new Societe($this->db);
        
        $table_name = MAIN_DB_PREFIX . "dolifarm_plot"; 
        
        $this->info_box_contents = array();

        // 1. CONTEGGIO TOTALE
        $sql_count = "SELECT count(*) as nb FROM " . $table_name . " WHERE entity = " . $conf->entity . " AND status = 1";
        
        $total_active = 0;
        $res_count = $this->db->query($sql_count);
        if ($res_count) {
            $obj_count = $this->db->fetch_object($res_count);
            $total_active = $obj_count->nb;
        }

        // 2. HEADER
        $title_text = $langs->trans("Plots") . " (" . $total_active . ")";
        
        $this->info_box_head = array(
            'text' => $title_text,
            'limit' => 0,
            'graph' => 0,
            'sublink' => DOL_URL_ROOT . '/dolifarm/dolifarmplot_list.php',
            'subtext' => $langs->trans("ShowList"),
            'subpicto' => 'object_dolifarmplot@dolifarm', 
        );

        // 3. LISTA PLOTS (Con fk_soc per il proprietario)
        // Aggiungiamo t.fk_soc alla query
        $sql = "SELECT t.rowid, t.ref, t.label, t.date_creation, t.status, t.fk_soc";
        $sql .= " FROM " . $table_name . " as t";
        $sql .= " WHERE t.entity = " . $conf->entity;
        $sql .= " AND t.status = 1"; 
        $sql .= " ORDER BY t.date_creation DESC";
        $sql .= " LIMIT " . $max;

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;

            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                
                // Setup Plot Object
                $plotstatic->id = $obj->rowid;
                $plotstatic->ref = $obj->ref;
                $plotstatic->label = $obj->label;
                $plotstatic->date_creation = $this->db->jdate($obj->date_creation);
                $plotstatic->status = $obj->status;
                $plotstatic->statut = $obj->status;

                // Setup ThirdParty Object (Chi è in carico)
                $owner_html = '';
                if ($obj->fk_soc > 0) {
                    $societestatic->fetch($obj->fk_soc);
                    // getNomUrl(1) restituisce Icona + Nome linkato
                    $owner_html = $societestatic->getNomUrl(1, 'customer', 24); 
                } else {
                    $owner_html = '<span class="opacitymedium">'.$langs->trans("Unknown").'</span>';
                }

                // COL 1: Link e Icona Plot
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => $plotstatic->getNomUrl(1),
                    'asis' => 1,
                );

                // COL 2: Etichetta Plot
                $this->info_box_contents[$i][] = array(
                    'td' => '',
                    'text' => dol_trunc($obj->label, 20),
                );

                // COL 3: [NUOVO] Chi è in carico (Azienda/Proprietario)
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="tdoverflowmax150"', // Classe CSS per troncare nomi lunghi se necessario
                    'text' => $owner_html,
                    'asis' => 1, // Importante per renderizzare l'HTML del link azienda
                );

                // COL 4: Data
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="center"',
                    'text' => dol_print_date($this->db->jdate($obj->date_creation), 'day'),
                );

                // COL 5: Stato
                $this->info_box_contents[$i][] = array(
                    'td' => 'class="right" width="18px"',
                    'text' => $plotstatic->getLibStatut(5),
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