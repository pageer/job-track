<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\JobController;
use App\Entity\Company;
use App\Entity\Job;
use App\Entity\JobSearch;
use App\Entity\User;
use App\Enum\JobStatus;
use App\Repository\CompanyRepository;
use App\Repository\JobRepository;
use App\Repository\JobSearchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class JobControllerTest extends ControllerTestCase
{
    public function testIndexReturnsJobsForAnOwnedSearch(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('findByJobSearch')->with(4)->willReturn([$job]);

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(4);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Platform Engineer', $data[0]['title']);
        $this->assertSame('Acme Inc', $data[0]['company']);
        $this->assertSame(4, $data[0]['jobSearchId']);
        $this->assertSame('investigating', $data[0]['status']);
    }

    public function testIndexReturns404ForAnotherUsersSearch(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $searchRepository = $this->createMock(JobSearchRepository::class);
        $searchRepository->method('find')->with(4)->willReturn($search);

        $controller = new JobController($this->createMock(JobRepository::class), $searchRepository, $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index(4);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Job search not found.', $this->decodeJson($response)['error']);
    }

    public function testCreatePersistsAJobWithGivenStatus(): void
    {
        $search = $this->createJobSearch(4);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobController($this->createMock(JobRepository::class), $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create(4, $this->jsonRequest('POST', '/api/job-searches/4/jobs', [
            'title' => '  Backend Developer  ',
            'company' => '  Globex  ',
            'status' => 'applied',
            'descriptionHtml' => '<p>Requirements...</p>',
            'descriptionUrl' => 'https://example.com/job',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Backend Developer', $data['title']);
        $this->assertSame('Globex', $data['company']);
        $this->assertSame('applied', $data['status']);

        $this->assertInstanceOf(Job::class, $persisted);
        $this->assertSame($search, $persisted->getJobSearch());
        $this->assertSame(JobStatus::Applied, $persisted->getStatus());
        $this->assertSame('https://example.com/job', $persisted->getDescriptionUrl());
    }

    public function testCreateDefaultsStatusToInvestigating(): void
    {
        $search = $this->createJobSearch(4);
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $controller = new JobController($this->createMock(JobRepository::class), $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $controller->create(4, $this->jsonRequest('POST', '/api/job-searches/4/jobs', [
            'title' => 'Backend Developer',
            'company' => 'Globex',
        ]));

        $this->assertInstanceOf(Job::class, $persisted);
        $this->assertSame(JobStatus::Investigating, $persisted->getStatus());
    }

    public function testCreateRequiresTitleAndCompany(): void
    {
        $search = $this->createJobSearch(4);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new JobController($this->createMock(JobRepository::class), $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create(4, $this->jsonRequest('POST', '/api/job-searches/4/jobs', [
            'title' => '',
            'company' => '',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A title and company are required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsAnInvalidStatus(): void
    {
        $search = $this->createJobSearch(4);

        $controller = new JobController($this->createMock(JobRepository::class), $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create(4, $this->jsonRequest('POST', '/api/job-searches/4/jobs', [
            'title' => 'Backend Developer',
            'company' => 'Globex',
            'status' => 'unsure',
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertStringContainsString('Invalid status', $this->decodeJson($response)['error']);
    }

    public function testCreateCreatesAndLinksTheCompany(): void
    {
        $search = $this->createJobSearch(4);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->with('Globex', $this->user->getId())->willReturn(null);

        $controller = new JobController($this->createMock(JobRepository::class), $this->searchRepositoryWith($search), $companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create(4, $this->jsonRequest('POST', '/api/job-searches/4/jobs', [
            'title' => 'Backend Developer',
            'company' => 'Globex',
        ]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $company = $persisted[0];
        $this->assertInstanceOf(Company::class, $company);
        $this->assertSame('Globex', (string) $company->getName());
        $job = $persisted[1];
        $this->assertInstanceOf(Job::class, $job);
        $this->assertSame($company, $job->getCompanyRef());
    }

    public function testCreateReusesAnExistingCompanyByName(): void
    {
        $search = $this->createJobSearch(4);
        $company = new Company();
        $this->setId($company, 7);
        $company->setUser($this->user)->setName('Globex');

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->with('Globex', $this->user->getId())->willReturn($company);

        $controller = new JobController($this->createMock(JobRepository::class), $this->searchRepositoryWith($search), $companyRepository, $entityManager);
        $this->bindController($controller);

        $controller->create(4, $this->jsonRequest('POST', '/api/job-searches/4/jobs', [
            'title' => 'Backend Developer',
            'company' => 'Globex',
        ]));

        $this->assertInstanceOf(Job::class, $persisted);
        $this->assertSame($company, $persisted->getCompanyRef());
    }

    public function testShowReturnsTheOwnedJob(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(9);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertSame('Platform Engineer', $data['title']);
        $this->assertArrayHasKey('jobSearchId', $data);
    }

    public function testShowReturns404ForAnotherUsersJob(): void
    {
        $otherUser = $this->createUser(2);
        $search = $this->createJobSearch(4, $otherUser);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->show(9);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Job not found.', $this->decodeJson($response)['error']);
    }

    public function testUpdateModifiesJobFields(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->update(9, $this->jsonRequest('PATCH', '/api/jobs/9', [
            'title' => 'Senior Backend Developer',
            'status' => 'in_progress',
            'descriptionHtml' => '',
            'descriptionUrl' => '',
        ]));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Senior Backend Developer', $job->getTitle());
        $this->assertSame(JobStatus::InProgress, $job->getStatus());
        $this->assertNull($job->getDescriptionHtml());
        $this->assertNull($job->getDescriptionUrl());
    }

    public function testUpdateRejectsBlankTitle(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->update(9, $this->jsonRequest('PATCH', '/api/jobs/9', ['title' => '  ']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A title is required.', $this->decodeJson($response)['error']);
    }

    public function testUpdateRelinksTheCompanyWhenChanged(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $company = new Company();
        $this->setId($company, 7);
        $company->setUser($this->user)->setName('Newco');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->with('Newco', $this->user->getId())->willReturn($company);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $companyRepository, $entityManager);
        $this->bindController($controller);

        $controller->update(9, $this->jsonRequest('PATCH', '/api/jobs/9', ['company' => 'Newco']));

        $this->assertSame('Newco', $job->getCompany());
        $this->assertSame($company, $job->getCompanyRef());
    }

    public function testDeleteRemovesTheJob(): void
    {
        $search = $this->createJobSearch(4);
        $job = $this->createJob(9, $search);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with(9)->willReturn($job);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($job);
        $entityManager->expects($this->once())->method('flush');

        $controller = new JobController($jobRepository, $this->searchRepositoryWith($search), $this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(9);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }

    private function searchRepositoryWith(?JobSearch $search): JobSearchRepository
    {
        $repository = $this->createMock(JobSearchRepository::class);
        $repository->method('find')->willReturn($search);

        return $repository;
    }
}
