<?php
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modPlafondCreance extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db              = $db;
        $this->numero          = 500010;
        $this->rights_class    = 'plafondcreance';
        $this->family          = 'crm';
        $this->module_position = '50';
        $this->name            = preg_replace('/^mod/i', '', get_class($this));
        $this->description     = "Surveillance de l'encours client et contrôle du plafond de créances";
        $this->version         = '1.0.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto           = 'bill';

        $this->module_parts     = array('triggers' => 1);
        $this->config_page_url  = array('setup.php@plafondcreance');
        $this->depends          = array('modFacture', 'modCommande');
        $this->requiredby       = array();
        $this->conflictwith     = array();
        $this->langfiles        = array('plafondcreance@plafondcreance');

        $this->const = array(
            0 => array('PLAFONDCREANCE_EXTRAFIELD_PLAFOND',  'chaine', '',  "Extrafield plafond de crédit (societe)", 0, 'current', 1),
            1 => array('PLAFONDCREANCE_EXTRAFIELD_OVERRIDE', 'chaine', '',  "Extrafield levée de verrou (societe)",   0, 'current', 1),
            2 => array('PLAFONDCREANCE_WEBHOOK_URL',         'chaine', '',  'URL webhook alertes',                   0, 'current', 1),
            3 => array('PLAFONDCREANCE_WEBHOOK_SECRET',      'chaine', '',  'Secret token HMAC SHA-256',             0, 'current', 1),
            4 => array('PLAFONDCREANCE_CHECK_PARENT',        'chaine', '0', "Vérifier encours société mère",         0, 'current', 1),
        );

        $this->rights = array();
        $this->menus  = array();
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/plafondcreance/sql/');
        if ($result < 0) {
            return -1;
        }
        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
