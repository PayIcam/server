<?php

namespace Payutc\Service;

use \Payutc\Config;
use \Payutc\Bom\User;
use \Payutc\Bom\Transaction;
use \Payutc\Log;

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

    public function reloadPayIcam($amount) {
        $confReloadEvent = Config::get('reload_event');
        $fun_id = $confReloadEvent['fun_id']; // PaperCut
        $article_id = $confReloadEvent['obj_id']; // article

        $user = $this->user();

        try {
            $this->checkRight(false, true, true, $fun_id);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        // On a un user ?
        if (!$user) {
            throw new \Payutc\Exception\CheckRightException("Vous devez connecter un utilisateur !");
        }

        // Verification de la possiblité de recharger
        if (!is_numeric($amount))
            throw new TransferException("Mauvais montant entré");

        $amount = $amount*1;

        if ($amount < 0) {
            Log::warn("TRANSFERT: Montant négatif par l'userID ".$user->getId()." vers PaperCut ");
            throw new TransferException("Tu ne peux pas faire un virement négatif (bien essayé)");
        } else if ($amount == 0) {
            throw new TransferException("Pas de montant saisi");
        } else if ($user->getCredit() < $amount) {
            throw new TransferException("Tu n'as pas assez d'argent pour réaliser ce virement");
        } else if ($amount % 1000 !== 0) {
            throw new TransferException("Vous essayez de recharger par autre chose que des dizaines.");
        }

        $dozens_c = intdiv($amount, 1000);

        $user = $this->user();

        try {
            $transaction = Transaction::createAndValidate(
                $user, // Buyer
                $user, // Seller
                1, // appId
                $fun_id, // funId
                [[$article_id, $dozens_c, null]] // objects
            );

            $user->checkReloadEvent($amount);
            \Payutc\Bom\ReloadEvent::reloadPayIcam($amount, $user->getId(), $transaction->getId(), $this->application()->getId());
            $user->incCreditEvent($amount);

            return $amount;
        } catch (\Exception $e) {
            Log::error("Error during transaction for event reload (".$user->getId()." amount: $amount): ".$e->getMessage());
            throw new TransferException("Une erreur inconnue s'est produite pendant le virement".$e->getMessage());
            return $e.getMessage();
        }
    }
}
