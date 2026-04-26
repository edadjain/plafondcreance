<?php
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

class interface_99_modPlafondCreance_PlafondCreanceTrigger extends DolibarrTriggers
{
    public $family      = 'plafondcreance';
    public $description = 'Triggers du module PlafondCreance';
    public $version     = self::VERSION_DOLIBARR;
    public $picto       = 'bill';

    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!in_array($action, array('BILL_VALIDATE', 'ORDER_VALIDATE'))) {
            return 0;
        }

        if (empty($conf->plafondcreance->enabled)) {
            return 0;
        }

        dol_include_once('/plafondcreance/class/actionPlafondCreance.class.php');

        $handler = new ActionPlafondCreance($this->db);
        $result  = $handler->checkCreditLimit($object, $user, $action);

        if ($result < 0) {
            $this->error  = $handler->error;
            $this->errors = $handler->errors;
            return -1;
        }

        return 0;
    }
}
