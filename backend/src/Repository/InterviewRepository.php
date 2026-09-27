<?php

namespace App\Repository;

use App\Entity\Interview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Interview>
 */
class InterviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interview::class);
    }

    /**
     * @return Interview[]
     */
    public function findAllWithJob(): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('a', 'j', 'js')
            ->join('i.application', 'a')
            ->join('a.job', 'j')
            ->join('j.jobSearch', 'js')
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
