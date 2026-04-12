<?php
/**
 * Library to manage FontAwesome Icons for DoliFarm Ecosystem
 * File: custom/dolifarm/lib/dolifarm_icons.lib.php
 */

/**
 * Restituisce il codice icona FontAwesome (v5 Free) per un dato oggetto/elemento.
 *
 * @param   string  $element_type   Il nome dell'oggetto (es. 'dolifarmplot', 'audit', 'machine')
 * @param   bool    $full_tag       Se TRUE restituisce il tag HTML <i>, se FALSE solo la stringa 'fa-xxx'
 * @return  string                  Codice icona o tag HTML
 */
function dolifarm_get_fa_icon($element_type, $full_tag = false) {
    
    // Normalizziamo l'input in minuscolo per evitare errori di case-sensitivity
    $key = strtolower($element_type);

    // Mappa Centrale delle Icone
    $icons = array(
        // --- DOLIFARM ---
        'dolifarm'              => 'fa-leaf',            // Modulo DoliFarm
        'dolifarmplot'          => 'fa-map-marked-alt',     // Appezzamenti
        'plot'                  => 'fa-map-marked-alt',     // Alias
        'dolifarmmachine'       => 'fa-tractor',            // Macchinari
        'machine'               => 'fa-tractor',            // Alias
        'dolifarmcrops'         => 'fa-seedling',           // Colture
        'crops'                 => 'fa-seedling',           // Alias
        'dolifarmagrodrug'      => 'fa-flask',              // Fitofarmaci
        'agrodrug'              => 'fa-flask',              // Alias
        'dolifarmfarmdossier'   => 'fa-file-contract',               // Fascicolo Aziendale
        'farmdossier'           => 'fa-file-contract',               // Alias
        'dolifarmestimatecosts' => 'fa-coins',              // Stima Costi
        'estimatecosts'         => 'fa-coins',              // Alias

        // --- DOLITRACE ---
        'dolitrace'             => 'fa-route',              // Modulo DoliTrace
        'dolitracecropplan'     => 'fa-clipboard-list',     // Piano Colturale
        'cropplan'              => 'fa-clipboard-list',     // Alias
        'dolitraceoperation'    => 'fa-tractor',              // Operazioni
        'operation'             => 'fa-tractor',              // Alias
        'dolitraceharvest'      => 'fa-shopping-basket',    // Raccolto
        'harvest'               => 'fa-shopping-basket',    // Alias

        // --- DOLIAGROPASS ---
        'doliagropass'          => 'fa-tasks',        // Modulo DoliAgroPass
        'doliagropassaudit'     => 'fa-clipboard-check',    // Audit
        'audit'                 => 'fa-clipboard-check',    // Alias
        'doliagropassindicator' => 'fa-chart-line',         // Indicatori
        'indicator'             => 'fa-chart-line',         // Alias

        // --- DEFAULT ---
        'default'               => 'fa-cube'                // Icona di fallback
    );

    // Recupera l'icona o usa il default
    $icon_code = isset($icons[$key]) ? $icons[$key] : $icons['default'];

    // Restituzione
    if ($full_tag) {
        return '<i class="fa ' . $icon_code . '" aria-hidden="true"></i>';
    } else {
        return $icon_code;
    }
}
?>