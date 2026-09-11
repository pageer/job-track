<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UserSetupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserSetupServiceTest extends TestCase
{
    private function createService(
        ?UserPasswordHasherInterface $hasher = null,
        ?EntityManagerInterface $em = null,
    ): UserSetupService {
        return new UserSetupService(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $hasher ?? $this->createMock(UserPasswordHasherInterface::class),
        );
    }

    public function testCreateUserSetsFieldsAndHashesPassword(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed-secret');

        $service = $this->createService(hasher: $hasher);

        $user = $service->createUser('Alice', 'alice@example.com', 'secret', ['ROLE_ADMIN']);

        $this->assertSame('Alice', $user->getName());
        $this->assertSame('alice@example.com', $user->getEmail());
        $this->assertSame('hashed-secret', $user->getPassword());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testCreateUserPersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService(em: $em);

        $user = $service->createUser('Bob', 'bob@example.com', 'password123');

        $this->assertSame('Bob', $user->getName());
    }

    public function testCreateUserCreatesDefaultJobSearch(): void
    {
        $service = $this->createService();

        $user = $service->createUser('Carol', 'carol@example.com', 'pass1234');

        $searches = $user->getJobSearches();
        $this->assertCount(1, $searches);

        $search = $searches->first();
        $this->assertSame('Job Search', $search->getName());
        $this->assertSame(
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            $search->getStartDate()->format('Y-m-d'),
        );
        $this->assertSame($user, $search->getUser());
    }
}
