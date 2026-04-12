<?php
/* Copyright (C) 2026       SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       dolifarmfarmdossier_machines.php
 * \ingroup    dolifarm
 * \brief      Tab for Machines linked to a Farm Dossier
 */

// --------------------------------------------------------------------
// BOOTSTRAP ROBUSTO
// --------------------------------------------------------------------
$res = 0;
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
if (! $res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

// Caricamento Classi
dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
// NOTA: Usiamo la classe plurale come da schema DB consolidato
dol_include_once('/dolifarm/class/dolifarmmachine.class.php'); 
dol_include_once('/dolifarm/lib/dolifarm_farmdossier.lib.php');

// Langs
$langs->loadLangs(array("dolifarm@dolifarm", "companies", "other"));

// Parametri
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Oggetti
$object = new DoliFarmFarmDossier($db);
$child = new DoliFarmMachine($db);

// Security check
if ($id > 0) {
    $object->fetch($id);
    $object->fetch_thirdparty();
}
if (!isModEnabled('dolifarm')) accessforbidden('Module not enabled');
$permissiontoread = $user->rights->dolifarm->dolifarmfarmdossier->read;
if (!$permissiontoread) accessforbidden();


/*
 * View
 */
$form = new Form($db);
$title = $object->ref . " - " . $langs->trans('Macchine');
$help_url = '';

llxHeader('', $title, $help_url);

if ($id > 0) {
    // 1. HEADER DEL FASCICOLO (Tab 'machines')
    $head = dolifarmfarmdossierPrepareHead($object);
    print dol_get_fiche_head($head, 'machines', $langs->trans("DoliFarmFarmDossier"), -1, $object->picto);

    // Link al padre
    $linkback = '<a href="' . dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1) . '">' . $langs->trans("BackToList") . '</a>';
    
    // Banner Principale
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    
    // 2. QUERY PER LISTA MACCHINE COLLEGATE
    // Nota: La tabella nel DB consolidato è 'llx_dolifarm_machine' (o machines, verifica install.sql)
    // Usiamo $child->table_element per essere sicuri se la classe è configurata bene.
    // Fallback: usiamo MAIN_DB_PREFIX . "dolifarm_machine" come da tuo schema ultimo.
    
    $sql = "SELECT t.rowid, t.ref, t.label, t.fk_type, t.power_kw, t.fuel_type, t.status";
    $sql .= " FROM " . MAIN_DB_PREFIX . "dolifarm_machine as t"; 
    $sql .= " WHERE t.fk_dossier = " . ((int)$object->id);
    $sql .= " ORDER BY t.ref ASC";

    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);

        // Pulsante "Nuova Macchina"
        if ($user->rights->dolifarm->dolifarmmachine->write) {
            print '<div class="tabsAction">';
            // Passiamo fk_dossier e fk_soc nell'URL per precompilare il form
            // Nota: il file card si chiama dolifarmmachine_card.php (singolare) nel tuo sistema file
            print '<a class="butAction" href="'.dol_buildpath('/dolifarm/dolifarmmachine_card.php', 1).'?action=create&fk_dossier='.$object->id.'&fk_soc='.$object->fk_soc.'">';
            print $langs->trans("AddMachine");
            print '</a>';
            print '</div>';
        }

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>' . $langs->trans("Ref/Targa") . '</td>';
        print '<td>' . $langs->trans("Label") . '</td>';
        print '<td>' . $langs->trans("Type") . '</td>';
        print '<td>' . $langs->trans("Fuel") . '</td>';
        print '<td class="right">' . $langs->trans("Power (KW)") . '</td>';
        print '<td class="right">' . $langs->trans("Status") . '</td>';
        print '</tr>';

        if ($num > 0) {
            while ($obj = $db->fetch_object($resql)) {
                $child->id = $obj->rowid;
                $child->ref = $obj->ref;
                $child->label = $obj->label;
                $child->status = $obj->status;

                print '<tr class="oddeven">';
                
                // Ref Link
                print '<td>';
                print $child->getNomUrl(1);
                print '</td>';
                
                print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
                
                // Type (Traduzione dizionario se necessario)
                $label_type = $langs->trans($obj->fk_type);
                print '<td>' . $label_type . '</td>';

                print '<td>' . dol_escape_htmltag($obj->fuel_type) . '</td>';

                print '<td class="right">' . $obj->power_kw . '</td>';
                print '<td class="right">' . $child->getLibStatut(5) . '</td>';
                print '</tr>';
            }
        } else {
            print '<tr><td colspan="6" class="opacitymedium">' . $langs->trans("None") . '</td></tr>';
        }
        print '</table>';
        
        $db->free($resql);
    } else {
        dol_print_error($db);
    }

    print '</div>';
    print dol_get_fiche_end();
}

llxFooter();
$db->close();