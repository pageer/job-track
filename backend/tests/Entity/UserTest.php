<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testGetRolesIncludesCustomRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testGetRolesDeduplicatesRoleUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_USER']);

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testIsAdmin(): void
    {
        $user = new User();

        $this->assertFalse($user->isAdmin());

        $user->setRoles(['ROLE_ADMIN']);

        $this->assertTrue($user->isAdmin());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->assertSame('user@example.com', $user->getUserIdentifier());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $user = new User();

        $this->assertNotNull($user->getCreatedAt());
    }
}
