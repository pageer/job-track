<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Job;
use App\Entity\JobNote;
use PHPUnit\Framework\TestCase;

final class JobNoteTest extends TestCase
{
    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $note = new JobNote();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($note->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $note->getCreatedAt());
        $this->assertLessThanOrEqual($after, $note->getCreatedAt());
    }

    public function testContentIsMutable(): void
    {
        $note = new JobNote();
        $note->setContent('Follow up next week');

        $this->assertSame('Follow up next week', $note->getContent());
    }

    public function testJobAssociation(): void
    {
        $job = new Job();
        $note = new JobNote();
        $note->setContent('A note');

        $job->addNote($note);

        $this->assertSame($job, $note->getJob());
        $this->assertTrue($job->getNotes()->contains($note));

        $job->removeNote($note);

        $this->assertNull($note->getJob());
        $this->assertFalse($job->getNotes()->contains($note));
    }
}
