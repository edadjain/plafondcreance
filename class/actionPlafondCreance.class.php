<?php
class ActionPlafondCreance
{
    public $db;
    public $error  = '';
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Vérifie le plafond de crédit. Retourne 0 = OK, -1 = bloqué.
     */
    public function checkCreditLimit($object, $user, $action)
    {
        global $conf, $langs;

        $langs->load('plafondcreance@plafondcreance');

        $extrafield_plafond  = !empty($conf->global->PLAFONDCREANCE_EXTRAFIELD_PLAFOND)  ? $conf->global->PLAFONDCREANCE_EXTRAFIELD_PLAFOND  : '';
        $extrafield_override = !empty($conf->global->PLAFONDCREANCE_EXTRAFIELD_OVERRIDE) ? $conf->global->PLAFONDCREANCE_EXTRAFIELD_OVERRIDE : '';

        if (empty($extrafield_plafond)) {
            return 0;
        }

        $socid = (int) $object->socid;
        if ($socid <= 0) {
            return 0;
        }

        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $soc = new Societe($this->db);
        if ($soc->fetch($socid) <= 0) {
            return 0;
        }
        $soc->fetch_optionals();

        $plafond = isset($soc->array_options['options_' . $extrafield_plafond])
            ? (float) $soc->array_options['options_' . $extrafield_plafond]
            : 0;

        if ($plafond <= 0) {
            return 0;
        }

        // Option : vérifier l'encours de la société mère
        $socid_check = $socid;
        if (!empty($conf->global->PLAFONDCREANCE_CHECK_PARENT) && !empty($soc->parent)) {
            $socParent = new Societe($this->db);
            if ($socParent->fetch((int) $soc->parent) > 0) {
                $socParent->fetch_optionals();
                $plafond_parent = isset($socParent->array_options['options_' . $extrafield_plafond])
                    ? (float) $socParent->array_options['options_' . $extrafield_plafond]
                    : 0;
                if ($plafond_parent > 0) {
                    $socid_check = (int) $soc->parent;
                    $plafond     = $plafond_parent;
                }
            }
        }

        $encours = $this->calculateEncours($socid_check);
        if ($encours < 0) {
            return 0; // Erreur de calcul → fail-open
        }

        dol_syslog('PlafondCreance: socid=' . $socid_check . ' encours=' . $encours . ' plafond=' . $plafond, LOG_DEBUG);

        // Scénario A : alerte 80 % (jamais bloquant)
        if ($encours >= $plafond * 0.8) {
            $this->sendWebhookAlert($socid_check, 80, $encours, $plafond, $user->id);
        }

        // Scénario B : blocage 100 %
        if ($encours >= $plafond) {
            $override = false;
            if (!empty($extrafield_override)) {
                $override = !empty($soc->array_options['options_' . $extrafield_override]);
            }

            $this->logAudit($socid_check, $encours, $plafond, $user->id, $override ? 'override_allowed' : 'blocked');

            if ($override) {
                return 0;
            }

            $this->error    = $langs->trans('PlafondCreanceBloque', price($encours), price($plafond));
            $this->errors[] = $this->error;
            return -1;
        }

        return 0;
    }

    /**
     * Encours = factures impayées - avoirs + commandes validées non facturées
     */
    public function calculateEncours($socid)
    {
        $socid = (int) $socid;

        $sql = 'SELECT'
            . ' COALESCE((SELECT SUM(f.total_ttc) FROM ' . MAIN_DB_PREFIX . 'facture f'
            . '  WHERE f.fk_soc = ' . $socid
            . '  AND f.entity IN (' . getEntity('invoice') . ')'
            . '  AND f.fk_statut = 1 AND f.paye = 0 AND f.type IN (0,1,3)), 0)'
            . ' - COALESCE((SELECT SUM(f.total_ttc) FROM ' . MAIN_DB_PREFIX . 'facture f'
            . '  WHERE f.fk_soc = ' . $socid
            . '  AND f.entity IN (' . getEntity('invoice') . ')'
            . '  AND f.fk_statut = 1 AND f.paye = 0 AND f.type = 2), 0)'
            . ' + COALESCE((SELECT SUM(c.total_ttc) FROM ' . MAIN_DB_PREFIX . 'commande c'
            . '  WHERE c.fk_soc = ' . $socid
            . '  AND c.entity IN (' . getEntity('order') . ')'
            . '  AND c.fk_statut IN (1,2,3) AND c.facture = 0), 0)'
            . ' AS encours';

        $result = $this->db->query($sql);
        if (!$result) {
            dol_syslog('PlafondCreance calculateEncours: ' . $this->db->lasterror(), LOG_ERR);
            return -1;
        }
        $row = $this->db->fetch_object($result);
        return max(0, (float) $row->encours);
    }

    /**
     * Webhook HMAC-signé. L'échec n'est jamais bloquant (fail-safe).
     */
    public function sendWebhookAlert($socid, $threshold_pct, $encours, $plafond, $userid)
    {
        global $conf;

        $url    = !empty($conf->global->PLAFONDCREANCE_WEBHOOK_URL)    ? $conf->global->PLAFONDCREANCE_WEBHOOK_URL    : '';
        $secret = !empty($conf->global->PLAFONDCREANCE_WEBHOOK_SECRET) ? $conf->global->PLAFONDCREANCE_WEBHOOK_SECRET : '';

        if (empty($url)) {
            return 0;
        }

        $payload = json_encode(array(
            'event'         => 'credit_threshold_reached',
            'threshold_pct' => $threshold_pct,
            'socid'         => $socid,
            'plafond'       => $plafond,
            'encours'       => $encours,
            'userid'        => $userid,
            'timestamp'     => dol_now(),
        ));

        $signature = hash_hmac('sha256', $payload, $secret);

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'X-Dolibarr-Signature: ' . $signature,
            ),
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ));

        if (curl_exec($ch) !== false) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            dol_syslog('PlafondCreance webhook sent HTTP ' . $http_code, LOG_DEBUG);
        } else {
            dol_syslog('PlafondCreance webhook error: ' . curl_error($ch), LOG_WARNING);
        }
        curl_close($ch);

        return 0; // Jamais bloquant
    }

    /**
     * Insère une ligne dans la table d'audit.
     */
    public function logAudit($socid, $encours, $plafond, $userid, $action)
    {
        $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'plafondcreance_log'
            . ' (fk_soc, encours, plafond, fk_user, action, date_action) VALUES ('
            . (int)   $socid   . ','
            . (float) $encours . ','
            . (float) $plafond . ','
            . (int)   $userid  . ','
            . "'" . $this->db->escape($action) . "',"
            . "'" . $this->db->idate(dol_now()) . "')";

        if (!$this->db->query($sql)) {
            dol_syslog('PlafondCreance logAudit: ' . $this->db->lasterror(), LOG_WARNING);
        }
    }
}
