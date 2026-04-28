<?php
/* Copyright (C) 2024 CreditGuard
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_modCreditGuard_CreditGuardTrigger.class.php
 * \ingroup creditguard
 * \brief   Trigger CreditGuard – contrôle de plafond de crédit
 *
 * Convention de nommage Dolibarr :
 *   interface_{priorité}_mod{NomModule}_{NomTrigger}.class.php
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceCreditGuardTrigger
 */
class InterfaceCreditGuardTrigger extends DolibarrTriggers
{
    /**
     * @param DoliDB $db
     */
    public function __construct($db)
    {
        parent::__construct($db);

        $this->name        = preg_replace('/^Interface/i', '', get_class($this));
        $this->family      = 'creditguard';
        $this->description = 'Trigger de contrôle du plafond de crédit (CreditGuard)';
        $this->version     = '1.0.0';
        $this->picto       = 'bill';
    }

    /**
     * Fonction appelée lors de chaque événement Dolibarr.
     *
     * @param  string        $action  Code événement
     * @param  CommonObject  $object  Objet concerné
     * @param  User          $user    Utilisateur
     * @param  Translate     $langs   Traductions
     * @param  Conf          $conf    Configuration
     * @return int                    0 = succès, <0 = erreur/blocage
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // On ne traite que ces deux événements
        if (!in_array($action, array('BILL_VALIDATE', 'ORDER_VALIDATE'), true)) {
            return 0;
        }

        // Le module doit être actif
        if (empty($conf->creditguard->enabled)) {
            return 0;
        }

        // Chargement de la bibliothèque métier
        require_once dol_buildpath('/creditguard/lib/creditguard.lib.php', 0);

        // ---------------------------------------------------------------
        // Lecture de la configuration
        // ---------------------------------------------------------------
        $ef_plafond   = empty($conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND)
            ? 'plafond_credit'
            : $conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND;

        $ef_deblocage = empty($conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE)
            ? 'deblocage_manuel'
            : $conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE;

        $webhook_url  = (string) ($conf->global->CREDITGUARD_WEBHOOK_URL ?? '');

        $seuil_pct    = isset($conf->global->CREDITGUARD_SEUIL_ALERTE)
            ? (float) $conf->global->CREDITGUARD_SEUIL_ALERTE
            : 80.0;
        $seuil_ratio  = $seuil_pct / 100;

        // ---------------------------------------------------------------
        // Identifiant du tiers
        // ---------------------------------------------------------------
        $socid = (int) ($object->socid ?? $object->fk_soc ?? 0);
        if ($socid <= 0) {
            dol_syslog('CreditGuard [' . $action . '] : pas de socid sur objet #' . $object->id, LOG_WARNING);
            return 0;
        }

        // ---------------------------------------------------------------
        // Lecture du plafond
        // ---------------------------------------------------------------
        $plafond = creditguard_get_plafond($this->db, $socid, $ef_plafond);

        // Cas 1 : plafond nul ou absent → aucun contrôle
        if ($plafond <= 0) {
            return 0;
        }

        // ---------------------------------------------------------------
        // Nom du tiers pour les messages
        // ---------------------------------------------------------------
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $soc = new Societe($this->db);
        $soc->fetch($socid);
        $nom_client = !empty($soc->name) ? $soc->name : 'Tiers #' . $socid;

        // ---------------------------------------------------------------
        // Calcul de l'encours
        // ---------------------------------------------------------------
        $encours = creditguard_get_encours($this->db, $socid);
        if ($encours < 0) {
            // Erreur SQL → fail-open, on ne bloque pas
            dol_syslog('CreditGuard [' . $action . '] : erreur calcul encours tiers #' . $socid, LOG_ERR);
            return 0;
        }

        $ratio = $encours / $plafond;
        $pct   = round($ratio * 100, 1);

        dol_syslog(
            'CreditGuard [' . $action . '] "' . $nom_client . '" '
            . 'encours=' . $encours . ' plafond=' . $plafond . ' (' . $pct . '%)',
            LOG_INFO
        );

        // ---------------------------------------------------------------
        // Cas 3 : Encours >= 100 % → Blocage ou déblocage exceptionnel
        // ---------------------------------------------------------------
        if ($ratio >= 1.0) {
            $deblocage = creditguard_is_deblocage_actif($this->db, $socid, $ef_deblocage);

            if ($deblocage) {
                // Déblocage actif → forcer la validation mais tout loguer
                $msg = 'CreditGuard : validation FORCÉE par déblocage manuel pour "'
                    . $nom_client . '" (' . $pct . '% du plafond)'
                    . ' – user ' . $user->login;
                dol_syslog($msg, LOG_WARNING);
                setEventMessages(
                    'CreditGuard : validation autorisée par déblocage manuel pour "'
                    . $nom_client . '" (' . $pct . '% du plafond).',
                    null,
                    'warnings'
                );
                if (!empty($webhook_url)) {
                    creditguard_send_webhook($webhook_url, array(
                        'event'       => $action,
                        'statut'      => 'FORCE_DEBLOCAGE',
                        'client'      => $nom_client,
                        'socid'       => $socid,
                        'encours'     => $encours,
                        'plafond'     => $plafond,
                        'pourcentage' => $pct,
                        'user'        => $user->login,
                        'timestamp'   => date('c'),
                    ));
                }
                return 0;
            }

            // Déblocage inactif → BLOCAGE
            $msg_block = 'CreditGuard : validation bloquée pour "' . $nom_client
                . '" — encours ' . number_format($encours, 2, ',', ' ') . ' €'
                . ' représente ' . $pct . '% du plafond autorisé'
                . ' (' . number_format($plafond, 2, ',', ' ') . ' €).'
                . ' Activez le déblocage manuel sur la fiche tiers pour forcer la validation.';

            setEventMessages($msg_block, null, 'errors');
            dol_syslog('CreditGuard [' . $action . '] BLOQUÉ : ' . $msg_block, LOG_WARNING);

            if (!empty($webhook_url)) {
                creditguard_send_webhook($webhook_url, array(
                    'event'       => $action,
                    'statut'      => 'BLOQUE',
                    'client'      => $nom_client,
                    'socid'       => $socid,
                    'encours'     => $encours,
                    'plafond'     => $plafond,
                    'pourcentage' => $pct,
                    'timestamp'   => date('c'),
                ));
            }

            return -1; // Valeur négative = blocage de l'action
        }

        // ---------------------------------------------------------------
        // Cas 2 : Encours >= seuil d'alerte (défaut 80 %) → Alerte seule
        // ---------------------------------------------------------------
        if ($ratio >= $seuil_ratio) {
            $msg_warn = 'CreditGuard : encours de "' . $nom_client
                . '" atteint ' . $pct . '% du plafond autorisé'
                . ' (' . number_format($plafond, 2, ',', ' ') . ' €).';

            setEventMessages($msg_warn, null, 'warnings');
            dol_syslog('CreditGuard [' . $action . '] ALERTE ' . $pct . '% : ' . $msg_warn, LOG_WARNING);

            if (!empty($webhook_url)) {
                creditguard_send_webhook($webhook_url, array(
                    'event'       => $action,
                    'statut'      => 'ALERTE',
                    'client'      => $nom_client,
                    'socid'       => $socid,
                    'encours'     => $encours,
                    'plafond'     => $plafond,
                    'pourcentage' => $pct,
                    'timestamp'   => date('c'),
                ));
            }

            return 0; // Alerte non bloquante
        }

        // Cas nominal : encours sous le seuil → rien à faire
        return 0;
    }
}
