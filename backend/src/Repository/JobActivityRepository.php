<?php

namespace App\Repository;

use App\Entity\JobActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobActivity>
 */
class JobActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobActivity::class);
    }

    /**
     * @return JobActivity[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
