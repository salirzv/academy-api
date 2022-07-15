<?php

namespace App\Repository;

use App\Entity\DownloadToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DownloadToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method DownloadToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method DownloadToken[]    findAll()
 * @method DownloadToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DownloadTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DownloadToken::class);
    }

    // /**
    //  * @return DownloadToken[] Returns an array of DownloadToken objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DownloadToken
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
