<?php
/* Copyright (C) 2026       Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
    global $db, $langs, $conf, $user;

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
    if (isModEnabled('dolitrace')) {
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
    }
    
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
    $head[$h][1] = $langs->trans("DoliFarmMachines");
    if ($nbMach > 0) {
        $head[$h][1] .= ' <span class="badge marginleftonlyshort">'.$nbMach.'</span>';
    }
    $head[$h][2] = 'machines';
    $h++;

    // Livestock and buildings of the dossier
    foreach (array('livestock' => 'DoliFarmLivestocks', 'building' => 'DoliFarmBuildings') as $tabkey => $tablabel) {
        if (!$user->hasRight('dolifarm', 'dolifarm'.$tabkey, 'read')) {
            continue;
        }
        $nbRecords = 0;
        $sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."dolifarm_".$tabkey." WHERE fk_dossier = ".((int) $object->id);
        $resql = $db->query($sql);
        if ($resql && $obj = $db->fetch_object($resql)) {
            $nbRecords = (int) $obj->nb;
        }
        $head[$h][0] = dolBuildUrl(dol_buildpath("/dolifarm/dolifarmfarmdossier_".$tabkey.".php", 1), ['id' => $object->id]);
        $head[$h][1] = $langs->trans($tablabel);
        if ($nbRecords > 0) {
            $head[$h][1] .= ' <span class="badge marginleftonlyshort">'.$nbRecords.'</span>';
        }
        $head[$h][2] = $tabkey;
        $h++;
    }

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
/**
 * Banner of the dossier, the same in all its tabs: ref, farm with its CUAA, paying agency, validity with its expiry badge
 *
 * @param	DoliFarmFarmDossier	$object		Dossier
 * @param	int					$onlybanner	1 to print only the banner (no navigation), as dol_banner_tab()
 * @return	void
 */
function dolifarmFarmDossierBanner($object, $onlybanner = 0)
{
	global $langs;

	dol_include_once('/dolifarm/lib/dolifarm_expiry.lib.php');
	$langs->load('dolifarm@dolifarm');

	$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmfarmdossier_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

	$morehtmlref = '<div class="refidno">';
	// Farm and its CUAA (tax code, idprof4 of the third party), the key of the dossier at AGEA
	if ($object->fk_soc > 0 && (is_object($object->thirdparty) || $object->fetch_thirdparty() > 0)) {
		$morehtmlref .= $object->thirdparty->getNomUrl(1);
		if (!empty($object->thirdparty->idprof4)) {
			$morehtmlref .= ' <span class="opacitymedium">- '.$langs->trans('CUAA').':</span> '.dol_escape_htmltag($object->thirdparty->idprof4);
		} else {
			$morehtmlref .= ' '.img_warning($langs->trans('CUAAMissingHelp')).' <span class="opacitymedium">'.$langs->trans('CUAAMissing').'</span>';
		}
	}
	// Paying agency and validity of the dossier
	$more = array();
	if (!empty($object->paying_agency)) {
		$more[] = '<span class="opacitymedium">'.$langs->trans($object->fields['paying_agency']['label']).':</span> '.$object->showOutputField($object->fields['paying_agency'], 'paying_agency', $object->paying_agency, '');
	}
	if (!empty($object->date_validity_end)) {
		// With the badge of the expiry (the only expiry date of a dossier)
		$more[] = '<span class="opacitymedium">'.$langs->trans($object->fields['date_validity_end']['label']).':</span> '.dol_print_date($object->date_validity_end, 'day').dolifarmExpiryBadge($object->date_validity_end, 0);
	}
	if ($more) {
		$morehtmlref .= '<br>'.implode(' &nbsp; ', $more);
	}
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', '', $onlybanner);
}
