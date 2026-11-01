<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com	<info@dolifarm.com> */

// 1. CARICAMENTO AMBIENTE
$res = 0;
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
if (! $res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (! $res) die("Include of main fails");

include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Caricamento lingue
$langs->loadLangs(array("dolifarm@dolifarm", "boxes"));

$title = $langs->trans("DoliFarmArea");

llxHeader("", $title);

// CSS per nascondere i bottoni inutili
print '<style>
    .boxhandle, .boxclose, .box-title .ico, .boxheader .ico { display: none !important; }
    .box { cursor: default !important; }
    .box-error { border: 1px solid red; padding: 10px; color: red; background: #ffeeee; margin-bottom: 10px; }
</style>';
dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');
print load_fiche_titre($title, '', dolifarm_get_fa_icon('dolifarm'));


// ============================================================================
// CONFIGURAZIONE WIDGET
// ============================================================================
// Il nome della classe coincide con il nome del file in /core/boxes/ (come
// richiesto dal loader dei widget di Dolibarr). 'module' indica un modulo
// esterno necessario al widget.
// ============================================================================

$widgets_to_load = array(
    array('file' => 'dolifarm_TotDossiers.php'),
    array('file' => 'dolifarm_TotPlots.php'),
    array('file' => 'dolifarm_TotMachines.php'),
    array('file' => 'dolifarm_CropPlansMonitoring.php', 'module' => 'dolitrace'),
    array('file' => 'dolifarmwidgettopplannedproducts.php', 'module' => 'dolitrace'),
);

// Escludi i widget dei moduli non attivi
foreach ($widgets_to_load as $key => $widget_info) {
    if (!empty($widget_info['module']) && !isModEnabled($widget_info['module'])) {
        unset($widgets_to_load[$key]);
    }
}
$widgets_to_load = array_values($widgets_to_load);

print '<div class="fichecenter">';

// ============================================================================
// GENERAZIONE AUTOMATICA WIDGET
// ============================================================================

// Contatore per gestire le colonne (sinistra/destra)
$i = 0;
$total = count($widgets_to_load);
$half = ceil($total / 2);

print '<div class="fichehalfleft">';

foreach ($widgets_to_load as $index => $widget_info) {
    
    // Cambio colonna a metà
    if ($index == $half) {
        print '</div><div class="fichehalfright">';
    }

    $file_path = dol_buildpath('/dolifarm/core/boxes/'.$widget_info['file'], 0);
    $class_name = preg_replace('/\.php$/i', '', $widget_info['file']);

    print '<div class="box-wrapper">';
    
    // 1. Controllo esistenza file
    if (file_exists($file_path)) {
        include_once $file_path;

        // 2. Controllo esistenza classe (Evita Fatal Error)
        if (class_exists($class_name)) {
            try {
                $box = new $class_name($db, '');
                $box->loadBox(5);
                $box->showBox();
            } catch (Exception $e) {
                print '<div class="box-error">Errore nel widget <strong>'.$class_name.'</strong>: '.$e->getMessage().'</div>';
            }
        } else {
            print '<div class="box-error">Errore: La classe <strong>'.$class_name.'</strong> non è stata trovata nel file <em>'.$widget_info['file'].'</em>.<br>Apri il file e controlla la riga "class Xyz extends...".</div>';
        }
    } else {
        print '<div class="box-error">Errore: File non trovato: <em>'.$widget_info['file'].'</em></div>';
    }

    print '</div>'; // Fine box-wrapper
}

print '</div>'; // Fine colonna destra
print '</div>'; // Fine fichecenter

llxFooter();
$db->close();