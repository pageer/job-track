<?php

namespace App\Controller;

use App\Entity\Resume;
use App\Entity\User;
use App\Enum\ResumeKind;
use App\Repository\ResumeRepository;
use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/resumes', name: 'api_resumes_')]
class ResumeController extends AbstractController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private FileUploader $fileUploader
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->resumeRepository->findByUser($user->getId()), 200, [], ['groups' => ['resume.read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $resume = new Resume();
        $resume->setUser($user);

        $name = trim((string) ($request->request->get('name') ?? $request->toArray()['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $resume->setName($name);

        $file = $request->files->get('file');
        if (null !== $file) {
            $resume->setKind(ResumeKind::File);
            $this->storeResumeFile($resume, $file);
        } else {
            $data = $request->toArray();
            $kind = (string) ($data['kind'] ?? '');
            if (ResumeKind::Link->value !== $kind) {
                return $this->json(['error' => 'Either upload a file or provide kind "link" with a linkUrl.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $linkUrl = trim((string) ($data['linkUrl'] ?? ''));
            if ('' === $linkUrl) {
                return $this->json(['error' => 'A linkUrl is required for link resumes.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if (false === filter_var($linkUrl, FILTER_VALIDATE_URL)) {
                return $this->json(['error' => 'Please provide a valid URL.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $resume->setKind(ResumeKind::Link);
            $resume->setLinkUrl($linkUrl);
        }

        $this->resumeRepository->getEntityManager()->persist($resume);
        $this->resumeRepository->getEntityManager()->flush();

        return $this->json($resume, Response::HTTP_CREATED, [], ['groups' => ['resume.read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $resume = $this->findOwnedResume($id);
        if (null === $resume) {
            return $this->json(['error' => 'Resume not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ('' === $name) {
                return $this->json(['error' => 'A name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $resume->setName($name);
        }

        if (ResumeKind::Link === $resume->getKind() && array_key_exists('linkUrl', $data)) {
            $linkUrl = trim((string) $data['linkUrl']);
            if (false === filter_var($linkUrl, FILTER_VALIDATE_URL)) {
                return $this->json(['error' => 'Please provide a valid URL.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $resume->setLinkUrl($linkUrl);
        }

        $this->resumeRepository->getEntityManager()->flush();

        return $this->json($resume, 200, [], ['groups' => ['resume.read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $resume = $this->findOwnedResume($id);
        if (null === $resume) {
            return $this->json(['error' => 'Resume not found.'], Response::HTTP_NOT_FOUND);
        }

        if (ResumeKind::File === $resume->getKind() && null !== $resume->getFilePath()) {
            $this->fileUploader->remove($resume->getFilePath());
        }

        $this->resumeRepository->getEntityManager()->remove($resume);
        $this->resumeRepository->getEntityManager()->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $resume = $this->findOwnedResume($id);
        if (null === $resume) {
            return $this->json(['error' => 'Resume not found.'], Response::HTTP_NOT_FOUND);
        }

        if (ResumeKind::File !== $resume->getKind() || null === $resume->getFilePath()) {
            return $this->json(['error' => 'This resume has no attached file.'], Response::HTTP_CONFLICT);
        }

        $path = $this->fileUploader->resolve($resume->getFilePath());
        if (!is_file($path)) {
            return $this->json(['error' => 'The stored file could not be found.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $resume->getFileName() ?? basename($path)
        );
        if (null !== $resume->getMimeType()) {
            $response->headers->set('Content-Type', $resume->getMimeType());
        }

        return $response;
    }

    private function findOwnedResume(int $id): ?Resume
    {
        /** @var User $user */
        $user = $this->getUser();
        $resume = $this->resumeRepository->find($id);

        return null !== $resume && $resume->getUser()->getId() === $user->getId() ? $resume : null;
    }

    private function storeResumeFile(Resume $resume, \Symfony\Component\HttpFoundation\File\UploadedFile $file): void
    {
        /** @var User $user */
        $user = $this->getUser();

        $fileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $path = $this->fileUploader->upload($file, 'resumes', $user->getId());

        if (null !== $resume->getFilePath()) {
            $this->fileUploader->remove($resume->getFilePath());
        }

        $resume->setFilePath($path);
        $resume->setFileName($fileName);
        $resume->setMimeType($mimeType);
        $resume->setFileSize($fileSize);
    }
}
