<?php
/* Copyright (C) 2024 CreditGuard Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    modCreditGuard.class.php
 * \ingroup creditguard
 * \brief   Descripteur du module CreditGuard
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description et activation du module CreditGuard
 */
class modCreditGuard extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // -- Identification du module --
        $this->numero = 500010; // Numéro unique (choisir un nombre > 500000 pour les modules tiers)
        $this->rights_class = 'creditguard';
        $this->family = 'crm';
        $this->module_position = '50';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Contrôle du plafond de crédit client (encours factures + commandes)';
        $this->descriptionlong = 'Module CreditGuard : surveille l\'encours client (factures impayées TTC + commandes validées non facturées TTC) par rapport à un plafond défini dans un extrafield du tiers. Bloque ou alerte selon le seuil atteint.';
        $this->editor_name = 'CreditGuard';
        $this->editor_url = '';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'bill';

        // -- Dépendances --
        $this->depends = array('modSociete', 'modFacture', 'modCommande');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('creditguard@creditguard');
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(15, 0, 0);
        $this->warnings_activation = array();

        // -- Constantes du module --
        $this->const = array(
            // array(nom, type, valeur_defaut, description, visible, deleteonunactiv)
            0 => array(
                'CREDITGUARD_EXTRAFIELD_PLAFOND',
                'chaine',
                'plafond_credit',
                'Nom de l\'extrafield du tiers contenant le plafond de crédit',
                1,
                'deleteforever',
            ),
            1 => array(
                'CREDITGUARD_EXTRAFIELD_DEBLOCAGE',
                'chaine',
                'deblocage_manuel',
                'Nom de l\'extrafield booléen de déblocage exceptionnel',
                1,
                'deleteforever',
            ),
            2 => array(
                'CREDITGUARD_WEBHOOK_URL',
                'chaine',
                '',
                'URL du webhook pour les alertes',
                1,
                'deleteforever',
            ),
            3 => array(
                'CREDITGUARD_SEUIL_ALERTE',
                'chaine',
                '80',
                'Seuil d\'alerte en pourcentage (défaut : 80)',
                1,
                'deleteforever',
            ),
        );

        // -- Tables SQL créées par le module --
        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();
        $this->cronjobs = array();

        // -- Permissions --
        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Consulter le tableau de bord CreditGuard';
        $this->rights[$r][3] = 1; // Activé par défaut
        $this->rights[$r][4] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Configurer le module CreditGuard';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'setup';
        $r++;

        // -- Menus --
        $this->menu = array();
    }

    /**
     * Fonction appelée lors de l'activation du module.
     * Insère les données initiales nécessaires.
     *
     * @return int 1 si OK, 0 si KO
     */
    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    /**
     * Fonction appelée lors de la désactivation du module.
     *
     * @return int 1 si OK, 0 si KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
