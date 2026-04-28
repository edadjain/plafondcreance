<?php
/* Copyright (C) 2024 Dolico Tech - www.dolico.tech */

$res = 0;
if (!$res && file_exists('../main.inc.php'))       { $res = @include '../main.inc.php'; }
if (!$res && file_exists('../../main.inc.php'))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists('../../../main.inc.php')) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Include of main fails'); }

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
dol_include_once('/creditguard/lib/creditguard.lib.php');

if (!$user->admin) { accessforbidden(); }

$langs->loadLangs(array('admin', 'creditguard@creditguard'));

llxHeader('', $langs->trans('About') . ' — CreditGuard');

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">'
    . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre(
    '<img src="' . dol_buildpath('/creditguard/img/logo_creditguard.png', 1) . '" height="28" style="vertical-align:middle;margin-right:8px" onerror="this.style.display=\'none\'">'
    . 'CreditGuard v1.0.0 — ' . $langs->trans('About'),
    $linkback, 'title_setup'
);

$head = creditguard_admin_prepare_head();
print dol_get_fiche_head($head, 'about', '', -1, '');

$lang_code = $langs->defaultlang;
$is_en     = (substr($lang_code, 0, 2) === 'en');

// -----------------------------------------------------------------------
// Styles
// -----------------------------------------------------------------------
print '<style>
.ab-section    { margin:22px 0; }
.ab-section h2 { font-size:1.2em; color:#1a1a2e; border-bottom:2px solid #3c6fba; padding-bottom:5px; }
.ab-badge      { display:inline-block; border-radius:4px; padding:3px 10px; font-size:0.82em; font-weight:bold; color:#fff; margin:2px; }
.ab-blue  { background:#3c6fba; }
.ab-green { background:#388e3c; }
.ab-gray  { background:#555; }
.ab-orange{ background:#e65100; }
.ab-card  { border:1px solid #e0e0e0; border-radius:8px; padding:16px 20px; margin:10px 0; background:#fafafa; }
.ab-card h3 { margin:0 0 6px 0; font-size:1em; }
.ab-contact a { color:#3c6fba; text-decoration:none; }
.ab-contact a:hover { text-decoration:underline; }
.ab-team  { display:flex; flex-wrap:wrap; gap:14px; margin-top:10px; }
.ab-member{ background:#e8f0fe; border-radius:8px; padding:12px 18px; min-width:200px; }
.ab-member b { display:block; font-size:1em; color:#1a1a2e; }
.ab-member span { font-size:0.85em; color:#555; }
</style>';

// -----------------------------------------------------------------------
// HEADER BADGES
// -----------------------------------------------------------------------
print '<div class="ab-section">';
print '<span class="ab-badge ab-blue">🛡️ CreditGuard v1.0.0</span>';
print '<span class="ab-badge ab-green">Dolibarr v15.0+</span>';
print '<span class="ab-badge ab-gray">PHP &gt;= 7.0</span>';
print '<span class="ab-badge ab-orange">GPL v3+</span>';
print '</div>';

if ($is_en) {
    // ====================================================================
    // ENGLISH
    // ====================================================================
    print '<div class="ab-section">';
    print '<h2>CREDITGUARD</h2>';
    print '<p>CreditGuard is a Dolibarr module for <b>real-time customer credit exposure monitoring</b>. '
        . 'It automatically checks the credit limit of each customer when invoices or orders are validated, '
        . 'and alerts or blocks the action depending on the configured threshold.</p>';
    print '<p>This module is compatible with <b>Dolibarr v15.0 and above</b>.</p>';
    print '</div>';

    print '<div class="ab-section">';
    print '<h2>KEY FEATURES</h2>';
    print '<div class="ab-card">';
    print '<ul>';
    print '<li>✅ Automatic exposure calculation: unpaid invoices (VAT incl.) + unvoiced orders (VAT incl.)</li>';
    print '<li>⚠️ Configurable alert threshold (default 80%) with webhook notification</li>';
    print '<li>❌ Automatic blocking when 100% limit is exceeded (optional manual override)</li>';
    print '<li>🔔 JSON webhook notifications for all alert levels</li>';
    print '<li>🔧 Admin configuration page with live exposure testing tool</li>';
    print '<li>🌍 French &amp; English language support</li>';
    print '</ul>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>INSTALL CREDITGUARD</h2>';
    print '<div class="ab-card">';
    print '<ol>';
    print '<li>Upload the <code>creditguard/</code> folder to <code>htdocs/custom/creditguard/</code></li>';
    print '<li>Go to <b>Administration → Modules</b> and activate <b>CreditGuard</b></li>';
    print '<li>Click ⚙️ to open the configuration page</li>';
    print '<li>Create the extra fields <code>plafond_credit</code> and <code>deblocage_manuel</code> on Third-Parties</li>';
    print '<li>Set a credit limit on a customer record and test with a validation</li>';
    print '</ol>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>LICENSE</h2>';
    print '<div class="ab-card">';
    print '<p><span class="ab-badge ab-orange">GPL V3+</span></p>';
    print '<p>CreditGuard is distributed under the terms of the <b>GNU General Public License v3</b> or higher.</p>';
    print '<p>You are free to use, modify and redistribute this software under the same license terms.</p>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>CONTACT &amp; SUPPORT</h2>';
    print '<div class="ab-card ab-contact">';
    print '<p>🌐 Website : <a href="https://www.dolico.tech" target="_blank">www.dolico.tech</a></p>';
    print '<p>📧 Email : <a href="mailto:info@dolico.tech">info@dolico.tech</a></p>';
    print '<p>📞 Phone : <a href="tel:+22671442089">+226 71 44 20 89</a></p>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>DEVELOPMENT TEAM</h2>';
    print '<div class="ab-team">';
    print '<div class="ab-member"><b>BADOLO Edadjain</b><span>Lead Developer<br><a href="mailto:info@dolico.tech" style="color:#3c6fba">info@dolico.tech</a></span></div>';
    print '<div class="ab-member"><b>ZOUNGRANA Joel</b><span>Developer<br>Dolico Tech</span></div>';
    print '</div></div>';

} else {
    // ====================================================================
    // FRANÇAIS
    // ====================================================================
    print '<div class="ab-section">';
    print '<h2>CREDITGUARD</h2>';
    print '<p>CreditGuard est un module Dolibarr de <b>surveillance en temps réel de l\'encours client</b>. '
        . 'Il contrôle automatiquement le plafond de crédit de chaque client lors de la validation des factures '
        . 'ou des commandes, et alerte ou bloque selon les seuils configurés.</p>';
    print '<p>Ce module est compatible avec <b>Dolibarr v15.0 et supérieur</b>.</p>';
    print '</div>';

    print '<div class="ab-section">';
    print '<h2>FONCTIONNALITÉS CLÉS</h2>';
    print '<div class="ab-card"><ul>';
    print '<li>✅ Calcul automatique de l\'encours : factures impayées TTC + commandes non facturées TTC</li>';
    print '<li>⚠️ Seuil d\'alerte configurable (défaut 80%) avec notification webhook</li>';
    print '<li>❌ Blocage automatique au dépassement de 100% du plafond (déblocage manuel optionnel)</li>';
    print '<li>🔔 Notifications JSON webhook pour tous les niveaux d\'alerte</li>';
    print '<li>🔧 Page de configuration admin avec outil de test d\'encours en direct</li>';
    print '<li>🌍 Support français &amp; anglais</li>';
    print '</ul></div></div>';

    print '<div class="ab-section">';
    print '<h2>INSTALLATION DE CREDITGUARD</h2>';
    print '<div class="ab-card"><ol>';
    print '<li>Uploader le dossier <code>creditguard/</code> dans <code>htdocs/custom/creditguard/</code> via FTP</li>';
    print '<li>Aller dans <b>Administration → Modules</b> et activer <b>CreditGuard</b></li>';
    print '<li>Cliquer sur ⚙️ pour ouvrir la page de configuration</li>';
    print '<li>Créer les champs extra <code>plafond_credit</code> et <code>deblocage_manuel</code> sur les Tiers</li>';
    print '<li>Renseigner un plafond sur une fiche client et tester en validant une facture</li>';
    print '</ol></div></div>';

    print '<div class="ab-section">';
    print '<h2>LICENCE</h2>';
    print '<div class="ab-card">';
    print '<p><span class="ab-badge ab-orange">GPL V3+</span></p>';
    print '<p>CreditGuard est distribué selon les termes de la <b>Licence Publique Générale GNU v3</b> ou supérieure.</p>';
    print '<p>Vous êtes libre d\'utiliser, modifier et redistribuer ce logiciel selon les mêmes termes de licence.</p>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>CONTACT &amp; SUPPORT</h2>';
    print '<div class="ab-card ab-contact">';
    print '<p>🌐 Site web : <a href="https://www.dolico.tech" target="_blank">www.dolico.tech</a></p>';
    print '<p>📧 Email : <a href="mailto:info@dolico.tech">info@dolico.tech</a></p>';
    print '<p>📞 Téléphone : <a href="tel:+22671442089">+226 71 44 20 89</a></p>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>ÉQUIPE DE DÉVELOPPEMENT</h2>';
    print '<div class="ab-team">';
    print '<div class="ab-member"><b>BADOLO Edadjain</b><span>Développeur principal<br><a href="mailto:info@dolico.tech" style="color:#3c6fba">info@dolico.tech</a></span></div>';
    print '<div class="ab-member"><b>ZOUNGRANA Joel</b><span>Développeur<br>Dolico Tech</span></div>';
    print '</div></div>';

    print '<div class="ab-section">';
    print '<h2>AUTRES MODULES</h2>';
    print '<div class="ab-card">';
    print '<p>Découvrez tous nos modules Dolibarr sur <a href="https://www.dolico.tech" target="_blank">www.dolico.tech</a></p>';
    print '</div></div>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
