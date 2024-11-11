<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yiisoft\Http\Status;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\User\Tests\Support\MockArraySessionStorage;
use Yiisoft\User\Tests\Support\MockIdentity;
use Yiisoft\User\Tests\Support\MockIdentityRepository;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Method\WebAuth;

final class WebAuthTest extends TestCase
{
    public function testSuccessfulAuthentication(): void
    {
        $user = $this->createCurrentUser();
        $user->login(new MockIdentity('test-id'));
        $result = (new WebAuth($user, new ResponseFactory()))->authenticate(new ServerRequest());

        $this->assertNotNull($result);
        $this->assertSame('test-id', $result->getId());
    }

    public function testIdentityNotAuthenticated(): void
    {
        $user = $this->createCurrentUser();
        $result = (new WebAuth($user, new ResponseFactory()))->authenticate(new ServerRequest());

        $this->assertNull($result);
    }

    public function testChallengeIsCorrect(): void
    {
        $response = new Response();
        $user = $this->createCurrentUser();
        $challenge = (new WebAuth($user, new ResponseFactory()))->challenge($response);

        $this->assertSame(Status::FOUND, $challenge->getStatusCode());
        $this->assertSame('/login', $challenge->getHeaderLine('Location'));
    }

    public function testCustomAuthUrl(): void
    {
        $response = new Response();
        $user = $this->createCurrentUser();
        $challenge = (new WebAuth($user, new ResponseFactory()))
            ->withAuthUrl('/custom-auth-url')
            ->challenge($response);

        $this->assertSame('/custom-auth-url', $challenge->getHeaderLine('Location'));
    }

    public function testImmutability(): void
    {
        $original = new WebAuth($this->createCurrentUser(), new ResponseFactory());

        $this->assertNotSame($original, $original->withAuthUrl('/custom-auth-url'));
    }

    private function createCurrentUser(): CurrentUser
    {
        return (new CurrentUser(new MockIdentityRepository(), new SimpleEventDispatcher()))
            ->withSession(new MockArraySessionStorage());
    }
}
