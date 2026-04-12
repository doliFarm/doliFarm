<?php
/* Copyright (C) 2026 SuperAdmin */

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
// CONFIGURAZIONE WIDGET (MODIFICA QUI I NOMI DELLE CLASSI)
// ============================================================================
// Verifica aprendo i file in /core/boxes/ come si chiamano esattamente le classi.
// Esempio: "class BoxTotPlots extends ModeleBoxes" -> Il nome è "BoxTotPlots"
// ============================================================================

$widgets_to_load = array(
    array(
        'file'  => 'dolifarm_TotDossiers.php',      // Nome del file fisico
        'class' => 'dolifarmWidgetTotDossiers'           // Nome della CLASS all'interno del file
    ),
    array(
        'file'  => 'dolifarm_TotPlots.php',
        'class' => 'dolifarmWidgetTotPlots'              // Verifica se è 'TotPlots', 'BoxTotPlots' o 'dolifarm_TotPlots'
    ),
    array(
        'file'  => 'dolifarm_TotMachines.php',
        'class' => 'dolifarmWidgetTotMachines'
    ),
    array(
        'file'  => 'dolifarm_CropPlansMonitoring.php',
        'class' => 'dolifarmWidgetCropPlansMonitoring'   // ATTENZIONE alla 'p' minuscola/maiuscola in Cropplans
    ),
    array(
        'file'  => 'dolifarmwidgettopplannedproducts.php',
        'class' => 'dolifarmWidgetTopPlannedProducts'   // ATTENZIONE alla 'p' minuscola/maiuscola in Cropplans
    ),
    
);

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

    $file_path = DOL_DOCUMENT_ROOT . '/custom/dolifarm/core/boxes/' . $widget_info['file'];
    $class_name = $widget_info['class'];

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