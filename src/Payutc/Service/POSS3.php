<?php

namespace Payutc\Service;

use \Payutc\Exception\PossException;
use \Payutc\Exception\UserNotFound;
use \Payutc\Bom\User;

class POSS3 extends POSS {

    protected function shouldICheckUser() {
        return true;
    }

    /**
     * Obtenir les infos d'un buyer
     *
     * @param String $badge_id
     * @return array $state
     */
    public function getBuyerInfo($badge_id, $fun_id=null) {
        // Verifier que le buyer existe
        try {
            $buyer = User::getUserFromBadge($badge_id);
        }
        catch(UserNotFound $ex) {
            Log::warn("getBuyerInfo($badge_id) : User not found");
            throw new PossException("Ce badge n'a pas été reconnu");
        }
        return parent::getBuyerInfo($buyer, $fun_id);
    }

    /**
     * Transaction complète,
     *         1. load le buyer
     *         2. multiselect
     *         3. endTransaction
     * @param String $badge_id
     * @param String $obj_ids list of ids separated by a space or json : [[id1, qte1], [id2, qt2], ...]
     * @return array $state
     */
    public function transaction($fun_id, $badge_id, $obj_ids) {

        // Verifier que le buyer existe
        try {
            $buyer = User::getUserFromBadge($badge_id);
        }
        catch(UserNotFound $ex) {
            Log::warn("transaction($fun_id, $badge_id, $obj_ids) : User not found");
            throw new PossException("Ce badge n'a pas été reconnu");
        }

        return parent::transaction($fun_id, $buyer, $obj_ids);
    }
}


