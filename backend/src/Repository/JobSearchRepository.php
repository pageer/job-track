<?php

namespace App\Repository;

use App\Entity\JobSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobSearch>
 */
class JobSearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobSearch::class);
    }

    /**
     * @return JobSearch[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('j.startDate', 'DESC')
            ->addOrderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return JobSearch[] Returns an array of JobSearch objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('j')
    //            ->andWhere('j.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('j.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }
}
