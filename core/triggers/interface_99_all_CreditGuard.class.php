<?php
/* Copyright (C) 2024 CreditGuard Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_all_CreditGuard.class.php
 * \ingroup creditguard
 * \brief   Trigger CreditGuard – contrôle de plafond de crédit
 *
 * Intercepte BILL_VALIDATE et ORDER_VALIDATE pour :
 *  - Envoyer une alerte webhook si l'encours dépasse 80 % du plafond
 *  - Bloquer la validation si l'encours dépasse 100 % et que le déblocage manuel est inactif
 */

// Autoload Dolibarr
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceCreditGuard
 */
class InterfaceCreditGuard extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        parent::__construct($db);

        $this->name        = preg_replace('/^Interface/i', '', get_class($this));
        $this->family      = 'creditguard';
        $this->description = 'Trigger de contrôle du plafond de crédit CreditGuard';
        $this->version     = '1.0.0';
        $this->picto       = 'bill';
    }

    /**
     * Fonction appelée par Dolibarr lors d'un événement.
     *
     * @param  string     $action    Code de l'événement ('BILL_VALIDATE', 'ORDER_VALIDATE', ...)
     * @param  CommonObject $object  Objet concerné par l'événement
     * @param  User        $user     Utilisateur déclenchant l'action
     * @param  Translate   $langs    Gestionnaire de traduction
     * @param  Conf        $conf     Configuration globale
     * @return int                   0 = succès (ne pas bloquer), <0 = erreur / bloquer
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        // On ne traite que les événements qui nous intéressent
        if (!in_array($action, array('BILL_VALIDATE', 'ORDER_VALIDATE'))) {
            return 0;
        }

        // Chargement de la bibliothèque du module
        $module_path = dol_buildpath('/creditguard/lib/creditguard.lib.php', 0);
        if (!file_exists($module_path)) {
            dol_syslog('CreditGuard Trigger: Librairie introuvable – ' . $module_path, LOG_ERR);
            return 0; // Fail-open : ne pas bloquer si le module est absent
        }
        require_once $module_path;

        // -----------------------------------------------------------------------
        // Récupération de la configuration
        // -----------------------------------------------------------------------
        $extrafield_plafond  = empty($conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND)
            ? 'plafond_credit'
            : $conf->global->CREDITGUARD_EXTRAFIELD_PLAFOND;

        $extrafield_deblocage = empty($conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE)
            ? 'deblocage_manuel'
            : $conf->global->CREDITGUARD_EXTRAFIELD_DEBLOCAGE;

        $webhook_url  = $conf->global->CREDITGUARD_WEBHOOK_URL ?? '';
        $seuil_alerte = isset($conf->global->CREDITGUARD_SEUIL_ALERTE)
            ? (float) $conf->global->CREDITGUARD_SEUIL_ALERTE / 100
            : 0.80;

        // -----------------------------------------------------------------------
        // Identification du tiers
        // -----------------------------------------------------------------------
        $socid = (int) ($object->socid ?? $object->fk_soc ?? 0);
        if ($socid <= 0) {
            dol_syslog('CreditGuard Trigger [' . $action . ']: Pas de tiers associé à l\'objet #' . $object->id, LOG_WARNING);
            return 0;
        }

        // -----------------------------------------------------------------------
        // Lecture du plafond
        // -----------------------------------------------------------------------
        $plafond = creditguard_get_plafond($this->db, $socid, $extrafield_plafond);

        // Cas 1 : plafond à 0 ou vide → pas de contrôle
        if ($plafond <= 0) {
            dol_syslog('CreditGuard Trigger [' . $action . ']: Tiers #' . $socid . ' – Pas de plafond défini, action autorisée', LOG_DEBUG);
            return 0;
        }

        // -----------------------------------------------------------------------
        // Chargement du nom du tiers pour les messages
        // -----------------------------------------------------------------------
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $societe = new Societe($this->db);
        $societe->fetch($socid);
        $nom_client = $societe->name ?? ('Tiers #' . $socid);

        // -----------------------------------------------------------------------
        // Calcul de l'encours
        // -----------------------------------------------------------------------
        $encours = creditguard_get_encours($this->db, $socid);
        if ($encours < 0) {
            dol_syslog('CreditGuard Trigger [' . $action . ']: Erreur calcul encours tiers #' . $socid, LOG_ERR);
            return 0; // Fail-open
        }

        $ratio = $encours / $plafond;
        $pct   = round($ratio * 100, 1);

        dol_syslog(
            'CreditGuard Trigger [' . $action . ']: Tiers "' . $nom_client . '" – '
            . 'Encours=' . $encours . ' / Plafond=' . $plafond . ' (' . $pct . '%)',
            LOG_INFO
        );

        // -----------------------------------------------------------------------
        // Cas 3 : Encours >= 100 % du plafond → Bloquer ou débloquer
        // -----------------------------------------------------------------------
        if ($ratio >= 1.0) {
            $deblocage = creditguard_is_deblocage_actif($this->db, $socid, $extrafield_deblocage);

            if (!$deblocage) {
                // BLOCAGE : empêcher la validation
                $langs->load('creditguard@creditguard');
                $msg = sprintf(
                    'CreditGuard : Validation bloquée. L\'encours de "%s" atteint %s %% du plafond autorisé (%s €). '
                    . 'Activez le déblocage manuel sur la fiche tiers pour forcer la validation.',
                    $nom_client,
                    $pct,
                    price($plafond)
                );
                setEventMessages($msg, null, 'errors');
                dol_syslog('CreditGuard Trigger [' . $action . ']: BLOQUÉ – ' . $msg, LOG_WARNING);

                // Envoyer le webhook même en cas de blocage
                if (!empty($webhook_url)) {
                    creditguard_send_webhook($webhook_url, array(
                        'event'      => $action,
                        'statut'     => 'BLOQUE',
                        'client'     => $nom_client,
                        'socid'      => $socid,
                        'encours'    => $encours,
                        'plafond'    => $plafond,
                        'pourcentage' => $pct,
                        'timestamp'  => date('c'),
                    ));
                }

                return -1; // Valeur négative = erreur = blocage de l'action
            }

            // Déblocage manuel actif → autoriser mais loguer
            $msg_log = 'CreditGuard Trigger [' . $action . ']: AUTORISÉ par déblocage manuel – '
                . 'Tiers "' . $nom_client . '" encours=' . $encours . ' / plafond=' . $plafond
                . ' (' . $pct . '%) – User #' . $user->id . ' (' . $user->login . ')';
            dol_syslog($msg_log, LOG_WARNING);

            setEventMessages(
                'CreditGuard : Validation forcée par déblocage manuel pour "' . $nom_client
                . '" (' . $pct . '% du plafond).',
                null,
                'warnings'
            );

            // Notifier le webhook
            if (!empty($webhook_url)) {
                creditguard_send_webhook($webhook_url, array(
                    'event'       => $action,
                    'statut'      => 'FORCE_DEBLOCAGE',
                    'client'      => $nom_client,
                    'socid'       => $socid,
                    'encours'     => $encours,
                    'plafond'     => $plafond,
                    'pourcentage' => $pct,
                    'user_login'  => $user->login,
                    'timestamp'   => date('c'),
                ));
            }

            return 0;
        }

        // -----------------------------------------------------------------------
        // Cas 2 : Encours >= seuil d'alerte (défaut 80 %) → Alerte webhook
        // -----------------------------------------------------------------------
        if ($ratio >= $seuil_alerte) {
            dol_syslog(
                'CreditGuard Trigger [' . $action . ']: ALERTE ' . ($seuil_alerte * 100) . '% – '
                . 'Tiers "' . $nom_client . '" à ' . $pct . '% du plafond',
                LOG_WARNING
            );

            setEventMessages(
                'CreditGuard : Attention, l\'encours de "' . $nom_client . '" atteint '
                . $pct . '% du plafond autorisé (' . price($plafond) . ' €).',
                null,
                'warnings'
            );

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

            return 0; // On laisse passer, c'est une alerte non bloquante
        }

        // Cas nominal : encours sous le seuil d'alerte
        return 0;
    }
}
