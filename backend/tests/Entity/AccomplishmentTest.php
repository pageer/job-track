<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Accomplishment;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class AccomplishmentTest extends TestCase
{
    public function testSettersAndGettersRoundTrip(): void
    {
        $accomplishment = new Accomplishment();
        $user = new User();
        $date = new \DateTimeImmutable('2026-03-15');

        $accomplishment->setUser($user);
        $accomplishment->setDate($date);
        $accomplishment->setCompany('Acme Inc');
        $accomplishment->setDescription('Shipped the billing rewrite.');

        $this->assertSame($user, $accomplishment->getUser());
        $this->assertSame($date, $accomplishment->getDate());
        $this->assertSame('Acme Inc', $accomplishment->getCompany());
        $this->assertSame('Shipped the billing rewrite.', $accomplishment->getDescription());
    }
}
