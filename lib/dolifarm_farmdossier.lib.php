<?php
/* Copyright (C) 2026       SuperAdmin
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolifarm_farmdossier.lib.php
 * \ingroup dolifarm
 * \brief   Library files with common functions for DoliFarmFarmDossier
 */

/**
 * Prepare array of tabs for DoliFarmFarmDossier
 *
 * @param   DoliFarmFarmdossier $object                 DoliFarmFarmdossier
 * @return  array<array{string,string,string}>  Array of tabs
 */
function dolifarmfarmdossierPrepareHead($object)
{
    global $db, $langs, $conf;

    $langs->load("dolifarm@dolifarm");

    // I tab sono attivati di default per garantire completezza
    // $showtabofpagecontact = getDolGlobalInt('MAIN_DOLIFARM_SHOW_PAGE_OF_CONTACT');
    // ... rimossi check condizionali ...

    $h = 0;
    $head = array();

    // ----------------------------------------------------------------------
    // TAB 1: SCHEDA RIEPILOGO (Card)
    // ----------------------------------------------------------------------
    $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_card.php", 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans("DoliFarmFarmDossier");
    $head[$h][2] = 'card';
    $h++;
    // ----------------------------------------------------------------------
    // TAB 2: Piani di produzione (Cropplans)
    // ----------------------------------------------------------------------
    // Calcolo badge: Conta i cropplan collegati a questo dossier
    $nbCropplans = 0;
    $sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."dolitrace_cropplan WHERE fk_dossier = ".((int)$object->id);
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        $nbCropplans = $obj->nb;
    }
    $head[$h][0] = dol_buildpath('/dolifarm/farmdossier_cropplans.php', 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("CropPlans");
    if ($nbCropplans > 0) {
        $head[$h][1] .= ' <span class="badge marginleftonlyshort">'.$nbCropplans.'</span>';
    }
    $head[$h][2] = 'cropplans';
    $h++;
    
    // ----------------------------------------------------------------------
    // TAB 2: TERRENI (Plots)
    // ----------------------------------------------------------------------
    // Calcolo badge: Conta i terreni collegati a questo dossier
    $nbPlots = 0;
    $sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."dolifarm_plot WHERE fk_dossier = ".((int)$object->id);
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        $nbPlots = $obj->nb;
    }

    $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_plots.php", 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans("Terreni");
    if ($nbPlots > 0) {
        $head[$h][1] .= ' <span class="badge marginleftonlyshort">'.$nbPlots.'</span>';
    }
    $head[$h][2] = 'plots';
    $h++;

    // ----------------------------------------------------------------------
    // TAB 3: MACCHINE (Machines)
    // ----------------------------------------------------------------------
    // Calcolo badge: Conta le macchine collegate a questo dossier
    $nbMach = 0;
    $sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."dolifarm_machine WHERE fk_dossier = ".((int)$object->id);
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        $nbMach = $obj->nb;
    }

    $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_machines.php", 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans("Macchine");
    if ($nbMach > 0) {
        $head[$h][1] .= ' <span class="badge marginleftonlyshort">'.$nbMach.'</span>';
    }
    $head[$h][2] = 'machines';
    $h++;

    // ----------------------------------------------------------------------
    // TAB 4: CONTATTI (Contacts)
    // ----------------------------------------------------------------------
    $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_contact.php", 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans("Contacts");
    $head[$h][2] = 'contact';
    $h++;

    // ----------------------------------------------------------------------
    // TAB 5: NOTE (Notes)
    // ----------------------------------------------------------------------
    $nbNote = 0;
    if (!empty($object->note_private)) $nbNote++;
    if (!empty($object->note_public)) $nbNote++;
    
    $head[$h][0] = dolBuildUrl(dol_buildpath('/dolifarm/dolifarmfarmdossier_note.php', 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans('Notes');
    if ($nbNote > 0) {
        $head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
    }
    $head[$h][2] = 'note';
    $h++;

    // ----------------------------------------------------------------------
    // TAB 6: DOCUMENTI (Documents)
    // ----------------------------------------------------------------------
    require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
    
    $upload_dir = $conf->dolifarm->dir_output."/dolifarmfarmdossier/".dol_sanitizeFileName($object->ref);
    $nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
    $nbLinks = Link::count($db, $object->element, $object->id);
    
    $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_document.php", 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans('Documents');
    if (($nbFiles + $nbLinks) > 0) {
        $head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
    }
    $head[$h][2] = 'document';
    $h++;

    // ----------------------------------------------------------------------
    // TAB 7: AGENDA (Events)
    // ----------------------------------------------------------------------
    $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_agenda.php", 1), ['id' => $object->id]);
    $head[$h][1] = $langs->trans("Events");
    $head[$h][2] = 'agenda';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //  'entity:+tabname:Title:@dolifarm:/dolifarm/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //  'entity:-tabname:Title:@dolifarm:/dolifarm/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolifarmfarmdossier@dolifarm');

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolifarmfarmdossier@dolifarm', 'remove');

    return $head;
}