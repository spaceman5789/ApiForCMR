<?php

namespace App\Repository;
use App\Entity\DealTn;
use App\Entity\DealAn;
use App\Entity\DealSn;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Deal>
 *
 * @method Deal|null find($id, $lockMode = null, $lockVersion = null)
 * @method Deal|null findOneBy(array $criteria, array $orderBy = null)
 * @method Deal[]    findAll()
 * @method Deal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DealAnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DealAn::class);
    }

//    /**
//     * @return Deal[] Returns an array of Deal objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Deal
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

 
  


    public function getAllDealsDB(?int $page = null, ?int $pageSize = null, ?string $dateStart = null, ?string $dateEnd = null, string $userField = null, ?array $offersId = null, ?array $leadsId = null): array
    {
        // Requête pour la table deal_tn
        $qbTn = $this->getEntityManager()->createQueryBuilder()
            ->select('d')
            ->from(DealTn::class, 'd')
            ->where('d.client_id = :userField')
            ->setParameter('userField', $userField);

        // Requête pour la table deal_an
        $qbAn = $this->getEntityManager()->createQueryBuilder()
            ->select('d')
            ->from(DealAn::class, 'd')
            ->where('d.client_id = :userField')
            ->setParameter('userField', $userField);
        
         // Requête pour la table deal_sn
         $qbSn = $this->getEntityManager()->createQueryBuilder()
         ->select('d')
         ->from(DealSn::class, 'd')
         ->where('d.client_id = :userField')
         ->setParameter('userField', $userField);    

        // Appliquer les mêmes filtres sur les deux requêtes
        $filters = function ($qb) use ($dateStart, $dateEnd, $offersId, $leadsId) {
            if ($dateStart !== null && $dateEnd !== null) {
                $startDate = new \DateTime($dateStart);
                $endDate = (new \DateTime($dateEnd))->setTime(23, 59, 59);

                $qb->andWhere('d.date_created >= :dateStart')
                    ->andWhere('d.date_created < :dateEnd')
                    ->setParameter('dateStart', $startDate)
                    ->setParameter('dateEnd', $endDate);
            }

            if ($offersId !== null) {
                $qb->andWhere('d.offer_id IN (:offersId)')
                    ->setParameter('offersId', $offersId);
            }

            if ($leadsId !== null) {
                $qb->andWhere('d.lead_id IN (:leadsId)')
                    ->setParameter('leadsId', $leadsId);
            }

            return $qb;
        };

        $qbTn = $filters($qbTn);
        $qbAn = $filters($qbAn);
        $qbSn = $filters($qbSn);

        // Exécuter les deux requêtes
        $dealsTn = $qbTn->getQuery()->getResult();
        $dealsAn = $qbAn->getQuery()->getResult();
        $dealsSn = $qbSn->getQuery()->getResult();

        // Combiner les résultats
        $allDeals = array_merge($dealsTn, $dealsAn,$dealsSn);

        // Normaliser les résultats
        $normalizedDeals = array_map(function ($deal) {
            return $this->normalizeDeal($deal);
        }, $allDeals);

        $totalItems = count($normalizedDeals);

        if ($page !== null && $pageSize !== null) {
            // Pagination
            $offset = ($page - 1) * $pageSize;
            $pagedDeals = array_slice($normalizedDeals, $offset, $pageSize);

            return [
                'data' => $pagedDeals,
                'totalItems' => $totalItems,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => ceil($totalItems / $pageSize),
            ];
        }

        // Retour sans pagination
        return [
            'data' => $normalizedDeals,
            'totalItems' => $totalItems,
        ];
    }

    private function normalizeDeal($deal): array
    {
        $statusNames = $this->getStatusNames();
        $dateThreshold = new \DateTime('2024-11-16');
    
        $createdAt = $deal->getDateCreated();
        $orderId = $createdAt > $dateThreshold ? $deal->getOrderId() : $deal->getBitrixId();
        $stageId = $deal->getStatus();
        $statusName = $statusNames[$stageId] ?? 'Unknown';

        if ($statusName == 'Unknown') {
            file_put_contents('../var/log/error_db_status_unknown_' . date('Y-m-d') . '.log', json_encode($deal) . PHP_EOL, FILE_APPEND);
        }
    
        return [
            'order_ID' => $orderId,
            'Offer_ID' => $deal->getOfferId(),
            'Lead_ID' => $deal->getLeadId(),
            'Created_at' => $createdAt,
            'Status' => $statusName,
        ];
    }
    
    public function getStatusNames(): array
    {
        return [
            'NEW' => 'New lead', // MA et AN OLD
            'C6:NEW' => 'New lead', // Tunisie 
            'C17:NEW' => 'New lead', //ke
            'C21:NEW' => 'New lead', //LY
            'C25:NEW' => 'New lead', //SN
            'C29:NEW' => 'New lead', //AN


            'PREPARATION' => 'Processing',//MA et AN OLD
            'C6:PREPARATION' => 'Processing',//TN
            'C17:PREPARATION' => 'Processing', //ke
            'C21:PREPARATION' => 'Processing', //LY
            'C25:PREPARATION' => 'Processing', //SN
            'C29:PREPARATION' => 'Processing', //AN
            
            'C6:UC_00EJVW' => 'Processing', // call back TN
            'C17:PREPAYMENT_INVOIC' => 'Processing', // call back KENYA
            'UC_ROPKBH' => 'Processing', // CallBack AN Old
            'C21:PREPAYMENT_INVOIC' => 'Processing', // call back LY
            'C25:PREPAYMENT_INVOIC' => 'Processing', // call back SN
            'C29:PREPAYMENT_INVOIC' => 'Processing', // call back AN


            'C6:UC_9TTZDM' => 'Processing', // Verification TN
            'UC_11IBWS' => 'Processing', // Verification AN OLD
            'C17:EXECUTING' => 'Processing', // Verification KENYA
            'C21:EXECUTING' => 'Processing', // Verification LY
            'C25:EXECUTING' => 'Processing', // Verification SN
            'C29:EXECUTING' => 'Processing', // Verification AN


            'C6:UC_Q69K6V' => 'Processing', // Nom TN

            'LOSE' => 'Cancel',//MA et AN OLD
            'C6:LOSE' => 'Cancel',//TN
            'C17:LOSE' => 'Cancel',//KENYA
            'C21:LOSE' => 'Cancel',//LY
            'C25:LOSE' => 'Cancel',//SN
            'C29:LOSE' => 'Cancel',//AN
            

            'WON' => 'Approved',//MA et AN OLD
            'C6:WON' => 'Approved',//TN
            'C17:WON' => 'Approved',//KE
            'C21:WON' => 'Approved',//LY
            'C25:WON' => 'Approved',//SN
            'C29:WON' => 'Approved',//AN
            

            'PREPAYMENT_INVOICE' => 'Cancel', // Fusionner "Approvedtocg MA" avec "Cancel"
            'C6:EXECUTING' => 'Cancel', // Fusionner "Approvedtocg TN" avec "Cancel"
            'C17:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg KE" avec "Cancel"
            'C21:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg LY" avec "Cancel"
            'C25:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg SN" avec "Cancel"
            'C29:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg AN" avec "Cancel"

           
            'UC_GNK6HS' => 'SPAM',//MA SPAM
            'C6:UC_RS2SSM' => 'SPAM',//TN SPAM
            'C17:UC_X0677F' => 'SPAM', // KY
            'C21:1' => 'SPAM', // LY Spam
            'C21:UC_I2B1ZE' => 'SPAM',
            'C25:1' => 'SPAM', // SN Spam
            'C29:UC_8ZODDQ' => 'SPAM', // AN Spam   
            'C29:1' => 'SPAM', // AN Spam   
            'EXECUTING' => 'SPAM', // AN OLD

            'C6:UC_KS3JD1' => 'SPAM',//TN BLACK LIST
            'C17:UC_QQ7EZA' => 'SPAM',//KY BLACK LIST
            'C21:4' => 'SPAM', // LY BLACK LIST
            'C21:UC_J3ETBK' => 'SPAM',//LY BLACK LIST
            'C25:4' => 'SPAM', // SN BLACK LIST
            'C29:UC_3SQYKW' => 'SPAM', // AN BLACK LIST
            'C29:3' => 'SPAM', // AN BLACK LIST
            'CUC_7YUT0F' => 'SPAM',//AN BLACK LIST OLD


            'C6:UC_VFCGR5' => 'DOUBLE',// Double TN
            'UC_4Y0D8A' => 'DOUBLE',// Double MA
            'C17:UC_2446OA' => 'DOUBLE', // Double KE
            'C21:2' => 'DOUBLE', // Double LY
            'C25:2' => 'DOUBLE', // Double SN
            'C29:UC_6XBK1Y' => 'DOUBLE', // Double AN
            'C29:2' => 'DOUBLE', // Double AN 
            'UC_K0GLPN' => 'DOUBLE', // Double AN OLD


           
            'UC_VTU4BP' => 'Approved', //// Fusionner "Approved" avec "Approve+ MA"
            'C6:UC_9GP25Z' => 'Approved', // Fusionner "Approved" avec "Approve+ TN"
            'C17:UC_23YD73' => 'Approved', // Fusionner "Approved" avec "KE Approve+"
            'C21:3' => 'Approved' ,// Fusionner "Approved" avec "LY Approve+"
            'C25:3' => 'Approved' ,// Fusionner "Approved" avec "SN Approve+"
            'C29:UC_QJZYOG' => 'Approved', // Fusionner "Approved" avec "AN Approve+"
            'FINAL_INVOICE' => 'Approved' // Fusionner "Approved" avec "AN Approve+" OLD


        ];
    }
    public function createDealANBD(
        string $orderId,
        string $leadId,
        string $clientId,
        string $status,
        string $contactId,
        string $offerId,$jsonValue
    ): ?DealAn {
        try {
            $deal = new DealAn();
            // Format dates to 'YYYY-MM-DD HH:MM:SS'
            $formattedDateCreated = ($dateCreated ?? new \DateTime())->format('Y-m-d H:i:s');
            $formattedDateModified = ($dateModified ?? new \DateTime())->format('Y-m-d H:i:s');


            $deal->setOrderId($orderId)
                ->setLeadId($leadId)
                ->setClientId($clientId)
                ->setStatus($status)
                ->setContactId($contactId)
                ->setDateCreated(new \DateTime($formattedDateCreated))
                ->setDateModified(new \DateTime($formattedDateModified))
                ->setOfferId($offerId)
                ->setIsSent(false)
                ->setJsonValue($jsonValue);
    
            $this->_em->persist($deal);
            $this->_em->flush();
    
            return $deal;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Gérer l'erreur de contrainte unique ici, en retournant `null`
            return null;
        } catch (\Exception $e) {
            // Gérer les autres types d'erreurs si nécessaire
            return null;
        }
    }
    
    
 /**
     * Update stage in the database.
     */
    public function updateStageAN($deal,$stage,$bitrixId)
    {
       
        $deal->setIsSent(true);
        $deal->setStatus($stage)->setBitrixId($bitrixId);
        $this->_em->persist($deal);
        $this->_em->flush();
    }

    
    public function createDealANFromBitrix(
        string $clientId,
        string $status,
        string $bitrixId,
        string $offerId,
        $dateCreated,$dateModified,$leadID,$orderId
    ): ?DealAn {
        try {
            $deal = new DealAn();
          

            $deal->setClientId($clientId)
                ->setStatus($status)
                ->setDateCreated(new \DateTime( $dateCreated))
                ->setOfferId($offerId)
                ->setIsSent(true)
                ->setDateModified(new \DateTime($dateModified))
                ->setBitrixId($bitrixId)->setOrderId($orderId)
                ->setLeadId($leadID);
            $this->_em->persist($deal);
            $this->_em->flush();
            $this->_em->clear(); 
            return $deal;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Gérer l'erreur de contrainte unique ici, en retournant `null`
            return null;
        } catch (\Exception $e) {
            // Gérer les autres types d'erreurs si nécessaire
            return null;
        }
    }
   
    /**
     * Trouve un deal par lead_id et order_id
     */
    public function findByLeadAndOrder(string $leadId, string $orderId): ?DealAn
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.lead_id = :leadId')
            ->andWhere('d.order_id = :orderId')
            ->setParameter('leadId', $leadId)
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
