<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Job;
use App\Entity\User;
use App\Enum\ResumeKind;
use App\Enum\JobStatus;
use App\Repository\ApplicationRepository;
use App\Repository\JobRepository;
use App\Service\FileUploader;
use App\Service\HtmlSanitizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class ApplicationController extends AbstractController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private JobRepository $jobRepository,
        private FileUploader $fileUploader,
        private HtmlSanitizer $htmlSanitizer,
    ) {
    }

    #[Route('/api/jobs/{jobId}/application', name: 'api_applications_show', methods: ['GET'])]
    public function show(int $jobId): JsonResponse
    {
        $job = $this->findOwnedJob($jobId);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        $application = $job->getApplication();
        if (null === $application) {
            return $this->json(null, Response::HTTP_NOT_FOUND);
        }

        return $this->json($application, 200, [], ['groups' => ['application.read', 'interview.read']]);
    }

    #[Route('/api/jobs/{jobId}/application', name: 'api_applications_create', methods: ['POST'])]
    public function create(int $jobId, Request $request): JsonResponse
    {
        $job = $this->findOwnedJob($jobId);
        if (null === $job) {
            return $this->json(['error' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        if (null !== $job->getApplication()) {
            return $this->json(['error' => 'This job already has an application.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();

        $application = new Application();
        $application->setJob($job);

        $resumeError = $this->applyResumeFromData($application, $data);
        if (null !== $resumeError) {
            return $this->json(['error' => $resumeError], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('coverLetterHtml', $data)) {
            $raw = $this->nullableString($data['coverLetterHtml']);
            $application->setCoverLetterHtml(null !== $raw ? $this->htmlSanitizer->sanitize($raw) : null);
        }
        if (array_key_exists('notes', $data)) {
            $application->setNotes($this->nullableString($data['notes']));
        }
        if (array_key_exists('actionDate', $data)) {
            $application->setActionDate($this->parseDate($data['actionDate']));
        }

        if ($job->getStatus() === JobStatus::Investigating) {
            $job->setStatus(JobStatus::Applied);
        }

        $this->applicationRepository->getEntityManager()->persist($application);
        $this->applicationRepository->getEntityManager()->flush();

        return $this->json($application, Response::HTTP_CREATED, [], ['groups' => ['application.read', 'interview.read']]);
    }

    #[Route('/api/applications/{id}', name: 'api_applications_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $application = $this->findOwnedApplication($id);
        if (null === $application) {
            return $this->json(['error' => 'Application not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('resumeKind', $data)) {
            $resumeError = $this->applyResumeFromData($application, $data);
            if (null !== $resumeError) {
                return $this->json(['error' => $resumeError], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (array_key_exists('coverLetterHtml', $data)) {
            $raw = $this->nullableString($data['coverLetterHtml']);
            $application->setCoverLetterHtml(null !== $raw ? $this->htmlSanitizer->sanitize($raw) : null);
        }
        if (array_key_exists('notes', $data)) {
            $application->setNotes($this->nullableString($data['notes']));
        }
        if (array_key_exists('actionDate', $data)) {
            $application->setActionDate($this->parseDate($data['actionDate']));
        }

        $this->applicationRepository->getEntityManager()->flush();

        return $this->json($application, 200, [], ['groups' => ['application.read', 'interview.read']]);
    }

    #[Route('/api/applications/{id}', name: 'api_applications_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $application = $this->findOwnedApplication($id);
        if (null === $application) {
            return $this->json(['error' => 'Application not found.'], Response::HTTP_NOT_FOUND);
        }

        if (ResumeKind::File === $application->getResumeKind() && null !== $application->getResumeFilePath()) {
            $this->fileUploader->remove($application->getResumeFilePath());
        }

        $this->applicationRepository->getEntityManager()->remove($application);
        $this->applicationRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/applications/{id}/resume-file', name: 'api_applications_upload_file', methods: ['POST'])]
    public function uploadFile(int $id, Request $request): JsonResponse
    {
        $application = $this->findOwnedApplication($id);
        if (null === $application) {
            return $this->json(['error' => 'Application not found.'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (null === $file) {
            return $this->json(['error' => 'No file uploaded.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $application->getResumeFilePath()) {
            $this->fileUploader->remove($application->getResumeFilePath());
        }

        $fileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $path = $this->fileUploader->upload($file, 'applications', $application->getJob()?->getJobSearch()?->getUser()?->getId() ?? '0');
        $application->setResumeKind(ResumeKind::File);
        $application->setResumeFilePath($path);
        $application->setResumeFileName($fileName);
        $application->setResumeMimeType($mimeType);
        $application->setResumeFileSize($fileSize);
        $application->setResumeLinkUrl(null);

        $this->applicationRepository->getEntityManager()->flush();

        return $this->json($application, 200, [], ['groups' => ['application.read', 'interview.read']]);
    }

    #[Route('/api/applications/{id}/resume/download', name: 'api_applications_download_resume', methods: ['GET'])]
    public function downloadResume(int $id): Response
    {
        $application = $this->findOwnedApplication($id);
        if (null === $application) {
            return $this->json(['error' => 'Application not found.'], Response::HTTP_NOT_FOUND);
        }

        if (ResumeKind::File !== $application->getResumeKind() || null === $application->getResumeFilePath()) {
            return $this->json(['error' => 'This application has no attached resume file.'], Response::HTTP_CONFLICT);
        }

        $path = $this->fileUploader->resolve($application->getResumeFilePath());
        if (!is_file($path)) {
            return $this->json(['error' => 'The stored file could not be found.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $application->getResumeFileName() ?? basename($path)
        );
        if (null !== $application->getResumeMimeType()) {
            $response->headers->set('Content-Type', $application->getResumeMimeType());
        }

        return $response;
    }

    /**
     * Applies the resume fields from a JSON payload. Supports:
     *   resumeKind: "link"  -> requires resumeLinkUrl
     *   resumeKind: null    -> clears the current resume (removes the file from disk)
     *
     * @param array<string, mixed> $data
     */
    private function applyResumeFromData(Application $application, array $data): ?string
    {
        $kind = $data['resumeKind'] ?? null;

        if (null === $kind || '' === $kind) {
            if (ResumeKind::File === $application->getResumeKind() && null !== $application->getResumeFilePath()) {
                $this->fileUploader->remove($application->getResumeFilePath());
            }
            $application->setResumeKind(null);
            $application->setResumeFilePath(null);
            $application->setResumeFileName(null);
            $application->setResumeMimeType(null);
            $application->setResumeFileSize(null);
            $application->setResumeLinkUrl(null);

            return null;
        }

        if (ResumeKind::Link->value === $kind) {
            $linkUrl = trim((string) ($data['resumeLinkUrl'] ?? ''));
            if ('' === $linkUrl || false === filter_var($linkUrl, FILTER_VALIDATE_URL)) {
                return 'A valid resumeLinkUrl is required for link resumes.';
            }
            if (ResumeKind::File === $application->getResumeKind() && null !== $application->getResumeFilePath()) {
                $this->fileUploader->remove($application->getResumeFilePath());
            }
            $application->setResumeKind(ResumeKind::Link);
            $application->setResumeLinkUrl($linkUrl);
            $application->setResumeFilePath(null);
            $application->setResumeFileName(null);
            $application->setResumeMimeType(null);
            $application->setResumeFileSize(null);

            return null;
        }

        if (ResumeKind::File->value === $kind) {
            return 'Upload the file using POST /api/applications/{id}/resume-file instead.';
        }

        return sprintf('Invalid resumeKind. Allowed values: %s, %s or null.', ResumeKind::Link->value, ResumeKind::File->value);
    }

    private function findOwnedApplication(int $id): ?Application
    {
        /** @var User $user */
        $user = $this->getUser();
        $application = $this->applicationRepository->find($id);

        return null !== $application && $application->getJob()?->getJobSearch()?->getUser()?->getId() === $user->getId()
            ? $application
            : null;
    }

    private function findOwnedJob(int $jobId): ?Job
    {
        /** @var User $user */
        $user = $this->getUser();
        $job = $this->jobRepository->find($jobId);

        return null !== $job && $job->getJobSearch()?->getUser()?->getId() === $user->getId() ? $job : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value || !is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
