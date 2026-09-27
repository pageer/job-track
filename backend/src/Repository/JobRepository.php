<?php

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    /**
     * @return Job[]
     */
    public function findByJobSearch(int $jobSearchId): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.jobSearch = :jobSearchId')
            ->setParameter('jobSearchId', $jobSearchId)
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Job[]
     */
    public function findAllWithSearch(): array
    {
        return $this->createQueryBuilder('j')
            ->addSelect('js')
            ->join('j.jobSearch', 'js')
            ->orderBy('j.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
