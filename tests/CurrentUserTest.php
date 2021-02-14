<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\MockAccessChecker;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\StubCurrentIdentity;

final class CurrentUserTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('42');
        self::assertSame(
            $identity,
            $this->createCurrentUser($identity)->getIdentity()
        );
    }

    public function testGetIdentityDefault(): void
    {
        self::assertInstanceOf(
            GuestIdentity::class,
            $this->createCurrentUser()->getIdentity()
        );
    }

    public function testGetId(): void
    {
        self::assertNull(
            $this->createCurrentUser()->getId()
        );
        self::assertSame(
            '42',
            $this->createCurrentUser(new MockIdentity('42'))->getId()
        );
    }

    public function testIsGuest(): void
    {
        self::assertTrue(
            $this->createCurrentUser()->isGuest()
        );
        self::assertFalse(
            $this->createCurrentUser(new MockIdentity('42'))->isGuest()
        );
    }

    public function testCanWithoutChecker(): void
    {
        self::assertFalse(
            $this->createCurrentUser()->can('createPost')
        );
    }

    public function testCanWithChecker(): void
    {
        $currentUser = $this->createCurrentUser();
        $currentUser->setAccessChecker(new MockAccessChecker(true));

        self::assertTrue($currentUser->can('createPost'));
    }

    private function createCurrentUser(IdentityInterface $identity = null): CurrentUser
    {
        return new CurrentUser(
            new StubCurrentIdentity($identity ?? new GuestIdentity())
        );
    }
}
