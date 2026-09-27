<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CompanyController;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class CompanyControllerTest extends ControllerTestCase
{
    public function testIndexReturnsOwnedCompanies(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByUser')->with($this->user->getId())->willReturn([$company]);

        $controller = new CompanyController($companyRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $this->decodeJson($response);
        $this->assertCount(1, $data);
        $this->assertSame('Acme Inc', $data[0]['name']);
        $this->assertSame(0, $data[0]['jobCount']);
    }

    public function testCreatePersistsCompany(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (mixed $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $controller = new CompanyController($companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/companies', ['name' => '  Acme Inc  ']));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertInstanceOf(Company::class, $persisted);
        $this->assertSame('Acme Inc', $persisted->getName());
        $this->assertSame($this->user, $persisted->getUser());
    }

    public function testCreateRequiresAName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $controller = new CompanyController($this->createMock(CompanyRepository::class), $entityManager);
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/companies', ['name' => '  ']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('A company name is required.', $this->decodeJson($response)['error']);
    }

    public function testCreateRejectsADuplicateName(): void
    {
        $existing = $this->createCompany(3, name: 'Acme Inc');
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('findByNameForUser')->with('Acme Inc', $this->user->getId())->willReturn($existing);

        $controller = new CompanyController($companyRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->create($this->jsonRequest('POST', '/api/companies', ['name' => 'Acme Inc']));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('A company with this name already exists.', $this->decodeJson($response)['error']);
    }

    public function testUpdateRenamesCompany(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);
        $companyRepository->method('findByNameForUser')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = new CompanyController($companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->update(3, $this->jsonRequest('PATCH', '/api/companies/3', ['name' => 'Globex']));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('Globex', $company->getName());
    }

    public function testUpdateReturns404ForAnotherUsersCompany(): void
    {
        $otherUser = $this->createUser(2);
        $company = $this->createCompany(3, $otherUser);
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);

        $controller = new CompanyController($companyRepository, $this->createMock(EntityManagerInterface::class));
        $this->bindController($controller);

        $response = $controller->update(3, $this->jsonRequest('PATCH', '/api/companies/3', ['name' => 'Globex']));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Company not found.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRefusesWhenLinkedToOne(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $company->getJobs()->add($this->createJob(9, $this->createJobSearch(4)));
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $controller = new CompanyController($companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(3);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('This company is linked to jobs or people and cannot be deleted.', $this->decodeJson($response)['error']);
    }

    public function testDeleteRefusesWhenLinkedToAPerson(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $company->getPeople()->add($this->createPerson(5, company: $company));
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $controller = new CompanyController($companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(3);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testDeleteRemovesCompany(): void
    {
        $company = $this->createCompany(3, name: 'Acme Inc');
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('find')->with(3)->willReturn($company);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($company);
        $entityManager->expects($this->once())->method('flush');

        $controller = new CompanyController($companyRepository, $entityManager);
        $this->bindController($controller);

        $response = $controller->delete(3);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($this->decodeJson($response)['success']);
    }
}
