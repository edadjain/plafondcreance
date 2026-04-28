<?php
/* Copyright (C) 2024 CreditGuard
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modCreditGuard.class.php
 * \ingroup creditguard
 * \brief   Descripteur du module CreditGuard (contrôle plafond de crédit)
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Class modCreditGuard
 */
class modCreditGuard extends DolibarrModules
{
    /**
     * @param DoliDB $db
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // ---------- Identification ----------
        $this->numero          = 500099; // Numéro unique pour les modules tiers
        $this->rights_class    = 'creditguard';
        $this->family          = 'crm';
        $this->module_position = 500;
        $this->name            = preg_replace('/^mod/i', '', get_class($this)); // = 'CreditGuard'
        $this->description     = 'Contrôle du plafond de crédit client (encours factures + commandes)';
        $this->version         = '1.0.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name); // MAIN_MODULE_CREDITGUARD
        $this->picto           = 'bill';

        // ---------- Dépendances ----------
        $this->depends      = array('modSociete', 'modFacture', 'modCommande');
        $this->requiredby   = array();
        $this->conflictwith = array();
        $this->langfiles    = array('creditguard@creditguard');

        // ---------- Parties activées ----------
        $this->module_parts = array(
            'triggers' => 1, // Active le trigger
        );

        // ---------- Page de configuration ----------
        $this->config_page_url = array('setup.php@creditguard');

        // ---------- Constantes ----------
        $this->const = array(
            0 => array(
                'CREDITGUARD_EXTRAFIELD_PLAFOND',
                'chaine',
                'plafond_credit',
                'Nom du champ extra (extrafield) du tiers contenant le plafond de crédit',
                1,
                'deleteforever',
            ),
            1 => array(
                'CREDITGUARD_EXTRAFIELD_DEBLOCAGE',
                'chaine',
                'deblocage_manuel',
                'Nom du champ extra (extrafield) booléen de déblocage exceptionnel',
                1,
                'deleteforever',
            ),
            2 => array(
                'CREDITGUARD_WEBHOOK_URL',
                'chaine',
                '',
                'URL du webhook pour les alertes JSON',
                1,
                'deleteforever',
            ),
            3 => array(
                'CREDITGUARD_SEUIL_ALERTE',
                'chaine',
                '80',
                'Seuil d\'alerte en % (défaut 80)',
                1,
                'deleteforever',
            ),
        );

        // ---------- Droits ----------
        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Accéder au module CreditGuard';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';
        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Configurer le module CreditGuard';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'setup';

        // ---------- Menus ----------
        $this->menu = array();
    }

    /**
     * Activation du module
     * @return int 1=OK
     */
    public function init($options = '')
    {
        return $this->_init(array(), $options);
    }

    /**
     * Désactivation du module
     * @return int 1=OK
     */
    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
