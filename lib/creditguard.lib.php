<?php
/* Copyright (C) 2024 CreditGuard Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/creditguard.lib.php
 * \ingroup creditguard
 * \brief   Fonctions utilitaires du module CreditGuard
 */

/**
 * Calcule l'encours total d'un tiers :
 *   Encours = Somme factures clients impayées TTC
 *           + Somme commandes clients validées non (ou partiellement) facturées TTC
 *
 * @param  DoliDB $db          Objet base de données
 * @param  int    $socid       Identifiant du tiers
 * @return float               Encours en devise de base, -1 en cas d'erreur
 */
function creditguard_get_encours($db, $socid)
{
    $socid = (int) $socid;
    if ($socid <= 0) {
        return -1;
    }

    $encours = 0.0;

    // -----------------------------------------------------------------------
    // 1. Factures clients impayées (statut 1 = validée, 2 = partiellement payée)
    //    On prend le restant dû TTC pour couvrir le cas des paiements partiels.
    // -----------------------------------------------------------------------
    $sql = "SELECT SUM(f.total_ttc - f.paye) AS montant_impaye";
    $sql .= " FROM " . MAIN_DB_PREFIX . "facture AS f";
    $sql .= " WHERE f.fk_soc = " . $socid;
    $sql .= " AND f.entity IN (" . getEntity('invoice') . ")";
    $sql .= " AND f.type IN (0, 1, 3)"; // Facture standard, avoir à exclure (type 2), acompte
    $sql .= " AND f.fk_statut IN (1, 2)"; // 1 = validée, 2 = partiellement payée
    $sql .= " AND f.paye = 0"; // Pas entièrement payée

    // Recalcul exact via le solde restant dû
    $sql2 = "SELECT SUM(f.total_ttc - COALESCE(psum.montant_paye, 0)) AS montant_impaye";
    $sql2 .= " FROM " . MAIN_DB_PREFIX . "facture AS f";
    $sql2 .= " LEFT JOIN (";
    $sql2 .= "   SELECT fk_facture, SUM(amount) AS montant_paye";
    $sql2 .= "   FROM " . MAIN_DB_PREFIX . "paiement_facture";
    $sql2 .= "   GROUP BY fk_facture";
    $sql2 .= " ) AS psum ON psum.fk_facture = f.rowid";
    $sql2 .= " WHERE f.fk_soc = " . $socid;
    $sql2 .= " AND f.entity IN (" . getEntity('invoice') . ")";
    $sql2 .= " AND f.type NOT IN (2)"; // Exclure les avoirs
    $sql2 .= " AND f.fk_statut IN (1, 2)"; // Validées ou partiellement payées
    $sql2 .= " AND f.paye = 0"; // Non soldées

    $resql = $db->query($sql2);
    if (!$resql) {
        dol_syslog('creditguard_get_encours: Erreur requête factures - ' . $db->lasterror(), LOG_ERR);
        return -1;
    }
    $obj = $db->fetch_object($resql);
    $encours += (float) ($obj->montant_impaye ?? 0);
    $db->free($resql);

    // -----------------------------------------------------------------------
    // 2. Commandes clients validées, non entièrement facturées
    //    Statuts commande : 1 = validée, 2 = en cours de traitement
    //    On prend la partie non encore facturée (total_ttc - montant déjà facturé)
    // -----------------------------------------------------------------------
    $sql3 = "SELECT SUM(c.total_ttc - COALESCE(fsum.montant_facture, 0)) AS montant_non_facture";
    $sql3 .= " FROM " . MAIN_DB_PREFIX . "commande AS c";
    $sql3 .= " LEFT JOIN (";
    $sql3 .= "   SELECT fk_commande, SUM(f2.total_ttc) AS montant_facture";
    $sql3 .= "   FROM " . MAIN_DB_PREFIX . "element_element AS ee";
    $sql3 .= "   JOIN " . MAIN_DB_PREFIX . "facture AS f2 ON f2.rowid = ee.fk_target";
    $sql3 .= "     AND ee.targettype = 'facture'";
    $sql3 .= "     AND f2.fk_statut IN (1, 2)"; // Factures validées ou partiellement payées
    $sql3 .= "     AND f2.type NOT IN (2)"; // Pas d'avoirs
    $sql3 .= "   WHERE ee.sourcetype = 'commande'";
    $sql3 .= "   GROUP BY fk_commande";
    $sql3 .= " ) AS fsum ON fsum.fk_commande = c.rowid";
    $sql3 .= " WHERE c.fk_soc = " . $socid;
    $sql3 .= " AND c.entity IN (" . getEntity('order') . ")";
    $sql3 .= " AND c.fk_statut IN (1, 2)"; // Validée ou en cours
    $sql3 .= " AND c.facture = 0"; // Non entièrement facturée

    $resql3 = $db->query($sql3);
    if (!$resql3) {
        dol_syslog('creditguard_get_encours: Erreur requête commandes - ' . $db->lasterror(), LOG_ERR);
        return -1;
    }
    $obj3 = $db->fetch_object($resql3);
    $montant_non_facture = (float) ($obj3->montant_non_facture ?? 0);
    // On ne prend pas les valeurs négatives (sur-facturation possible)
    if ($montant_non_facture > 0) {
        $encours += $montant_non_facture;
    }
    $db->free($resql3);

    return round($encours, 2);
}

/**
 * Envoie une notification JSON vers le webhook configuré.
 *
 * @param  string $webhook_url  URL du webhook
 * @param  array  $payload      Données à envoyer
 * @return bool                 true si succès HTTP (2xx), false sinon
 */
function creditguard_send_webhook($webhook_url, array $payload)
{
    if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        dol_syslog('creditguard_send_webhook: URL invalide ou vide', LOG_WARNING);
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $context = stream_context_create(array(
        'http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
            'content'       => $json,
            'timeout'       => 5, // secondes
            'ignore_errors' => true,
        ),
    ));

    $result = @file_get_contents($webhook_url, false, $context);

    if ($result === false) {
        dol_syslog('creditguard_send_webhook: Échec de l\'envoi vers ' . $webhook_url, LOG_WARNING);
        return false;
    }

    // Vérifier le code HTTP de la réponse
    if (isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;
        if ($httpCode < 200 || $httpCode >= 300) {
            dol_syslog('creditguard_send_webhook: Code HTTP ' . $httpCode . ' reçu', LOG_WARNING);
            return false;
        }
    }

    return true;
}

/**
 * Retourne le plafond de crédit d'un tiers depuis son extrafield.
 *
 * @param  DoliDB  $db           Objet base de données
 * @param  int     $socid        Identifiant du tiers
 * @param  string  $extrafield   Nom du champ extra (sans le préfixe "options_")
 * @return float                 Plafond (0 = pas de contrôle)
 */
function creditguard_get_plafond($db, $socid, $extrafield)
{
    $socid     = (int) $socid;
    $extrafield = preg_replace('/[^a-zA-Z0-9_]/', '', $extrafield); // Sécurisation

    if ($socid <= 0 || empty($extrafield)) {
        return 0;
    }

    $sql = "SELECT " . $db->sanitize($extrafield) . " AS plafond";
    $sql .= " FROM " . MAIN_DB_PREFIX . "societe_extrafields";
    $sql .= " WHERE fk_object = " . $socid;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('creditguard_get_plafond: Erreur - ' . $db->lasterror(), LOG_ERR);
        return 0;
    }
    $obj = $db->fetch_object($resql);
    $db->free($resql);

    return (float) ($obj->plafond ?? 0);
}

/**
 * Vérifie si le déblocage manuel est activé pour un tiers.
 *
 * @param  DoliDB  $db           Objet base de données
 * @param  int     $socid        Identifiant du tiers
 * @param  string  $extrafield   Nom du champ extra booléen
 * @return bool
 */
function creditguard_is_deblocage_actif($db, $socid, $extrafield)
{
    $socid     = (int) $socid;
    $extrafield = preg_replace('/[^a-zA-Z0-9_]/', '', $extrafield);

    if ($socid <= 0 || empty($extrafield)) {
        return false;
    }

    $sql = "SELECT " . $db->sanitize($extrafield) . " AS deblocage";
    $sql .= " FROM " . MAIN_DB_PREFIX . "societe_extrafields";
    $sql .= " WHERE fk_object = " . $socid;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('creditguard_is_deblocage_actif: Erreur - ' . $db->lasterror(), LOG_ERR);
        return false;
    }
    $obj = $db->fetch_object($resql);
    $db->free($resql);

    return !empty($obj->deblocage) && $obj->deblocage == 1;
}
