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

llxHeader('', $langs->trans('CreditGuardManuel'));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">'
    . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre(
    '<img src="' . dol_buildpath('/creditguard/img/logo_creditguard.png', 1) . '" height="28" style="vertical-align:middle;margin-right:8px" onerror="this.style.display=\'none\'">'
    . 'CreditGuard v1.0.0 — ' . $langs->trans('CreditGuardManuel'),
    $linkback, 'title_setup'
);

$head = creditguard_admin_prepare_head();
print dol_get_fiche_head($head, 'manuel', '', -1, '');

$lang_code = $langs->defaultlang;
$is_en     = (substr($lang_code, 0, 2) === 'en');

// -----------------------------------------------------------------------
// Styles inline pour le manuel
// -----------------------------------------------------------------------
print '<style>
.cg-step      { background:#f8f9fa; border-left:4px solid #3c6fba; padding:14px 18px; margin:16px 0; border-radius:4px; }
.cg-step h3   { margin:0 0 8px 0; color:#3c6fba; font-size:1.05em; }
.cg-step p    { margin:4px 0; }
.cg-badge     { display:inline-block; background:#3c6fba; color:#fff; border-radius:3px; padding:2px 8px; font-size:0.82em; margin:0 2px; }
.cg-badge.ok  { background:#388e3c; }
.cg-badge.warn{ background:#f57c00; }
.cg-badge.err { background:#d32f2f; }
.cg-schema    { background:#1e1e2e; color:#cdd6f4; font-family:monospace; padding:16px; border-radius:6px; font-size:0.88em; line-height:1.7; overflow-x:auto; }
.cg-screen    { border:2px solid #ccc; border-radius:6px; padding:14px; margin:10px 0; background:#fff; font-size:0.92em; }
.cg-screen .bar { background:#3c6fba; color:#fff; padding:4px 10px; border-radius:3px 3px 0 0; margin:-14px -14px 10px -14px; font-size:0.85em; }
.cg-note      { background:#fff8e1; border:1px solid #ffe082; padding:8px 12px; border-radius:4px; margin:8px 0; font-size:0.9em; }
table.cg-table { border-collapse:collapse; width:100%; margin:10px 0; }
table.cg-table th { background:#3c6fba; color:#fff; padding:6px 10px; text-align:left; }
table.cg-table td { border:1px solid #ddd; padding:6px 10px; }
table.cg-table tr:nth-child(even) td { background:#f5f5f5; }
</style>';

if ($is_en) {
    // ====================================================================
    // ENGLISH VERSION
    // ====================================================================
    print '<h2>📖 CreditGuard – User Manual</h2>';
    print '<p>CreditGuard automatically checks the customer credit exposure against a defined limit when validating invoices or orders. It alerts or blocks depending on the configured threshold.</p>';

    // Step 1
    print '<div class="cg-step"><h3>Step 1 — Create the extra fields on the Third-Party record</h3>';
    print '<p>Go to <b>Administration → System → Extra fields → Third-Parties (societe)</b></p>';
    print '<p>Create the following two extra fields:</p>';
    print '<div class="cg-screen"><div class="bar">Administration &gt; Extra fields &gt; Third-Parties</div>';
    print '<table class="cg-table">';
    print '<tr><th>Field code</th><th>Label</th><th>Type</th><th>Role</th></tr>';
    print '<tr><td><code>plafond_credit</code></td><td>Credit limit</td><td><span class="cg-badge">Price / Double</span></td><td>Stores the maximum allowed credit amount (e.g. 5000.00)</td></tr>';
    print '<tr><td><code>deblocage_manuel</code></td><td>Manual override</td><td><span class="cg-badge">Checkbox</span></td><td>When checked, allows validation even if the limit is exceeded</td></tr>';
    print '</table></div>';
    print '<div class="cg-note">⚠️ The field codes must match exactly what you enter in the CreditGuard settings.</div>';
    print '</div>';

    // Step 2
    print '<div class="cg-step"><h3>Step 2 — Configure the CreditGuard module</h3>';
    print '<p>Go to <b>Administration → Modules → CreditGuard ⚙️ → Configuration tab</b></p>';
    print '<div class="cg-screen"><div class="bar">CreditGuard — Configuration</div>';
    print '<table class="cg-table">';
    print '<tr><th>Parameter</th><th>Example value</th><th>Description</th></tr>';
    print '<tr><td>Credit limit extrafield</td><td><code>plafond_credit</code></td><td>Name of the numeric extra field on the third-party</td></tr>';
    print '<tr><td>Manual override extrafield</td><td><code>deblocage_manuel</code></td><td>Name of the checkbox extra field (leave blank to disable)</td></tr>';
    print '<tr><td>Alert threshold (%)</td><td><code>80</code></td><td>% of the limit that triggers a non-blocking alert</td></tr>';
    print '<tr><td>Webhook URL</td><td><code>https://myserver.com/hook</code></td><td>HTTP endpoint receiving JSON alerts (optional)</td></tr>';
    print '</table></div>';
    print '</div>';

    // Step 3
    print '<div class="cg-step"><h3>Step 3 — Set the credit limit on a customer</h3>';
    print '<p>Go to <b>Third-Parties → [Customer name] → Edit</b></p>';
    print '<div class="cg-screen"><div class="bar">Third-Party — Edit</div>';
    print '<p>In the <b>Extra fields</b> section, fill in:</p>';
    print '<ul><li><code>Credit limit</code> : e.g. <b>5 000.00</b></li>';
    print '<li><code>Manual override</code> : leave <b>unchecked</b> (normal operation)</li></ul>';
    print '</div>';
    print '</div>';

    // Step 4 – Behavior
    print '<div class="cg-step"><h3>Step 4 — Understand the behavior when validating</h3>';
    print '<p>Each time a <b>customer invoice</b> or <b>customer order</b> is validated, CreditGuard computes:</p>';
    print '<div class="cg-schema">';
    print 'EXPOSURE = unpaid invoices (VAT incl., remaining balance)<br>';
    print '         + validated orders not yet invoiced (VAT incl.)<br><br>';
    print 'RATIO    = EXPOSURE / CREDIT LIMIT × 100';
    print '</div>';
    print '<br>';
    print '<table class="cg-table">';
    print '<tr><th>Condition</th><th>Result</th></tr>';
    print '<tr><td>Credit limit = 0 or not set</td><td><span class="cg-badge ok">✅ No check — validation allowed</span></td></tr>';
    print '<tr><td>Ratio ≥ alert threshold (e.g. 80%)</td><td><span class="cg-badge warn">⚠️ Warning on screen + Webhook notification</span></td></tr>';
    print '<tr><td>Ratio ≥ 100% AND override = NO</td><td><span class="cg-badge err">❌ Validation BLOCKED</span></td></tr>';
    print '<tr><td>Ratio ≥ 100% AND override = YES</td><td><span class="cg-badge ok">✅ Forced through + LOG_WARNING written</span></td></tr>';
    print '</table></div>';

    // Step 5 – Webhook
    print '<div class="cg-step"><h3>Step 5 — Webhook JSON format</h3>';
    print '<p>When an alert or block occurs, CreditGuard sends a <b>POST</b> request with this JSON body:</p>';
    print '<div class="cg-schema">{<br>';
    print '&nbsp;&nbsp;"event"       : "BILL_VALIDATE",<br>';
    print '&nbsp;&nbsp;"statut"      : "ALERTE",   <span style="color:#a6e3a1">// ALERTE | BLOQUE | FORCE_DEBLOCAGE</span><br>';
    print '&nbsp;&nbsp;"client"      : "Société Exemple",<br>';
    print '&nbsp;&nbsp;"socid"       : 42,<br>';
    print '&nbsp;&nbsp;"encours"     : 4250.00,<br>';
    print '&nbsp;&nbsp;"plafond"     : 5000.00,<br>';
    print '&nbsp;&nbsp;"pourcentage" : 85.0,<br>';
    print '&nbsp;&nbsp;"timestamp"   : "2024-06-01T14:32:00+00:00"<br>}';
    print '</div></div>';

} else {
    // ====================================================================
    // VERSION FRANÇAISE
    // ====================================================================
    print '<h2>📖 CreditGuard – Manuel d\'utilisation</h2>';
    print '<p>CreditGuard vérifie automatiquement l\'encours client par rapport à un plafond défini lors de la validation des factures et commandes. Il alerte ou bloque selon le seuil configuré.</p>';

    // Étape 1
    print '<div class="cg-step"><h3>Étape 1 — Créer les champs extra (extrafields) sur la fiche Tiers</h3>';
    print '<p>Accédez à <b>Administration → Système → Champs extra → Tiers (societe)</b></p>';
    print '<p>Créez les deux champs suivants :</p>';
    print '<div class="cg-screen"><div class="bar">Administration &gt; Champs extra &gt; Tiers</div>';
    print '<table class="cg-table">';
    print '<tr><th>Code du champ</th><th>Libellé</th><th>Type</th><th>Rôle</th></tr>';
    print '<tr><td><code>plafond_credit</code></td><td>Plafond de crédit</td><td><span class="cg-badge">Prix / Nombre décimal</span></td><td>Montant maximum autorisé (ex : 5000.00)</td></tr>';
    print '<tr><td><code>deblocage_manuel</code></td><td>Déblocage manuel</td><td><span class="cg-badge">Checkbox</span></td><td>Si coché, autorise la validation même en dépassement</td></tr>';
    print '</table></div>';
    print '<div class="cg-note">⚠️ Les codes de champs doivent correspondre exactement à ce que vous renseignerez dans la configuration CreditGuard.</div>';
    print '</div>';

    // Étape 2
    print '<div class="cg-step"><h3>Étape 2 — Configurer le module CreditGuard</h3>';
    print '<p>Accédez à <b>Administration → Modules → CreditGuard ⚙️ → Onglet Configuration</b></p>';
    print '<div class="cg-screen"><div class="bar">CreditGuard — Configuration</div>';
    print '<table class="cg-table">';
    print '<tr><th>Paramètre</th><th>Exemple</th><th>Description</th></tr>';
    print '<tr><td>Extrafield "Plafond de crédit"</td><td><code>plafond_credit</code></td><td>Nom du champ numérique sur la fiche Tiers</td></tr>';
    print '<tr><td>Extrafield "Déblocage manuel"</td><td><code>deblocage_manuel</code></td><td>Nom du champ checkbox (laisser vide pour désactiver)</td></tr>';
    print '<tr><td>Seuil d\'alerte (%)</td><td><code>80</code></td><td>% du plafond déclenchant l\'alerte non bloquante</td></tr>';
    print '<tr><td>URL du Webhook</td><td><code>https://monserveur.com/hook</code></td><td>Endpoint recevant les alertes JSON (facultatif)</td></tr>';
    print '</table></div>';
    print '</div>';

    // Étape 3
    print '<div class="cg-step"><h3>Étape 3 — Définir le plafond sur la fiche client</h3>';
    print '<p>Accédez à <b>Tiers → [Nom du client] → Modifier</b></p>';
    print '<div class="cg-screen"><div class="bar">Fiche Tiers — Modification</div>';
    print '<p>Dans la section <b>Informations complémentaires</b>, renseigner :</p>';
    print '<ul><li><code>Plafond de crédit</code> : ex. <b>5 000,00</b></li>';
    print '<li><code>Déblocage manuel</code> : laisser <b>décoché</b> (fonctionnement normal)</li></ul>';
    print '</div>';
    print '</div>';

    // Étape 4 – Comportement
    print '<div class="cg-step"><h3>Étape 4 — Comprendre le comportement à la validation</h3>';
    print '<p>À chaque validation d\'une <b>facture client</b> ou d\'une <b>commande client</b>, CreditGuard calcule :</p>';
    print '<div class="cg-schema">';
    print 'ENCOURS = Σ factures impayées TTC (solde restant dû)<br>';
    print '        + Σ commandes validées non facturées TTC<br><br>';
    print 'RATIO   = ENCOURS / PLAFOND × 100';
    print '</div>';
    print '<br>';
    print '<table class="cg-table">';
    print '<tr><th>Condition</th><th>Résultat</th></tr>';
    print '<tr><td>Plafond = 0 ou non défini</td><td><span class="cg-badge ok">✅ Aucun contrôle — validation autorisée</span></td></tr>';
    print '<tr><td>Ratio ≥ seuil d\'alerte (ex : 80%)</td><td><span class="cg-badge warn">⚠️ Message d\'avertissement + Notification Webhook</span></td></tr>';
    print '<tr><td>Ratio ≥ 100% ET déblocage = NON</td><td><span class="cg-badge err">❌ Validation BLOQUÉE</span></td></tr>';
    print '<tr><td>Ratio ≥ 100% ET déblocage = OUI</td><td><span class="cg-badge ok">✅ Validé en force + log d\'audit</span></td></tr>';
    print '</table></div>';

    // Étape 5 – Webhook
    print '<div class="cg-step"><h3>Étape 5 — Format du Webhook JSON</h3>';
    print '<p>Lors d\'une alerte ou d\'un blocage, CreditGuard envoie une requête <b>POST</b> avec ce corps JSON :</p>';
    print '<div class="cg-schema">{<br>';
    print '&nbsp;&nbsp;"event"       : "BILL_VALIDATE",<br>';
    print '&nbsp;&nbsp;"statut"      : "ALERTE",   <span style="color:#a6e3a1">// ALERTE | BLOQUE | FORCE_DEBLOCAGE</span><br>';
    print '&nbsp;&nbsp;"client"      : "Société Exemple SARL",<br>';
    print '&nbsp;&nbsp;"socid"       : 42,<br>';
    print '&nbsp;&nbsp;"encours"     : 4250.00,<br>';
    print '&nbsp;&nbsp;"plafond"     : 5000.00,<br>';
    print '&nbsp;&nbsp;"pourcentage" : 85.0,<br>';
    print '&nbsp;&nbsp;"timestamp"   : "2024-06-01T14:32:00+00:00"<br>}';
    print '</div></div>';

    // FAQ
    print '<div class="cg-step"><h3>❓ Questions fréquentes</h3>';
    print '<p><b>Q : Le module bloque-t-il toujours si le plafond est dépassé ?</b><br>';
    print 'R : Non. Si le champ <code>deblocage_manuel</code> est coché sur la fiche tiers, la validation est autorisée. Un log d\'avertissement est écrit et le webhook est notifié.</p>';
    print '<p><b>Q : Que se passe-t-il si le champ extrafield n\'existe pas sur la fiche ?</b><br>';
    print 'R : Le plafond sera considéré comme 0, et aucun contrôle ne sera effectué (fail-open).</p>';
    print '<p><b>Q : L\'encours est-il calculé avec ou sans TVA ?</b><br>';
    print 'R : Toujours <b>TTC</b> (total_ttc), conformément à la pratique commerciale standard.</p>';
    print '<p><b>Q : Le webhook est-il obligatoire ?</b><br>';
    print 'R : Non. Si le champ URL est vide, aucune requête HTTP ne sera envoyée. Le module fonctionne normalement sans webhook.</p>';
    print '</div>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
