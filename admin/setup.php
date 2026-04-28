<?php
/* Copyright (C) 2024 Dolico Tech - www.dolico.tech
 * Développeurs : BADOLO Edadjain <info@dolico.tech> / ZOUNGRANA Joel
 * Support      : info@dolico.tech | Tel : +22671442089
 *
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

$res = 0;
if (!$res && file_exists('../main.inc.php'))       { $res = @include '../main.inc.php'; }
if (!$res && file_exists('../../main.inc.php'))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
dol_include_once('/creditguard/lib/creditguard.lib.php');

if (!$user->admin) { accessforbidden(); }

$langs->loadLangs(array('admin', 'creditguard@creditguard'));

$action = GETPOST('action', 'aZ09');
$error  = 0;

// -----------------------------------------------------------------------
// Traitement POST
// -----------------------------------------------------------------------
if ($action === 'update') {
    $p_plafond   = GETPOST('CREDITGUARD_EXTRAFIELD_PLAFOND',   'alpha');
    $p_deblocage = GETPOST('CREDITGUARD_EXTRAFIELD_DEBLOCAGE', 'alpha');
    $p_webhook   = GETPOST('CREDITGUARD_WEBHOOK_URL',          'alpha');
    $p_seuil     = (int) GETPOST('CREDITGUARD_SEUIL_ALERTE',   'int');

    if (empty($p_plafond)) {
        setEventMessages($langs->trans('CreditGuardErrorPlafondRequired'), null, 'errors');
        $error++;
    }
    if (!empty($p_webhook) && !filter_var($p_webhook, FILTER_VALIDATE_URL)) {
        setEventMessages($langs->trans('CreditGuardErrorWebhookInvalid'), null, 'errors');
        $error++;
    }
    if ($p_seuil < 1 || $p_seuil > 99) {
        setEventMessages($langs->trans('CreditGuardErrorSeuilRange'), null, 'errors');
        $error++;
    }

    if (!$error) {
        dolibarr_set_const($db, 'CREDITGUARD_EXTRAFIELD_PLAFOND',   $p_plafond,   'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_EXTRAFIELD_DEBLOCAGE', $p_deblocage, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_WEBHOOK_URL',          $p_webhook,   'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'CREDITGUARD_SEUIL_ALERTE',         $p_seuil,     'chaine', 0, '', $conf->entity);
        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    }
}

// -----------------------------------------------------------------------
// Valeurs courantes
// -----------------------------------------------------------------------
$cur_plafond   = $conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND   ?? 'plafond_credit';
$cur_deblocage = $conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE ?? 'deblocage_manuel';
$cur_webhook   = $conf->global->CREDITGUARD_WEBHOOK_URL          ?? '';
$cur_seuil     = (int) ($conf->global->CREDITGUARD_SEUIL_ALERTE  ?? 80);

// Extrafields disponibles sur la fiche Tiers
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
llxHeader('', $langs->trans('CreditGuardSetup'));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">'
    . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre(
    '<img src="' . dol_buildpath('/creditguard/img/logo_creditguard.png', 1) . '" height="28" style="vertical-align:middle;margin-right:8px" onerror="this.style.display=\'none\'">'
    . 'CreditGuard v1.0.0',
    $linkback,
    'title_setup'
);

// Onglets
$head = creditguard_admin_prepare_head();
print dol_get_fiche_head($head, 'setup', '', -1, '');

// Bandeau info éditeur
print '<div style="background:#f0f4ff;border-left:4px solid #3c6fba;padding:8px 14px;margin-bottom:18px;border-radius:3px;font-size:0.9em">';
print '<b>Dolico Tech</b> &nbsp;|&nbsp; <a href="https://www.dolico.tech" target="_blank">www.dolico.tech</a>';
print ' &nbsp;|&nbsp; <a href="mailto:info@dolico.tech">info@dolico.tech</a>';
print ' &nbsp;|&nbsp; +226 71 44 20 89';
print '</div>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token"  value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield">' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '</tr>';

// -- Extrafield Plafond --
print '<tr class="oddeven"><td>';
print '<b>' . $langs->trans('CreditGuardParamPlafond') . '</b></td><td>';
if (!empty($xf_list)) {
    print '<select class="flat minwidth250" onchange="document.getElementById(\'ef_plafond\').value=this.value">';
    print '<option value="">— ' . $langs->trans('SelectExtrafield') . ' —</option>';
    foreach ($xf_list as $k => $lbl) {
        $sel = ($k === $cur_plafond) ? ' selected' : '';
        print '<option value="' . dol_escape_htmltag($k) . '"' . $sel . '>' . dol_escape_htmltag($lbl) . '</option>';
    }
    print '</select><br>';
}
print '<input type="text" id="ef_plafond" name="CREDITGUARD_EXTRAFIELD_PLAFOND" class="flat minwidth200"'
    . ' value="' . dol_escape_htmltag($cur_plafond) . '" placeholder="plafond_credit">';
print '</td><td class="opacitymedium small">' . $langs->trans('CreditGuardParamPlafondHelp') . '</td></tr>';

// -- Extrafield Déblocage --
print '<tr class="oddeven"><td>';
print '<b>' . $langs->trans('CreditGuardParamDeblocage') . '</b></td><td>';
if (!empty($xf_list)) {
    print '<select class="flat minwidth250" onchange="document.getElementById(\'ef_deblocage\').value=this.value">';
    print '<option value="">— ' . $langs->trans('None') . ' —</option>';
    foreach ($xf_list as $k => $lbl) {
        $sel = ($k === $cur_deblocage) ? ' selected' : '';
        print '<option value="' . dol_escape_htmltag($k) . '"' . $sel . '>' . dol_escape_htmltag($lbl) . '</option>';
    }
    print '</select><br>';
}
print '<input type="text" id="ef_deblocage" name="CREDITGUARD_EXTRAFIELD_DEBLOCAGE" class="flat minwidth200"'
    . ' value="' . dol_escape_htmltag($cur_deblocage) . '" placeholder="deblocage_manuel">';
print '</td><td class="opacitymedium small">' . $langs->trans('CreditGuardParamDeblocageHelp') . '</td></tr>';

// -- Seuil alerte --
print '<tr class="oddeven"><td><b>' . $langs->trans('CreditGuardParamSeuil') . '</b></td><td>';
print '<input type="number" name="CREDITGUARD_SEUIL_ALERTE" class="flat width75" min="1" max="99" value="' . $cur_seuil . '"> %';
print '</td><td class="opacitymedium small">' . $langs->trans('CreditGuardParamSeuilHelp') . '</td></tr>';

// -- URL Webhook --
print '<tr class="oddeven"><td><b>' . $langs->trans('CreditGuardParamWebhook') . '</b></td><td>';
print '<input type="url" name="CREDITGUARD_WEBHOOK_URL" class="flat minwidth400"'
    . ' value="' . dol_escape_htmltag($cur_webhook) . '"'
    . ' placeholder="https://exemple.com/webhook/creditguard">';
print '</td><td class="opacitymedium small">' . $langs->trans('CreditGuardParamWebhookHelp') . '</td></tr>';

print '</table><br>';
print '<div class="center"><input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// -----------------------------------------------------------------------
// Aperçu de la logique (tableau récapitulatif)
// -----------------------------------------------------------------------
print '<br><div class="info" style="max-width:620px">';
print '<b>' . $langs->trans('CreditGuardLogicSummary') . '</b><br><br>';
print '<table class="nobordernopadding" style="width:100%">';
print '<tr style="background:#e8f0fe"><th style="padding:4px 8px;text-align:left">' . $langs->trans('Condition') . '</th><th style="padding:4px 8px;text-align:left">' . $langs->trans('Action') . '</th></tr>';
print '<tr><td style="padding:4px 8px">Plafond = 0 ou non défini</td><td style="padding:4px 8px">✅ ' . $langs->trans('CreditGuardActionPass') . '</td></tr>';
print '<tr style="background:#fffde7"><td style="padding:4px 8px">Encours ≥ ' . $cur_seuil . '% du plafond</td><td style="padding:4px 8px">⚠️ ' . $langs->trans('CreditGuardActionAlert') . '</td></tr>';
print '<tr style="background:#fce8e6"><td style="padding:4px 8px">Encours ≥ 100% + déblocage = NON</td><td style="padding:4px 8px">❌ ' . $langs->trans('CreditGuardActionBlock') . '</td></tr>';
print '<tr style="background:#e8f5e9"><td style="padding:4px 8px">Encours ≥ 100% + déblocage = OUI</td><td style="padding:4px 8px">✅ ' . $langs->trans('CreditGuardActionForce') . '</td></tr>';
print '</table></div>';

// -----------------------------------------------------------------------
// Outil de test
// -----------------------------------------------------------------------
print '<br><h3>' . $langs->trans('CreditGuardTestTool') . '</h3>';
$test_socid = (int) GETPOST('test_socid', 'int');
if ($test_socid > 0) {
    $t_encours   = creditguard_get_encours($db, $test_socid);
    $t_plafond   = creditguard_get_plafond($db, $test_socid, $cur_plafond);
    $t_deblocage = creditguard_is_deblocage_actif($db, $test_socid, $cur_deblocage);
    print '<div class="info" style="max-width:420px">';
    print '<b>' . $langs->trans('ThirdParty') . ' #' . $test_socid . '</b><br>';
    print $langs->trans('CreditGuardEncours') . ' : <b>' . price($t_encours) . ' €</b><br>';
    print $langs->trans('CreditGuardPlafond') . ' : <b>' . price($t_plafond) . ' €</b><br>';
    if ($t_plafond > 0) {
        $t_ratio = round(($t_encours / $t_plafond) * 100, 1);
        $color   = $t_ratio >= 100 ? '#d32f2f' : ($t_ratio >= $cur_seuil ? '#f57c00' : '#388e3c');
        print 'Ratio : <b style="color:' . $color . '">' . $t_ratio . ' %</b><br>';
    }
    print $langs->trans('CreditGuardDeblocage') . ' : <b>' . ($t_deblocage ? $langs->trans('Yes') : $langs->trans('No')) . '</b>';
    print '</div>';
}
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
print $langs->trans('IDThirdParty') . ' : ';
print '<input type="number" name="test_socid" class="flat width75" min="1" value="' . $test_socid . '">';
print ' <input type="submit" class="button" value="' . $langs->trans('Compute') . '">';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
