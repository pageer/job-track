<?php

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * @return Contact[]
     */
    public function findByInterview(int $interviewId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.interview = :interviewId')
            ->setParameter('interviewId', $interviewId)
            ->getQuery()
            ->getResult();
    }

    public function findForPersonAndDate(int $personId, \DateTimeImmutable $date, ?string $description): ?Contact
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->andWhere('c.person = :personId')
            ->andWhere('c.date = :date')
            ->setParameter('personId', $personId)
            ->setParameter('date', $date);

        if (null === $description) {
            $queryBuilder->andWhere('c.description IS NULL');
        } else {
            $queryBuilder->andWhere('c.description = :description')
                ->setParameter('description', $description);
        }

        return $queryBuilder->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
