<?php

namespace App\Service;

use App\Entity\JobSearch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserSetupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Creates a user (and their default job search) and flushes the transaction.
     *
     * @param list<string> $roles
     */
    public function createUser(string $name, string $email, string $plainPassword, array $roles = []): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $search = new JobSearch();
        $search->setName('Job Search');
        $search->setStartDate(new \DateTimeImmutable('today'));

        $this->entityManager->persist($user);
        $user->addJobSearch($search);
        $this->entityManager->persist($search);

        $this->entityManager->flush();

        return $user;
    }
}
