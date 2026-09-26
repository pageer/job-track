<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Todo;
use PHPUnit\Framework\TestCase;

final class TodoTest extends TestCase
{
    public function testCreatedAtIsSetOnConstruction(): void
    {
        $todo = new Todo();

        $this->assertNotNull($todo->getCreatedAt());
    }

    public function testSettersAndGettersRoundTrip(): void
    {
        $todo = new Todo();
        $targetDate = new \DateTimeImmutable('2026-09-25');

        $todo->setDescription('Update resume');
        $todo->setDetails('Add the new project.');
        $todo->setTargetDate($targetDate);

        $this->assertSame('Update resume', $todo->getDescription());
        $this->assertSame('Add the new project.', $todo->getDetails());
        $this->assertSame($targetDate, $todo->getTargetDate());
    }

    public function testDetailsCanBeNull(): void
    {
        $todo = new Todo();

        $this->assertNull($todo->getDetails());

        $todo->setDetails(null);

        $this->assertNull($todo->getDetails());
    }
}
