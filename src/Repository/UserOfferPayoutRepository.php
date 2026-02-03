<?php

namespace App\Repository;

use App\Entity\UserOfferPayout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserOfferPayout>
 *
 * @method UserOfferPayout|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserOfferPayout|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserOfferPayout[]    findAll()
 * @method UserOfferPayout[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserOfferPayoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserOfferPayout::class);
    }

//    /**
//     * @return UserOfferPayout[] Returns an array of UserOfferPayout objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?UserOfferPayout
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
