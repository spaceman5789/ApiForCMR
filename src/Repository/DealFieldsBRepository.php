<?php

namespace App\Repository;

use App\Entity\DealFieldsB;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DealFieldsB>
 *
 * @method DealFieldsB|null find($id, $lockMode = null, $lockVersion = null)
 * @method DealFieldsB|null findOneBy(array $criteria, array $orderBy = null)
 * @method DealFieldsB[]    findAll()
 * @method DealFieldsB[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DealFieldsBRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DealFieldsB::class);
    }

//    /**
//     * @return DealFieldsB[] Returns an array of DealFieldsB objects
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

//    public function findOneBySomeField($value): ?DealFieldsB
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
