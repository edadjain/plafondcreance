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
 * \file    core/modules/modCreditGuard.class.php
 * \ingroup creditguard
 * \brief   Descripteur du module CreditGuard v1.0.0
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

        // ---------------------------------------------------------------
        // Identification du module
        // ---------------------------------------------------------------
        $this->numero          = 500099;
        $this->rights_class    = 'creditguard';

        // Catégorie : Dolico Tech (correspond au group visible dans la liste des modules)
        $this->family          = 'dolicotech';
        $this->familylabel     = 'Dolico Tech';
        $this->module_position = 500;

        $this->name        = preg_replace('/^mod/i', '', get_class($this)); // CreditGuard
        $this->description = 'Surveillance de l\'encours client et contrôle du plafond de crédit';
        $this->descriptionlong = 'CreditGuard surveille en temps réel l\'encours de chaque client '
            . '(factures impayées TTC + commandes validées non facturées TTC) et le compare à un plafond '
            . 'défini sur la fiche tiers. En cas de dépassement, la validation est bloquée ou une alerte '
            . 'est envoyée selon le niveau configuré.';

        $this->version    = '1.0.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto      = 'bill';

        // ---------------------------------------------------------------
        // Éditeur / Support
        // ---------------------------------------------------------------
        $this->editor_name = 'Dolico Tech';
        $this->editor_url  = 'https://www.dolico.tech';

        // ---------------------------------------------------------------
        // Dépendances
        // ---------------------------------------------------------------
        $this->depends      = array('modSociete', 'modFacture', 'modCommande');
        $this->requiredby   = array();
        $this->conflictwith = array();
        $this->langfiles    = array('creditguard@creditguard');
        $this->phpmin       = array(7, 0);
        $this->need_dolibarr_version = array(15, 0, 0);

        // ---------------------------------------------------------------
        // Parties activées
        // ---------------------------------------------------------------
        $this->module_parts = array('triggers' => 1);

        // ---------------------------------------------------------------
        // Page de configuration (premier onglet)
        // ---------------------------------------------------------------
        $this->config_page_url = array('setup.php@creditguard');

        // ---------------------------------------------------------------
        // Constantes
        // ---------------------------------------------------------------
        $this->const = array(
            0 => array(
                'CREDITGUARD_EXTRAFIELD_PLAFOND',
                'chaine',
                'plafond_credit',
                'Nom de l\'extrafield (tiers) contenant le plafond de crédit',
                1,
                'deleteforever',
            ),
            1 => array(
                'CREDITGUARD_EXTRAFIELD_DEBLOCAGE',
                'chaine',
                'deblocage_manuel',
                'Nom de l\'extrafield booléen (tiers) de déblocage exceptionnel',
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

        // ---------------------------------------------------------------
        // Droits
        // ---------------------------------------------------------------
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

        // ---------------------------------------------------------------
        // Menus
        // ---------------------------------------------------------------
        $this->menu = array();
    }

    /**
     * Activation du module
     * @return int 1=OK, <0=KO
     */
    public function init($options = '')
    {
        return $this->_init(array(), $options);
    }

    /**
     * Désactivation du module
     * @return int 1=OK, <0=KO
     */
    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
