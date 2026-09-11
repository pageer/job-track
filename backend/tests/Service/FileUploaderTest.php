<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploaderTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir() . '/job-track-uploader-test-' . bin2hex(random_bytes(4));
        mkdir($this->uploadDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->uploadDir);
    }

    public function testUploadMovesFileAndReturnsRelativePath(): void
    {
        $absoluteDir = $this->uploadDir . '/resumes/42';
        $file = $this->createUploadMock('pdf');

        $file->expects($this->once())->method('move')->willReturnCallback(
            function (string $targetDir, string $filename): File {
                $this->assertSame($this->uploadDir . '/resumes/42', $targetDir);
                $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.pdf$/', $filename);

                return $this->createMock(File::class);
            },
        );

        $uploader = new FileUploader($this->uploadDir);
        $result = $uploader->upload($file, 'resumes', 42);

        $this->assertMatchesRegularExpression('#^var/uploads/resumes/42/[0-9a-f]{32}\.pdf$#', $result);
    }

    public function testUploadFallsBackToBinWhenNoExtension(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn(null);
        $file->method('getClientOriginalName')->willReturn('noext');
        $file->expects($this->once())->method('move')->willReturnCallback(
            function (string $targetDir, string $filename): File {
                $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.bin$/', $filename);

                return $this->createMock(File::class);
            },
        );

        $uploader = new FileUploader($this->uploadDir);
        $result = $uploader->upload($file, 'resumes', 1);

        $this->assertMatchesRegularExpression('#^var/uploads/resumes/1/[0-9a-f]{32}\.bin$#', $result);
    }

    public function testUploadSanitisesExtensionCharacters(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn('weird..ext');
        $file->method('getClientOriginalName')->willReturn('doc.txt');
        $file->expects($this->once())->method('move')->willReturnCallback(
            function (string $targetDir, string $filename): File {
                $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.weirdext$/', $filename);

                return $this->createMock(File::class);
            },
        );

        $uploader = new FileUploader($this->uploadDir);
        $result = $uploader->upload($file, 'resumes', 1);

        $this->assertMatchesRegularExpression('#^var/uploads/resumes/1/[0-9a-f]{32}\.weirdext$#', $result);
    }

    public function testUploadCreatesDirectory(): void
    {
        $absoluteDir = $this->uploadDir . '/docs/7';
        $this->assertDirectoryDoesNotExist($absoluteDir);

        $file = $this->createUploadMock('pdf');
        $file->method('move')->willReturnCallback(
            function () use ($absoluteDir): File {
                $this->assertDirectoryExists($absoluteDir);

                return $this->createMock(File::class);
            },
        );

        $uploader = new FileUploader($this->uploadDir);
        $uploader->upload($file, 'docs', 7);
    }

    public function testResolve(): void
    {
        $uploader = new FileUploader($this->uploadDir);

        $this->assertSame(
            $this->uploadDir . '/resumes/42/abc.pdf',
            $uploader->resolve('var/uploads/resumes/42/abc.pdf'),
        );
    }

    public function testRemoveDeletesExistingFile(): void
    {
        $relativePath = 'var/uploads/docs/1/abc.pdf';
        $absoluteDir = $this->uploadDir . '/docs/1';
        mkdir($absoluteDir, 0775, true);
        touch($absoluteDir . '/abc.pdf');

        $uploader = new FileUploader($this->uploadDir);
        $uploader->remove($relativePath);

        $this->assertFileDoesNotExist($absoluteDir . '/abc.pdf');
    }

    public function testRemoveSkipsNonExistingFile(): void
    {
        $uploader = new FileUploader($this->uploadDir);
        $uploader->remove('var/uploads/docs/99/nope.pdf');

        $this->addToAssertionCount(1);
    }

    private function createUploadMock(string $extension): UploadedFile&MockObject
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn($extension);
        $file->method('getClientOriginalName')->willReturn('doc.' . $extension);

        return $file;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }
}
