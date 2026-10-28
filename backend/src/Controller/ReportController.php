<?php

namespace App\Controller;

use App\Entity\JobActivity;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\JobActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports', name: 'api_reports_')]
class ReportController extends AbstractController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private JobActivityRepository $jobActivityRepository,
    ) {
    }

    #[Route('', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = $user->getId();

        $activities = $this->jobActivityRepository->findByUser($userId);
        $totalHours = 0.0;
        foreach ($activities as $activity) {
            $totalHours += $activity->getHoursSpent() ?? 0.0;
        }

        return $this->json([
            'totalApplications' => $this->applicationRepository->countForUser($userId),
            'applicationsWithInterview' => $this->applicationRepository->countWithInterviewForUser($userId),
            'totalActivities' => count($activities),
            'totalHours' => round($totalHours, 2),
            'weekly' => $this->weeklyBreakdown($activities),
        ]);
    }

    /**
     * @param JobActivity[] $activities
     *
     * @return list<array{weekStart: string, activityCount: int, hours: float}>
     */
    private function weeklyBreakdown(array $activities): array
    {
        $weeks = [];

        foreach ($activities as $activity) {
            $date = $activity->getDate();
            if (null === $date) {
                continue;
            }

            $weekStart = $date->modify('monday this week')->format('Y-m-d');
            if (!isset($weeks[$weekStart])) {
                $weeks[$weekStart] = [
                    'weekStart' => $weekStart,
                    'activityCount' => 0,
                    'hours' => 0.0,
                ];
            }

            ++$weeks[$weekStart]['activityCount'];
            $weeks[$weekStart]['hours'] += $activity->getHoursSpent() ?? 0.0;
        }

        ksort($weeks);

        foreach ($weeks as &$week) {
            $week['hours'] = round($week['hours'], 2);
        }
        unset($week);

        return array_values($weeks);
    }
}
