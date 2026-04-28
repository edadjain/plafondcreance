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
 * \file    lib/creditguard.lib.php
 * \ingroup creditguard
 * \brief   Fonctions métier CreditGuard
 */

/**
 * Calcule l'encours total d'un tiers :
 *   = Somme des factures clients impayées TTC (solde restant dû)
 *   + Somme des commandes clients validées non facturées TTC
 *
 * @param  DoliDB  $db     Objet base de données Dolibarr
 * @param  int     $socid  Identifiant du tiers (societe.rowid)
 * @return float           Encours en devise, ou -1 en cas d'erreur SQL
 */
function creditguard_get_encours($db, $socid)
{
    $socid = (int) $socid;
    if ($socid <= 0) {
        return 0;
    }

    $encours = 0.0;

    // ------------------------------------------------------------------
    // 1. Factures clients impayées : solde restant dû exact
    //    Statuts : 1 = validée non payée, 2 = partiellement payée
    //    Types   : 0 = standard, 1 = avoir déduit, 3 = acompte
    //    On exclut les avoirs (type 2) qui viendraient en déduction
    // ------------------------------------------------------------------
    $sql = "SELECT SUM(f.total_ttc - COALESCE(p.montant_paye, 0)) AS impaye";
    $sql .= " FROM " . MAIN_DB_PREFIX . "facture AS f";
    $sql .= " LEFT JOIN (";
    $sql .= "   SELECT fk_facture, SUM(amount) AS montant_paye";
    $sql .= "   FROM " . MAIN_DB_PREFIX . "paiement_facture";
    $sql .= "   GROUP BY fk_facture";
    $sql .= " ) AS p ON p.fk_facture = f.rowid";
    $sql .= " WHERE f.fk_soc = " . $socid;
    $sql .= " AND f.entity IN (" . getEntity('invoice') . ")";
    $sql .= " AND f.fk_statut IN (1, 2)";
    $sql .= " AND f.paye = 0";
    $sql .= " AND f.type NOT IN (2)"; // Exclure avoirs

    $res = $db->query($sql);
    if (!$res) {
        dol_syslog('CreditGuard::creditguard_get_encours factures KO ' . $db->lasterror(), LOG_ERR);
        return -1;
    }
    $row = $db->fetch_object($res);
    $encours += (float) ($row->impaye ?? 0);
    $db->free($res);

    // ------------------------------------------------------------------
    // 2. Commandes clients validées, non entièrement facturées
    //    Statuts commande : 1 = validée, 2 = en cours de livraison
    //    facture = 0 : non soldée (pas entièrement facturée)
    //    On déduit la part déjà facturée pour éviter le double-comptage
    // ------------------------------------------------------------------
    $sql2 = "SELECT SUM(c.total_ttc - COALESCE(fac.deja_facture, 0)) AS non_facture";
    $sql2 .= " FROM " . MAIN_DB_PREFIX . "commande AS c";
    $sql2 .= " LEFT JOIN (";
    $sql2 .= "   SELECT ee.fk_source AS fk_commande, SUM(f2.total_ttc) AS deja_facture";
    $sql2 .= "   FROM " . MAIN_DB_PREFIX . "element_element AS ee";
    $sql2 .= "   INNER JOIN " . MAIN_DB_PREFIX . "facture AS f2 ON f2.rowid = ee.fk_target";
    $sql2 .= "     AND ee.targettype = 'facture'";
    $sql2 .= "     AND f2.fk_statut IN (1, 2)";
    $sql2 .= "     AND f2.paye = 0";
    $sql2 .= "     AND f2.type NOT IN (2)";
    $sql2 .= "   WHERE ee.sourcetype = 'commande'";
    $sql2 .= "   GROUP BY ee.fk_source";
    $sql2 .= " ) AS fac ON fac.fk_commande = c.rowid";
    $sql2 .= " WHERE c.fk_soc = " . $socid;
    $sql2 .= " AND c.entity IN (" . getEntity('order') . ")";
    $sql2 .= " AND c.fk_statut IN (1, 2)";
    $sql2 .= " AND c.facture = 0";

    $res2 = $db->query($sql2);
    if (!$res2) {
        dol_syslog('CreditGuard::creditguard_get_encours commandes KO ' . $db->lasterror(), LOG_ERR);
        return -1;
    }
    $row2 = $db->fetch_object($res2);
    $non_facture = (float) ($row2->non_facture ?? 0);
    if ($non_facture > 0) {
        $encours += $non_facture;
    }
    $db->free($res2);

    return round($encours, 2);
}

/**
 * Lit le plafond de crédit d'un tiers depuis son extrafield.
 *
 * @param  DoliDB  $db          Objet base de données
 * @param  int     $socid       Identifiant du tiers
 * @param  string  $extrafield  Nom du champ extra (sans préfixe "options_")
 * @return float                Plafond (0 = pas de contrôle)
 */
function creditguard_get_plafond($db, $socid, $extrafield)
{
    $socid      = (int) $socid;
    $extrafield = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $extrafield);

    if ($socid <= 0 || $extrafield === '') {
        return 0;
    }

    $sql = "SELECT " . $extrafield . " AS plafond";
    $sql .= " FROM " . MAIN_DB_PREFIX . "societe_extrafields";
    $sql .= " WHERE fk_object = " . $socid;

    $res = $db->query($sql);
    if (!$res) {
        dol_syslog('CreditGuard::creditguard_get_plafond KO ' . $db->lasterror(), LOG_ERR);
        return 0;
    }
    $row = $db->fetch_object($res);
    $db->free($res);

    return (float) ($row->plafond ?? 0);
}

/**
 * Vérifie si le déblocage manuel est actif pour un tiers.
 *
 * @param  DoliDB  $db          Objet base de données
 * @param  int     $socid       Identifiant du tiers
 * @param  string  $extrafield  Nom du champ extra booléen
 * @return bool
 */
function creditguard_is_deblocage_actif($db, $socid, $extrafield)
{
    $socid      = (int) $socid;
    $extrafield = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $extrafield);

    if ($socid <= 0 || $extrafield === '') {
        return false;
    }

    $sql = "SELECT " . $extrafield . " AS deblocage";
    $sql .= " FROM " . MAIN_DB_PREFIX . "societe_extrafields";
    $sql .= " WHERE fk_object = " . $socid;

    $res = $db->query($sql);
    if (!$res) {
        dol_syslog('CreditGuard::creditguard_is_deblocage_actif KO ' . $db->lasterror(), LOG_ERR);
        return false;
    }
    $row = $db->fetch_object($res);
    $db->free($res);

    return !empty($row->deblocage) && (int) $row->deblocage === 1;
}

/**
 * Envoie une requête POST JSON vers le webhook configuré.
 * L'échec n'est jamais bloquant.
 *
 * @param  string  $url      URL du webhook
 * @param  array   $payload  Données à envoyer
 * @return bool
 */
function creditguard_send_webhook($url, array $payload)
{
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        dol_syslog('CreditGuard::creditguard_send_webhook URL invalide : ' . $url, LOG_WARNING);
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ctx = stream_context_create(array(
        'http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
            'content'       => $json,
            'timeout'       => 5,
            'ignore_errors' => true,
        ),
    ));

    $result = @file_get_contents($url, false, $ctx);

    if ($result === false) {
        dol_syslog('CreditGuard::creditguard_send_webhook échec envoi vers ' . $url, LOG_WARNING);
        return false;
    }

    return true;
}

/**
 * Prépare les onglets de navigation des pages d'administration CreditGuard.
 *
 * @return array  Tableau des onglets Dolibarr (url, label, id)
 */
function creditguard_admin_prepare_head()
{
    global $langs;
    $langs->load('creditguard@creditguard');

    $h    = 0;
    $head = array();

    // Onglet Configuration
    $head[$h][0] = dol_buildpath('/creditguard/admin/setup.php', 1);
    $head[$h][1] = '<span class="fa fa-cog"></span> ' . $langs->trans('CreditGuardSetup');
    $head[$h][2] = 'setup';
    $h++;

    // Onglet Manuel
    $head[$h][0] = dol_buildpath('/creditguard/admin/manuel.php', 1);
    $head[$h][1] = '<span class="fa fa-book"></span> ' . $langs->trans('CreditGuardManuel');
    $head[$h][2] = 'manuel';
    $h++;

    // Onglet À propos
    $head[$h][0] = dol_buildpath('/creditguard/admin/about.php', 1);
    $head[$h][1] = '<span class="fa fa-info-circle"></span> ' . $langs->trans('About');
    $head[$h][2] = 'about';
    $h++;

    return $head;
}
