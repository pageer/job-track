<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    public function __construct(private string $uploadDir)
    {
    }

    /**
     * Stores an uploaded file under var/uploads/<subdir>/<ownerId>/<random>.<ext>
     * and returns the path relative to the project directory (e.g. var/uploads/resumes/3/abc.pdf).
     */
    public function upload(UploadedFile $file, string $subdir, int|string $ownerId): string
    {
        $relativeBase = 'var/uploads/' . trim($subdir, '/') . '/' . $ownerId;
        $absoluteDir = $this->uploadDir . '/' . $subdir . '/' . $ownerId;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new FileException(sprintf('Unable to create the upload directory "%s".', $absoluteDir));
        }

        $extension = $file->guessExtension() ?? pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $extension = preg_replace('/[^a-z0-9]/i', '', (string) $extension);
        $extension = $extension ?: 'bin';

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        try {
            $file->move($absoluteDir, $filename);
        } catch (FileException $e) {
            throw new FileException(sprintf('Unable to store the uploaded file: %s', $e->getMessage()), 0, $e);
        }

        return $relativeBase . '/' . $filename;
    }

    /**
     * Resolves a project-relative path (stored in the DB) to an absolute path.
     */
    public function resolve(string $relativePath): string
    {
        return $this->uploadDir . '/' . substr($relativePath, strlen('var/uploads/'));
    }

    public function remove(string $relativePath): void
    {
        $absolute = $this->resolve($relativePath);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
