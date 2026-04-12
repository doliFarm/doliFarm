<?php
/* File: custom/dolifarm/farmdossier_cropplans.php */

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
// Carichiamo le classi necessarie
dol_include_once('/dolifarm/class/dolifarmfarmdossier.class.php');
dol_include_once('/dolifarm/lib/dolifarm_farmdossier.lib.php');
dol_include_once('/dolitrace/class/dolitracecropplan.class.php');

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Load object
$object = new DoliFarmFarmDossier($db);
if ($id > 0) $object->fetch($id);

// Security
if ($object->id <= 0) accessforbidden();

/* View */
$title = $object->ref . " - " . $langs->trans("CropPlans");
llxHeader('', $title);

// Prepare Head (Tabs)
$head = dolifarmfarmdossierPrepareHead($object);
print dol_get_fiche_head($head, 'cropplans', $langs->trans("FarmDossier"), -1, $object->picto);

// Banner
$linkback = '<a href="' . dol_buildpath('/dolifarm/farmdossier_list.php', 1) . '">' . $langs->trans("BackToList") . '</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

print load_fiche_titre($langs->trans("LinkedCropPlans"), '', 'dolitrace@dolitrace');

// --- LISTA PIANI COLTURALI COLLEGATI ---
$cropplan_static = new DoliTraceCropplan($db);

// Query: Seleziona tutti i piani che hanno fk_dossier = ID del fascicolo corrente
$sql = "SELECT rowid, ref, label, date_start, date_end, status";
$sql .= " FROM " . MAIN_DB_PREFIX . "dolitrace_cropplan";
$sql .= " WHERE fk_dossier = " . ((int)$object->id);
$sql .= " ORDER BY date_start DESC";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    
    // Bottone "Nuovo Piano per questo Fascicolo"
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.dol_buildpath('/dolitrace/dolitracecropplan_card.php', 1).'?action=create&fk_dossier='.$object->id.'&fk_soc='.$object->fk_soc.'">';
    print $langs->trans("AddCropPlan");
    print '</a>';
    print '</div>';

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans("Ref") . '</td>';
    print '<td>' . $langs->trans("Label") . '</td>';
    print '<td>' . $langs->trans("DateStart") . '</td>';
    print '<td>' . $langs->trans("DateEnd") . '</td>';
    print '<td class="right">' . $langs->trans("Status") . '</td>';
    print '</tr>';

    if ($num > 0) {
        while ($obj = $db->fetch_object($resql)) {
            $cropplan_static->id = $obj->rowid;
            $cropplan_static->ref = $obj->ref;
            $cropplan_static->label = $obj->label;
            $cropplan_static->status = $obj->status;

            print '<tr class="oddeven">';
            print '<td>' . $cropplan_static->getNomUrl(1) . '</td>';
            print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
            print '<td>' . dol_print_date($db->jdate($obj->date_start), 'day') . '</td>';
            print '<td>' . dol_print_date($db->jdate($obj->date_end), 'day') . '</td>';
            print '<td class="right">' . $cropplan_static->getLibStatut(5) . '</td>';
            print '</tr>';
        }
    } else {
        print '<tr><td colspan="5" class="opacitymedium">' . $langs->trans("None") . '</td></tr>';
    }
    print '</table>';
} else {
    dol_print_error($db);
}

print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();