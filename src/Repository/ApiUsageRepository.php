<?php

namespace App\Repository;

use App\Entity\ApiUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiUsage>
 *
 * @method ApiUsage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiUsage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApiUsage[]    findAll()
 * @method ApiUsage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiUsage::class);
    }

//    /**
//     * @return ApiUsage[] Returns an array of ApiUsage objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ApiUsage
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
