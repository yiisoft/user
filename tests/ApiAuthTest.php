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
use Yiisoft\User\Method\ApiAuth;

final class ApiAuthTest extends TestCase
{
    public function testSuccessfulAuthentication(): void
    {
        $user = $this->createCurrentUser();
        $user->login(new MockIdentity('test-id'));
        $result = (new ApiAuth($user))->authenticate(new ServerRequest());

        $this->assertNotNull($result);
        $this->assertSame('test-id', $result->getId());
    }

    public function testIdentityNotAuthenticated(): void
    {
        $user = $this->createCurrentUser();
        $result = (new ApiAuth($user))->authenticate(new ServerRequest());

        $this->assertNull($result);
    }

    public function testChallengeIsCorrect(): void
    {
        $response = new Response(Status::UNAUTHORIZED);
        $user = $this->createCurrentUser();
        $challenge = (new ApiAuth($user))->challenge($response);

        $this->assertSame(Status::UNAUTHORIZED, $challenge->getStatusCode());
    }

    private function createCurrentUser(): CurrentUser
    {
        return (new CurrentUser(new MockIdentityRepository(), new SimpleEventDispatcher()))
            ->withSession(new MockArraySessionStorage());
    }
}
