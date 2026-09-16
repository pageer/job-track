<?php

namespace App\Repository;

use App\Entity\OneDriveToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OneDriveToken>
 */
class OneDriveTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OneDriveToken::class);
    }

    public function findForUser(int $userId): ?OneDriveToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
