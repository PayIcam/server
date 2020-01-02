<?php

namespace Payutc\Service;

use \Payutc\Config;
use \Payutc\Bom\User;

/**
 * RELOADEVENT.php
 *
 * Ce service expose les méthodes pour permettre d'effectuer le rechargement d'un solde Event d'un user.
 *
 */

class RELOADEVENT extends \ServiceBase {
    /**
    * Fonction pour recharger un client.
    *
    * @param int $amount (en centimes)
    * @return Boolean
    */
    public function reload($amount, $badge_uid, $reload_type) {
        // On a une appli qui a les droits ?
        $this->checkRight();

        try {
            $buyer = User::getUserFromBadge($badge_uid);
        }
        catch(UserNotFound $ex) {
            Log::warn("transaction($fun_id, $badge_uid, $obj_ids) : User not found");
            throw new ReloadEventException("Ce badge n'a pas été reconnu");
        }

        $buyer->checkReloadEvent($amount);
        \Payutc\Bom\ReloadEvent::reload($amount, $buyer->getId(), $this->user()->getId(), $this->application()->getId(), $reload_type);
        $buyer->incCreditEvent($amount);

        return true;
    }
}
