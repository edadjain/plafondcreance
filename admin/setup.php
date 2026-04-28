<?php
/* Copyright (C) 2024 CreditGuard
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup creditguard
 * \brief   Page de configuration du module CreditGuard
 */

// Chargement de l'environnement Dolibarr (remonte jusqu'à main.inc.php)
$res = 0;
if (!$res && file_exists('../main.inc.php'))       { $res = @include '../main.inc.php'; }
if (!$res && file_exists('../../main.inc.php'))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
dol_include_once('/creditguard/lib/creditguard.lib.php');

// Accès réservé aux administrateurs
if (!$user->admin) {
    accessforbidden();
}

$langs->loadLangs(array('admin'));

$action = GETPOST('action', 'aZ09');
$error  = 0;

// -----------------------------------------------------------------------
// Traitement POST – sauvegarde
// -----------------------------------------------------------------------
if ($action === 'update') {
    $p_plafond   = GETPOST('CREDITGUARD_EXTRAFIELD_PLAFOND',   'alpha');
    $p_deblocage = GETPOST('CREDITGUARD_EXTRAFIELD_DEBLOCAGE', 'alpha');
    $p_webhook   = GETPOST('CREDITGUARD_WEBHOOK_URL',          'alpha');
    $p_seuil     = (int) GETPOST('CREDITGUARD_SEUIL_ALERTE',   'int');

    if (empty($p_plafond)) {
        setEventMessages('Le nom de l\'extrafield "Plafond" est obligatoire.', null, 'errors');
        $error++;
    }
    if (!empty($p_webhook) && !filter_var($p_webhook, FILTER_VALIDATE_URL)) {
        setEventMessages('L\'URL du webhook n\'est pas valide.', null, 'errors');
        $error++;
    }
    if ($p_seuil < 1 || $p_seuil > 99) {
        setEventMessages('Le seuil d\'alerte doit être compris entre 1 et 99 %.', null, 'errors');
        $error++;
    }

    if (!$error) {
        dolibarr_set_const($db, 'CREDITGUARD_EXTRAFIELD_PLAFOND',   $p_plafond,   'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_EXTRAFIELD_DEBLOCAGE', $p_deblocage, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_WEBHOOK_URL',          $p_webhook,   'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_SEUIL_ALERTE',         $p_seuil,     'chaine', 0, '', $conf->entity);
        setEventMessages('Configuration sauvegardée.', null, 'mesgs');
    }
}

// -----------------------------------------------------------------------
// Valeurs courantes
// -----------------------------------------------------------------------
$cur_plafond   = $conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND   ?? 'plafond_credit';
$cur_deblocage = $conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE ?? 'deblocage_manuel';
$cur_webhook   = $conf->global->CREDITGUARD_WEBHOOK_URL          ?? '';
$cur_seuil     = $conf->global->CREDITGUARD_SEUIL_ALERTE         ?? 80;

// Extrafields disponibles sur la fiche Tiers (pour peupler les listes)
$xf = new ExtraFields($db);
$xf->fetch_name_optionals_label('societe');
$xf_list = array();
if (!empty($xf->attributes['societe']['label'])) {
    foreach ($xf->attributes['societe']['label'] as $k => $lbl) {
        $xf_list[$k] = $k . ' — ' . $lbl;
    }
}

// -----------------------------------------------------------------------
// Affichage
// -----------------------------------------------------------------------
llxHeader('', 'CreditGuard – Configuration');

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">'
    . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre('CreditGuard – Configuration', $linkback, 'title_setup');
print dol_get_fiche_head();

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token"  value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield">Paramètre</td><td>Valeur</td></tr>';

// -- Extrafield Plafond --
print '<tr class="oddeven"><td>';
print '<b>Extrafield "Plafond de crédit"</b><br>';
print '<small>Nom de l\'extrafield de type <em>Prix</em> ou <em>Nombre</em> sur la fiche Tiers '
    . '(ex&nbsp;: <code>plafond_credit</code>)</small>';
print '</td><td>';
if (!empty($xf_list)) {
    print '<select class="flat minwidth250" onchange="document.getElementById(\'ef_plafond\').value=this.value">';
    print '<option value="">— choisir dans la liste —</option>';
    foreach ($xf_list as $k => $lbl) {
        $sel = ($k === $cur_plafond) ? ' selected' : '';
        print '<option value="' . dol_escape_htmltag($k) . '"' . $sel . '>'
            . dol_escape_htmltag($lbl) . '</option>';
    }
    print '</select><br>';
}
print '<input type="text" id="ef_plafond" name="CREDITGUARD_EXTRAFIELD_PLAFOND" class="flat minwidth200"'
    . ' value="' . dol_escape_htmltag($cur_plafond) . '" placeholder="plafond_credit">';
print '</td></tr>';

// -- Extrafield Déblocage --
print '<tr class="oddeven"><td>';
print '<b>Extrafield "Déblocage manuel"</b><br>';
print '<small>Nom de l\'extrafield de type <em>Checkbox</em> sur la fiche Tiers '
    . '(ex&nbsp;: <code>deblocage_manuel</code>). Laisser vide pour désactiver.</small>';
print '</td><td>';
if (!empty($xf_list)) {
    print '<select class="flat minwidth250" onchange="document.getElementById(\'ef_deblocage\').value=this.value">';
    print '<option value="">— aucun —</option>';
    foreach ($xf_list as $k => $lbl) {
        $sel = ($k === $cur_deblocage) ? ' selected' : '';
        print '<option value="' . dol_escape_htmltag($k) . '"' . $sel . '>'
            . dol_escape_htmltag($lbl) . '</option>';
    }
    print '</select><br>';
}
print '<input type="text" id="ef_deblocage" name="CREDITGUARD_EXTRAFIELD_DEBLOCAGE" class="flat minwidth200"'
    . ' value="' . dol_escape_htmltag($cur_deblocage) . '" placeholder="deblocage_manuel">';
print '</td></tr>';

// -- Seuil alerte --
print '<tr class="oddeven"><td>';
print '<b>Seuil d\'alerte (%)</b><br>';
print '<small>Pourcentage à partir duquel une alerte est envoyée au webhook (défaut&nbsp;: 80).</small>';
print '</td><td>';
print '<input type="number" name="CREDITGUARD_SEUIL_ALERTE" class="flat width75"'
    . ' min="1" max="99" value="' . (int) $cur_seuil . '"> %';
print '</td></tr>';

// -- URL Webhook --
print '<tr class="oddeven"><td>';
print '<b>URL du Webhook</b><br>';
print '<small>Endpoint HTTP(S) recevant les alertes JSON. Laisser vide pour désactiver.</small>';
print '</td><td>';
print '<input type="url" name="CREDITGUARD_WEBHOOK_URL" class="flat minwidth400"'
    . ' value="' . dol_escape_htmltag($cur_webhook) . '"'
    . ' placeholder="https://exemple.com/webhook/creditguard">';
print '</td></tr>';

print '</table><br>';
print '<div class="center"><input type="submit" class="button button-save" value="Enregistrer"></div>';
print '</form>';

// -----------------------------------------------------------------------
// Outil de test (calcul d'encours à la demande)
// -----------------------------------------------------------------------
print '<br><hr>';
print '<h3>Outil de test – Calculer l\'encours d\'un tiers</h3>';

$test_socid = (int) GETPOST('test_socid', 'int');
if ($test_socid > 0) {
    $t_encours   = creditguard_get_encours($db, $test_socid);
    $t_plafond   = creditguard_get_plafond($db, $test_socid, $cur_plafond);
    $t_deblocage = creditguard_is_deblocage_actif($db, $test_socid, $cur_deblocage);

    print '<div class="info" style="max-width:500px">';
    print '<b>Tiers #' . $test_socid . '</b><br>';
    print 'Encours calculé : <b>' . price($t_encours) . ' €</b><br>';
    print 'Plafond : <b>' . price($t_plafond) . ' €</b><br>';
    if ($t_plafond > 0) {
        $t_ratio = round(($t_encours / $t_plafond) * 100, 1);
        $color   = $t_ratio >= 100 ? 'red' : ($t_ratio >= $cur_seuil ? 'orange' : 'green');
        print 'Ratio : <b style="color:' . $color . '">' . $t_ratio . ' %</b><br>';
    }
    print 'Déblocage manuel : <b>' . ($t_deblocage ? 'OUI' : 'NON') . '</b>';
    print '</div>';
}

print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" style="margin-top:10px">';
print 'ID du tiers : <input type="number" name="test_socid" class="flat width75" min="1"'
    . ' value="' . $test_socid . '">';
print ' <input type="submit" class="button" value="Calculer">';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
