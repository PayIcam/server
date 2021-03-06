<?php
/**
 * Reversement
 *
 * Functions related to reversement table
 * Table: t_reversement_rev
 */

namespace Payutc\Bom;

use \Payutc\Db\Dbal;
use \Payutc\Exception\ReversementNotFound;
use \Payutc\Exception\UpdateFailed;
use \Payutc\Bom\Purchase;

class Reversement
{
    public $id;
    public $funId;
    public $step; // 'W', 'A', 'V'
    public $created;
    public $updated = null;
    public $usrAsk;
    public $usrDone = null;
    public $amount = null;
    public $taux = null;
    public $frais = null;

    public function __construct($funId=null, $usrId=null) {
        $this->funId = $funId;
        $this->usrAsk = $usrId;
        $this->step = 'W';
        if($funId) {
            $this->amount = self::getNotReversed($funId);
        }
        $this->created = new \DateTime();
    }

    public function insert() {
        $conn = Dbal::conn();
        $conn->insert('t_reversement_rev', array(
                'fun_id' => $this->funId,
                'rev_step' => $this->step,
                'rev_date_created' => $this->created,
                'rev_date_updated' => $this->updated,
                'usr_id_ask' => $this->usrAsk,
                'usr_id_done' => $this->usrDone,
                'rev_amount' => $this->amount,
                'rev_taux' => $this->taux,
                'rev_frais' => $this->frais
                ),
                array(
                    "integer",
                    "string",
                    "datetime",
                    "datetime",
                    "integer",
                    "integer",
                    "integer",
                    "integer",
                    "integer")
            );
        return $conn->lastInsertId();
    }


    public function update($usrId) {
        $qb = Dbal::createQueryBuilder();
        $qb->update('t_reversement_rev', 'rev')
            ->set('rev_date_updated', ':rev_date_updated')
            ->set('usr_id_done', ':usr_id_done')
            ->set('rev_taux', ':rev_taux')
            ->set('rev_frais', ':rev_frais')
            ->set('rev_step', ':rev_step')
            ->where('rev_id = :rev_id')
            ->andWhere('rev_step = :step')
            ->setParameter('rev_id', $this->id, "integer")
            ->setParameter('step', 'W', 'string')
            ->setParameter('rev_date_updated', new \DateTime(), "datetime")
            ->setParameter('usr_id_done', $usrId, 'integer')
            ->setParameter('rev_taux', $this->taux, 'integer')
            ->setParameter('rev_frais', $this->frais, 'integer')
            ->setParameter('rev_step', $this->step, 'string');
        $affectedRows = $qb->execute();
        if ($affectedRows != 1){
            Log::debug("Reversement($this->id): no lines updated");
            throw new UpdateFailed("Impossible de changer l'état du reversement");
        }
    }

    // Obtenir le montant total reversé
    public static function getTotal($funId = null) {
        $qb = Dbal::createQueryBuilder();
        $qb->select('sum(rev_amount) as total')
            ->from('t_reversement_rev', 'rev')
            ->where("rev.rev_step = 'V'");

        if($funId != null) {
            $qb->andWhere('rev.fun_id = :fun_id')->setParameter('fun_id', $funId);
        }

        $result = $qb->execute()->fetch();
        return $result['total'];
    }

    // Obtenir le montant total non reversé.
    public static function getNotReversed($funId = null) {
        return Purchase::getRevenue($funId) - self::getTotal($funId);
    }

    // Obtenir le montant total en attente de reversement.
    public static function getWait($funId = null) {
        $qb = Dbal::createQueryBuilder();
        $qb->select('sum(rev_amount) as total')
            ->from('t_reversement_rev', 'rev')
            ->where("rev.rev_step = 'W'");

        if($funId != null) {
            $qb->andWhere('rev.fun_id = :fun_id')->setParameter('fun_id', $funId);
        }

        $result = $qb->execute()->fetch();
        return $result['total'];
    }

    // Retourne le dernier reversement effectué pour une fundation donné.
    public static function getLast($funId, $step='V') {
        $qb = self::getQbBase()
            ->where('rev.fun_id = :fun_id')
            ->andWhere("rev.rev_step = :step")
            ->setParameter('fun_id', $funId)
            ->setParameter('step', $step)
            ->orderBy('rev.rev_date_updated', 'DESC')
            ->setMaxResults(1);

        $rev = self::getByQb($qb);
        if (count($rev) == 0) {
            return null;
        } else {
            return $rev[0];
        }
    }

    public static function getById($revId, $funId=null) {
        $qb = self::getQbBase()
            ->where('rev.rev_id = :rev_id')
            ->setParameter('rev_id', $revId);

        if($funId != null) {
            $qb->andWhere('rev.fun_id = :fun_id')->setParameter('fun_id', $funId);
        }

        $ret = self::getByQb($qb);
        if (count($ret) != 1) {
            throw new ReversementNotFound("Le reversement n'existe pas");
        }
        $reversement = $ret[0];


        // Récupérer la date de début des transactions prise en compte par ce reversement
        $qb = self::getQbBase()
            ->where('rev.fun_id = :fun_id')
            ->andWhere("rev.rev_date_created < :rev_date_created")
            ->setParameter('fun_id', $reversement->funId)
            ->setParameter('rev_date_created', $reversement->created)
            ->orderBy('rev.rev_date_created', 'DESC')
            ->setMaxResults(1);

        $ret = self::getByQb($qb);
        if (count($ret) != 1){
            $reversement->startDate = self::getFirstTransactionDate($reversement);
        } else {
            $reversement->startDate = $ret[0]->created;
        }

        return $reversement;
    }

    private static function getFirstTransactionDate($reversement) {
        try {
            // Récupérer la date de début des transactions prise en compte par ce reversement
            $qb = Dbal::createQueryBuilder()
                ->select('*')
                ->from('t_transaction_tra', 'tra')
                ->where('tra.fun_id = :fun_id')
                ->setParameter('fun_id', $reversement->funId)
                ->orderBy('tra.tra_date', 'ASC')
                ->setMaxResults(1);
            $query = $qb->execute();
            $count = $query->rowCount();

            $don = $query->fetch();
            return $don['tra_date'];
        } catch (Exception $e) {
            return "1992";
        }
    }

    public static function getAll($funId=null, $step='V') {
        $qb = self::getQbBase()
            ->where("rev.rev_step = :step")
            ->setParameter('step', $step)
            ->orderBy('rev.rev_date_updated', 'DESC');

        if($funId) {
            $qb->andWhere('rev.fun_id = :fun_id')
                ->setParameter('fun_id', $funId);
        }
        $reversements = self::getByQb($qb);

        // On veut ajouter à chaque reversement la date où il a commencé... id est celle du précédent
        // ATTENTION: Je suppose que l'on a pas en une seconde à une même date une demande de reversement & une nouvelle transaction ...
        $prevDate = [];
        foreach (array_reverse($reversements) as $reversement) {
            if (!isset($prevDate[$reversement->funId])) {
                $reversement->startDate = self::getFirstTransactionDate($reversement);
            } else {
                $reversement->startDate = $prevDate[$reversement->funId];
            }
            $prevDate[$reversement->funId] = $reversement->created;
        }

        return $reversements;
    }

    protected static function getQbBase(){
        return Dbal::createQueryBuilder()
            ->select('*')
            ->from('t_reversement_rev', 'rev');
    }

    protected static function getByQb($qb){
        $query = $qb->execute();

        $count = $query->rowCount();

        $ret = array();
        while($don = $query->fetch()) {
            $ret[] = self::fromArray($don);
        }
        return $ret;
    }

    protected static function fromArray($don){
        $reversement = new Reversement();
        $reversement->id = $don['rev_id'];
        $reversement->funId = $don['fun_id'];
        $reversement->step = $don['rev_step'];
        $reversement->created = $don['rev_date_created'];
        $reversement->updated = $don['rev_date_updated'];
        $reversement->usrAsk = $don['usr_id_ask'];
        $reversement->usrDone = $don['usr_id_done'];
        $reversement->amount = $don['rev_amount'];
        $reversement->taux = $don['rev_taux'];
        $reversement->frais = $don['rev_frais'];

        return $reversement;
    }

}