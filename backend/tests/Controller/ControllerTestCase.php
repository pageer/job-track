<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Application;
use App\Entity\Job;
use App\Entity\JobSearch;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Base class for unit-testing API controllers without booting a kernel.
 *
 * Builds a minimal service container with the services AbstractController
 * depends on (security.token_storage, security.authorization_checker and
 * serializer) so controller actions can be invoked directly.
 */
abstract class ControllerTestCase extends TestCase
{
    protected User $user;

    private ?Serializer $serializer = null;

    protected function setUp(): void
    {
        $this->user = $this->createUser(1);
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(int $id, array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $this->setId($user, $id);
        $user->setEmail('user' . $id . '@example.com')->setName('User ' . $id);
        if (['ROLE_USER'] !== $roles) {
            $user->setRoles($roles);
        }

        return $user;
    }

    /**
     * @param list<string> $roles
     */
    protected function loginAs(int $id = 1, array $roles = ['ROLE_USER']): User
    {
        $this->user = $this->createUser($id, $roles);

        return $this->user;
    }

    protected function createJobSearch(int $id, ?User $user = null, string $name = 'Search'): JobSearch
    {
        $search = new JobSearch();
        $this->setId($search, $id);
        $search->setUser($user ?? $this->user);
        $search->setName($name);
        $search->setStartDate(new \DateTimeImmutable('2026-01-01'));

        return $search;
    }

    protected function createJob(int $id, JobSearch $search, string $title = 'Platform Engineer', string $company = 'Acme Inc'): Job
    {
        $job = new Job();
        $this->setId($job, $id);
        $job->setJobSearch($search);
        $job->setTitle($title);
        $job->setCompany($company);

        return $job;
    }

    protected function createApplication(int $id, Job $job): Application
    {
        $application = new Application();
        $this->setId($application, $id);
        $application->setJob($job);

        return $application;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function bindController(AbstractController $controller, ?AuthorizationCheckerInterface $authorizationChecker = null, array $parameters = []): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('serializer', $this->createSerializer());
        $container->set('security.authorization_checker', $authorizationChecker ?? $this->alwaysGrantedAuthorizationChecker());
        if ([] !== $parameters) {
            $container->set('parameter_bag', new ParameterBag($parameters));
        }

        $controller->setContainer($container);
    }

    protected function createSerializer(): Serializer
    {
        if (null === $this->serializer) {
            $this->serializer = new Serializer(
                [
                    new BackedEnumNormalizer(),
                    new DateTimeNormalizer(),
                    new ObjectNormalizer(
                        new ClassMetadataFactory(new AttributeLoader()),
                        null,
                        PropertyAccess::createPropertyAccessor(),
                    ),
                ],
                [new JsonEncoder()],
            );
        }

        return $this->serializer;
    }

    protected function createAuthorizationChecker(bool $granted): AuthorizationCheckerInterface
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return $checker;
    }

    protected function alwaysGrantedAuthorizationChecker(): AuthorizationCheckerInterface
    {
        return $this->createAuthorizationChecker(true);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>    $server
     */
    protected function jsonRequest(string $method, string $uri, ?array $body = null, array $server = []): Request
    {
        $content = null === $body ? '' : json_encode($body, JSON_THROW_ON_ERROR);

        return Request::create($uri, $method, [], [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $server), $content);
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function decodeJson(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Expected a JSON object in the response body.');
        }

        return $data;
    }

    protected function setId(object $entity, int $id): void
    {
        $property = new ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
