<?php

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Person>
 */
class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    /**
     * @return Person[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByNameAndCompany(int $userId, string $name, ?int $companyId): ?Person
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.user = :userId')
            ->andWhere('p.name = :name')
            ->setParameter('userId', $userId)
            ->setParameter('name', $name);

        if (null === $companyId) {
            $qb->andWhere('p.company IS NULL');
        } else {
            $qb->andWhere('p.company = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $qb->getQuery()
            ->getOneOrNullResult();
    }
}
