<?php
/* Copyright (C) 2024 CreditGuard Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup creditguard
 * \brief   Page de configuration du module CreditGuard
 */

// Chargement de l'environnement Dolibarr
$res = 0;
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
dol_include_once('/creditguard/lib/creditguard.lib.php');

// Contrôle d'accès
if (!$user->admin) {
    accessforbidden();
}

$langs->loadLangs(array('admin', 'creditguard@creditguard'));

$action = GETPOST('action', 'aZ09');
$error  = 0;

// -----------------------------------------------------------------------
// Traitement de la sauvegarde
// -----------------------------------------------------------------------
if ($action === 'update' && !empty($_POST)) {
    $extrafield_plafond   = GETPOST('CREDITGUARD_EXTRAFIELD_PLAFOND', 'alpha');
    $extrafield_deblocage = GETPOST('CREDITGUARD_EXTRAFIELD_DEBLOCAGE', 'alpha');
    $webhook_url          = GETPOST('CREDITGUARD_WEBHOOK_URL', 'alpha');
    $seuil_alerte         = (int) GETPOST('CREDITGUARD_SEUIL_ALERTE', 'int');

    // Validations
    if (empty($extrafield_plafond)) {
        setEventMessages('Le champ "Extrafield plafond" est obligatoire.', null, 'errors');
        $error++;
    }

    if (!empty($webhook_url) && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        setEventMessages('L\'URL du webhook n\'est pas valide.', null, 'errors');
        $error++;
    }

    if ($seuil_alerte < 1 || $seuil_alerte > 99) {
        setEventMessages('Le seuil d\'alerte doit être compris entre 1 et 99 %.', null, 'errors');
        $error++;
    }

    if (!$error) {
        dolibarr_set_const($db, 'CREDITGUARD_EXTRAFIELD_PLAFOND',   $extrafield_plafond,   'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_EXTRAFIELD_DEBLOCAGE', $extrafield_deblocage, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_WEBHOOK_URL',          $webhook_url,          'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_SEUIL_ALERTE',         $seuil_alerte,         'chaine', 0, '', $conf->entity);

        setEventMessages('Configuration sauvegardée avec succès.', null, 'mesgs');
    }
}

// -----------------------------------------------------------------------
// Récupération des valeurs courantes
// -----------------------------------------------------------------------
$current_plafond   = $conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND   ?? 'plafond_credit';
$current_deblocage = $conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE ?? 'deblocage_manuel';
$current_webhook   = $conf->global->CREDITGUARD_WEBHOOK_URL          ?? '';
$current_seuil     = $conf->global->CREDITGUARD_SEUIL_ALERTE         ?? 80;

// Récupération des extrafields disponibles sur l'objet "societe"
$extrafieldsList = array();
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label('societe');
if (!empty($extrafields->attributes['societe']['label'])) {
    foreach ($extrafields->attributes['societe']['label'] as $key => $label) {
        $extrafieldsList[$key] = $key . ' (' . $label . ')';
    }
}

// -----------------------------------------------------------------------
// Affichage de la page
// -----------------------------------------------------------------------
$page_name = 'CreditGuard – Configuration';
llxHeader('', $page_name);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">'
    . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre($page_name, $linkback, 'title_setup');
print dol_get_fiche_head(array(), '', $page_name, -1);

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table summary="edit" class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield">' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

// -- Extrafield Plafond --
print '<tr class="oddeven">';
print '<td>';
print '<label for="CREDITGUARD_EXTRAFIELD_PLAFOND"><b>Extrafield "Plafond de crédit"</b></label>';
print '<br><small>Nom de l\'extrafield du tiers contenant le montant plafond (ex : <code>plafond_credit</code>)</small>';
print '</td>';
print '<td>';
if (!empty($extrafieldsList)) {
    print '<select name="CREDITGUARD_EXTRAFIELD_PLAFOND" id="CREDITGUARD_EXTRAFIELD_PLAFOND_select" class="flat minwidth300">';
    print '<option value="">-- Choisir dans la liste --</option>';
    foreach ($extrafieldsList as $key => $label) {
        $selected = ($key == $current_plafond) ? ' selected="selected"' : '';
        print '<option value="' . dol_escape_htmltag($key) . '"' . $selected . '>'
            . dol_escape_htmltag($label) . '</option>';
    }
    print '</select>';
    print '<br>';
}
print '<input type="text" name="CREDITGUARD_EXTRAFIELD_PLAFOND" id="CREDITGUARD_EXTRAFIELD_PLAFOND"'
    . ' class="flat minwidth200"'
    . ' value="' . dol_escape_htmltag($current_plafond) . '"'
    . ' placeholder="plafond_credit">';
print '</td>';
print '</tr>';

// -- Extrafield Déblocage --
print '<tr class="oddeven">';
print '<td>';
print '<label for="CREDITGUARD_EXTRAFIELD_DEBLOCAGE"><b>Extrafield "Déblocage manuel"</b></label>';
print '<br><small>Nom de l\'extrafield booléen (checkbox) du tiers (ex : <code>deblocage_manuel</code>)</small>';
print '</td>';
print '<td>';
if (!empty($extrafieldsList)) {
    print '<select name="CREDITGUARD_EXTRAFIELD_DEBLOCAGE" id="CREDITGUARD_EXTRAFIELD_DEBLOCAGE_select" class="flat minwidth300">';
    print '<option value="">-- Aucun --</option>';
    foreach ($extrafieldsList as $key => $label) {
        $selected = ($key == $current_deblocage) ? ' selected="selected"' : '';
        print '<option value="' . dol_escape_htmltag($key) . '"' . $selected . '>'
            . dol_escape_htmltag($label) . '</option>';
    }
    print '</select>';
    print '<br>';
}
print '<input type="text" name="CREDITGUARD_EXTRAFIELD_DEBLOCAGE" id="CREDITGUARD_EXTRAFIELD_DEBLOCAGE"'
    . ' class="flat minwidth200"'
    . ' value="' . dol_escape_htmltag($current_deblocage) . '"'
    . ' placeholder="deblocage_manuel">';
print '</td>';
print '</tr>';

// -- Seuil d'alerte --
print '<tr class="oddeven">';
print '<td>';
print '<label for="CREDITGUARD_SEUIL_ALERTE"><b>Seuil d\'alerte (%)</b></label>';
print '<br><small>Pourcentage d\'encours à partir duquel l\'alerte webhook est envoyée (défaut : 80)</small>';
print '</td>';
print '<td>';
print '<input type="number" name="CREDITGUARD_SEUIL_ALERTE" id="CREDITGUARD_SEUIL_ALERTE"'
    . ' class="flat width75" min="1" max="99"'
    . ' value="' . (int) $current_seuil . '"> %';
print '</td>';
print '</tr>';

// -- URL Webhook --
print '<tr class="oddeven">';
print '<td>';
print '<label for="CREDITGUARD_WEBHOOK_URL"><b>URL Webhook</b></label>';
print '<br><small>Endpoint HTTP(S) recevant les alertes JSON (laisser vide pour désactiver)</small>';
print '</td>';
print '<td>';
print '<input type="url" name="CREDITGUARD_WEBHOOK_URL" id="CREDITGUARD_WEBHOOK_URL"'
    . ' class="flat minwidth400"'
    . ' value="' . dol_escape_htmltag($current_webhook) . '"'
    . ' placeholder="https://mon-serveur.exemple.com/webhook/creditguard">';
print '</td>';
print '</tr>';

print '</table>';

print '<br>';
print '<div class="center">';
print '<input type="submit" class="button button-save" value="' . $langs->trans('Save') . '">';
print '</div>';
print '</form>';

// -----------------------------------------------------------------------
// Zone de test de l'encours (débogage admin)
// -----------------------------------------------------------------------
$test_socid = (int) GETPOST('test_socid', 'int');
if ($user->admin && $test_socid > 0) {
    $encours   = creditguard_get_encours($db, $test_socid);
    $plafond   = creditguard_get_plafond($db, $test_socid, $current_plafond);
    $deblocage = creditguard_is_deblocage_actif($db, $test_socid, $current_deblocage);

    print '<br><div class="info">';
    print '<b>Test tiers #' . $test_socid . ' :</b><br>';
    print 'Encours calculé : <b>' . price($encours) . ' €</b><br>';
    print 'Plafond : <b>' . price($plafond) . ' €</b><br>';
    if ($plafond > 0) {
        $ratio_test = round(($encours / $plafond) * 100, 1);
        print 'Ratio : <b>' . $ratio_test . ' %</b><br>';
    }
    print 'Déblocage manuel actif : <b>' . ($deblocage ? 'OUI' : 'NON') . '</b>';
    print '</div>';
}

print '<br>';
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
print '<b>Tester l\'encours pour le tiers ID :</b> ';
print '<input type="number" name="test_socid" class="flat width75" value="' . $test_socid . '" min="1">';
print ' <input type="submit" class="button button-search" value="Calculer">';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
