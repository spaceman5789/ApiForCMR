<?php

namespace App\Repository;

use App\Entity\Deal;
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
class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
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

    /**
     * Check if there is a deal created within one month with the same contact_id and offer_id
     *
     * @param string $contactId
     * @param string $offerId
     * @return bool
     */
    public function hasRecentDeal(string $contactId, string $offerId): bool
    {
        $oneMonthAgo = new \DateTime('-1 month');

        $queryBuilder = $this->createQueryBuilder('d')
            ->select('d.id')
            ->where('d.contact_id = :contactId')
            ->andWhere('d.offer_id = :offerId')
            ->andWhere('d.date_created >= :oneMonthAgo')
            ->setParameter('contactId', $contactId)
            ->setParameter('offerId', $offerId)
            ->setParameter('oneMonthAgo', $oneMonthAgo)
            ->setMaxResults(1);
        return (bool) $queryBuilder->getQuery()->getOneOrNullResult();
    }
    public function createDeal(
        string $orderId,
        string $leadId,
        string $clientId,
        string $status,
        string $contactId,
        string $offerId,
        $jsonValue
    ): ?Deal {
        try {
            $deal = new Deal();
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

    public function findUnsentDealsTN(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.is_sent = :is_sent')
            ->andWhere('d.offer_id LIKE :offer_prefix')
            ->setParameter('is_sent', false)
            ->setParameter('offer_prefix', '2%') // Match offer_id starting with '2'
            ->getQuery()
            ->getResult();
    }

    public function findUnsentDealsSN(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.is_sent = :is_sent')
            ->andWhere('d.offer_id LIKE :offer_prefix')
            ->setParameter('is_sent', false)
            ->setParameter('offer_prefix', '6%') // Match offer_id starting with '6'
            ->getQuery()
            ->getResult();
    }

    public function findUnsentDealsAN(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.is_sent = :is_sent')
            ->andWhere('d.offer_id LIKE :offer_prefix')
            ->setParameter('is_sent', false)
            ->setParameter('offer_prefix', '3%') // Match offer_id starting with '3'
            ->getQuery()
            ->getResult();
    }

    public function findUnsentDealsIQ(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.is_sent = :is_sent')
            ->andWhere('d.offer_id LIKE :offer_prefix')
            ->setParameter('is_sent', false)
            ->setParameter('offer_prefix', '5%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marks deals as sent in the database.
     */
    public function markDealAsSent($deal, $id)
    {

        $deal->setIsSent(true);
        $deal->setBitrixId($id);
        $this->_em->persist($deal);
        $this->_em->flush();
    }

    public function getAllDealsDB(?int $page = null, ?int $pageSize = null, ?string $dateStart = null, ?string $dateEnd = null, string $userField = null, ?array $offersId = null, ?array $leadsId = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.client_id = :userField')
            ->setParameter('userField', $userField);

        // Apply date filters if provided
        if ($dateStart !== null && $dateEnd !== null) {


            $startDate = new \DateTime($dateStart);
            $endDate = (new \DateTime($dateEnd))->setTime(23, 59, 59);


            $qb->andWhere('d.date_created >= :dateStart')
                ->andWhere('d.date_created < :dateEnd')
                ->setParameter('dateStart', $dateStart)
                ->setParameter('dateEnd', $endDate);
        }

        // Filter by offers and leads if provided
        if ($offersId !== null) {
            $qb->andWhere('d.offer_id IN (:offersId)')
                ->setParameter('offersId', $offersId);
        }

        if ($leadsId !== null) {
            $qb->andWhere('d.lead_id IN (:leadsId)')
                ->setParameter('leadsId', $leadsId);
        }

        // Check if pagination is requested
        if ($page !== null && $pageSize !== null) {
            $firstResult = ($page - 1) * $pageSize;
            $qb->setFirstResult($firstResult)
                ->setMaxResults($pageSize);

            // Use Paginator for paginated results
            $paginator = new Paginator($qb, true);

            $normalizedDeals = [];
            foreach ($paginator as $deal) {
                $normalizedDeals[] = $this->normalizeDeal($deal);
            }

            $totalItems = count($paginator);

            return [
                'data' => $normalizedDeals,
                'totalItems' => $totalItems,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => ceil($totalItems / $pageSize),
            ];
        } else {
            // Retrieve all results without pagination
            $allDeals = $qb->getQuery()->getResult();
            $normalizedDeals = [];

            foreach ($allDeals as $deal) {
                $normalizedDeals[] = $this->normalizeDeal($deal);
            }

            $totalItems = count($normalizedDeals);

            return [
                'data' => $normalizedDeals,
                'totalItems' => $totalItems,
            ];
        }
    }

    private function normalizeDeal($deal): array
    {
        $statusNames = $this->getStatusNames();
        $dateThreshold = new \DateTime('2024-11-13');

        $createdAt = $deal->getDateCreated();
        $orderId = $createdAt > $dateThreshold ? $deal->getOrderId() : $deal->getBitrixId();
        $stageId = $deal->getStatus();
        $statusName = $statusNames[$stageId] ?? 'Unknown';

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
            'NEW' => 'New lead',
            'C6:NEW' => 'New lead', // Tunisie 
            'C17:NEW' => 'New lead', //ke
            'C21:NEW' => 'New lead', //LY
            'C25:NEW' => 'New lead', //SN

            'PREPARATION' => 'Processing',
            'C6:PREPARATION' => 'Processing',
            'C17:PREPARATION' => 'Processing', //ke
            'C21:PREPARATION' => 'Processing', //LY
            'C25:PREPARATION' => 'Processing', //SN

            'C6:UC_00EJVW' => 'Processing', // call back TN
            'C17:PREPAYMENT_INVOIC' => 'Processing', // call back KENYA
            'UC_ROPKBH' => 'Processing', // CallBack AN
            'C21:PREPAYMENT_INVOIC' => 'Processing', // call back LY
            'C25:PREPAYMENT_INVOIC' => 'Processing', // call back SN

            'C6:UC_9TTZDM' => 'Processing', // Verification TN
            'UC_11IBWS' => 'Processing', // Verification AN
            'C17:EXECUTING' => 'Processing', // Verification KENYA
            'C21:EXECUTING' => 'Processing', // Verification LY
            'C25:EXECUTING' => 'Processing', // Verification SN


            'C6:UC_Q69K6V' => 'Processing', // Nom TN

            'LOSE' => 'Cancel',
            'C6:LOSE' => 'Cancel',
            'C17:LOSE' => 'Cancel', //KENYA
            'C21:LOSE' => 'Cancel', //LY
            'C25:LOSE' => 'Cancel', //SN


            'WON' => 'Approved', //MA AN
            'C6:WON' => 'Approved', //TN
            'C17:WON' => 'Approved', //KE
            'C21:WON' => 'Approved', //LY
            'C25:WON' => 'Approved', //SN

            'PREPAYMENT_INVOICE' => 'Cancel', // Fusionner "Approvedtocg MA/AN" avec "Cancel"
            'C6:EXECUTING' => 'Cancel', // Fusionner "Approvedtocg TN" avec "Cancel"
            'C17:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg KE" avec "Cancel"
            'C21:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg LY" avec "Cancel"
            'C25:FINAL_INVOICE' => 'Cancel', // Fusionner "Approvedtocg SN" avec "Cancel"

            'UC_GNK6HS' => 'SPAM',
            'C6:UC_RS2SSM' => 'SPAM',
            'C6:UC_KS3JD1' => 'SPAM', //TN BLACK LIST
            'C17:UC_QQ7EZA' => 'SPAM', //KY BLACK LIST
            'CUC_7YUT0F' => 'SPAM', //AN BLACK LIST
            'EXECUTING' => 'SPAM', // AN
            'C17:UC_X0677F' => 'SPAM', // KY
            'C21:1' => 'SPAM', // LY Spam
            'C21:UC_I2B1ZE' => 'SPAM',
            'C25:1' => 'SPAM', // SN Spam
            'C21:4' => 'SPAM', // LY BLACK LIST
            'C25:4' => 'SPAM', // SN BLACK LIST
            'C21:UC_J3ETBK' => 'SPAM',//LY BLACK LIST


            'C6:UC_VFCGR5' => 'DOUBLE', // Double TN
            'UC_4Y0D8A' => 'DOUBLE', // Double MA
            'UC_K0GLPN' => 'DOUBLE', // Double AN
            'C17:UC_2446OA' => 'DOUBLE', // Double KE
            'C21:2' => 'DOUBLE', // Double LY
            'C25:2' => 'DOUBLE', // Double SN

            'UC_VTU4BP' => 'Approved', //// Fusionner "Approved" avec "Approve+ MA"
            'C6:UC_9GP25Z' => 'Approved', // Fusionner "Approved" avec "Approve+ TN"
            'FINAL_INVOICE' => 'Approved', // Fusionner "Approved" avec "AN Approve+"
            'C17:UC_23YD73' => 'Approved', // Fusionner "Approved" avec "KE Approve+"
            'C21:3' => 'Approved', // Fusionner "Approved" avec "LY Approve+"
            'C25:3' => 'Approved' // Fusionner "Approved" avec "SN Approve+"

        ];
    }
}
