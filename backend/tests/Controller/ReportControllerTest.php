<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ReportController;
use App\Entity\JobActivity;
use App\Repository\ApplicationRepository;
use App\Repository\JobActivityRepository;
use Symfony\Component\HttpFoundation\Response;

final class ReportControllerTest extends ControllerTestCase
{
    public function testSummaryReturnsTotalsAndWeeklyBreakdown(): void
    {
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('countForUser')->with(1)->willReturn(12);
        $applicationRepository->method('countWithInterviewForUser')->with(1)->willReturn(4);

        $activityRepository = $this->createMock(JobActivityRepository::class);
        $activityRepository->method('findByUser')->with(1)->willReturn([
            $this->activity(1, '2026-09-20', 2.5),
            $this->activity(2, '2026-09-21', 1.25),
            $this->activity(3, '2026-09-22', 1.0),
        ]);

        $controller = new ReportController($applicationRepository, $activityRepository);
        $this->bindController($controller);

        $response = $controller->summary();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(12, $data['totalApplications']);
        $this->assertSame(4, $data['applicationsWithInterview']);
        $this->assertSame(3, $data['totalActivities']);
        $this->assertEqualsWithDelta(4.75, (float) $data['totalHours'], 0.001);

        $this->assertCount(2, $data['weekly']);
        $this->assertSame('2026-09-14', $data['weekly'][0]['weekStart']);
        $this->assertSame(1, $data['weekly'][0]['activityCount']);
        $this->assertEqualsWithDelta(2.5, (float) $data['weekly'][0]['hours'], 0.001);
        $this->assertSame('2026-09-21', $data['weekly'][1]['weekStart']);
        $this->assertSame(2, $data['weekly'][1]['activityCount']);
        $this->assertEqualsWithDelta(2.25, (float) $data['weekly'][1]['hours'], 0.001);
    }

    public function testSummaryUsesTheAuthenticatedUserAndHandlesNoData(): void
    {
        $this->loginAs(7);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('countForUser')->with(7)->willReturn(0);
        $applicationRepository->method('countWithInterviewForUser')->with(7)->willReturn(0);

        $activityRepository = $this->createMock(JobActivityRepository::class);
        $activityRepository->method('findByUser')->with(7)->willReturn([]);

        $controller = new ReportController($applicationRepository, $activityRepository);
        $this->bindController($controller);

        $response = $controller->summary();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame(0, $data['totalApplications']);
        $this->assertSame(0, $data['applicationsWithInterview']);
        $this->assertSame(0, $data['totalActivities']);
        $this->assertEquals(0, $data['totalHours']);
        $this->assertSame([], $data['weekly']);
    }

    private function activity(int $id, string $date, float $hours): JobActivity
    {
        $activity = new JobActivity();
        $this->setId($activity, $id);
        $activity->setUser($this->user);
        $activity->setDescription('Activity ' . $id);
        $activity->setDate(new \DateTimeImmutable($date));
        $activity->setHoursSpent($hours);

        return $activity;
    }
}
