<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\JobActivity;
use PHPUnit\Framework\TestCase;

final class JobActivityTest extends TestCase
{
    public function testCreatedAtIsSetOnConstruction(): void
    {
        $activity = new JobActivity();

        $this->assertNotNull($activity->getCreatedAt());
    }

    public function testSettersAndGettersRoundTrip(): void
    {
        $activity = new JobActivity();
        $date = new \DateTimeImmutable('2026-09-21');

        $activity->setDescription('Submit resume to Acme');
        $activity->setDetails('Tailored the summary section.');
        $activity->setDate($date);
        $activity->setHoursSpent(1.5);

        $this->assertSame('Submit resume to Acme', $activity->getDescription());
        $this->assertSame('Tailored the summary section.', $activity->getDetails());
        $this->assertSame($date, $activity->getDate());
        $this->assertSame(1.5, $activity->getHoursSpent());
    }

    public function testDetailsCanBeNull(): void
    {
        $activity = new JobActivity();

        $this->assertNull($activity->getDetails());

        $activity->setDetails(null);

        $this->assertNull($activity->getDetails());
    }
}
