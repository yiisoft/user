<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser\Storage\CurrentIdentityIdStorageInterface;
use Yiisoft\User\Tests\Mock\FakeCurrentIdentityIdStorage;
use Yiisoft\User\Tests\Mock\MockArraySessionStorage;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;
use Yiisoft\User\CurrentUser\CurrentUser;
use Yiisoft\User\UserAuth;

final class UserAuthTest extends TestCase
{
    public function testSuccessfulAuthentication(): void
    {
        $user = $this->createCurrentUser();
        $user->login($this->createIdentity('test-id'));
        $result = (new UserAuth($user, new Psr17Factory()))->authenticate($this->createRequest());

        self::assertNotNull($result);
        self::assertSame('test-id', $result->getId());
    }

    public function testIdentityNotAuthenticated(): void
    {
        $user = $this->createCurrentUser();
        $result = (new UserAuth($user, new Psr17Factory()))->authenticate($this->createRequest());

        self::assertNull($result);
    }

    public function testChallengeIsCorrect(): void
    {
        $response = new Response();
        $user = $this->createCurrentUser();
        $challenge = (new UserAuth($user, new Psr17Factory()))->challenge($response);

        self::assertSame(Status::FOUND, $challenge->getStatusCode());
        self::assertSame('/login', $challenge->getHeaderLine('Location'));
    }

    public function testCustomAuthUrl(): void
    {
        $response = new Response();
        $user = $this->createCurrentUser();
        $challenge = (new UserAuth($user, new Psr17Factory()))->withAuthUrl('/custom-auth-url')->challenge($response);

        self::assertSame('/custom-auth-url', $challenge->getHeaderLine('Location'));
    }

    public function testImmutability(): void
    {
        $original = new UserAuth($this->createCurrentUser(), new Psr17Factory());

        self::assertNotSame($original, $original->withAuthUrl('/custom-auth-url'));
    }

    private function createCurrentUser(): CurrentUser
    {
        return new CurrentUser(
            $this->createCurrentIdentityIdStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher()
        );
    }

    private function createIdentity(string $id): MockIdentity
    {
        return new MockIdentity($id);
    }

    private function createIdentityRepository(?IdentityInterface $identity = null): IdentityRepositoryInterface
    {
        return new MockIdentityRepository($identity);
    }

    private function createCurrentIdentityIdStorage(?string $id = null): CurrentIdentityIdStorageInterface
    {
        return new FakeCurrentIdentityIdStorage($id);
    }

    private function createEventDispatcher(): EventDispatcherInterface
    {
        return new MockEventDispatcher();
    }

    private function createRequest(array $serverParams = [], array $headers = []): ServerRequestInterface
    {
        return new ServerRequest(Method::GET, '/', $headers, null, '1.1', $serverParams);
    }
}
