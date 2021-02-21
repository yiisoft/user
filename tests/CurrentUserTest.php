<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentIdentityStorage\CurrentIdentityStorageInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Event\BeforeLogout;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\FakeCurrentIdentityStorage;
use Yiisoft\User\Tests\Mock\MockAccessChecker;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;

final class CurrentUserTest extends TestCase
{
    public function testIdentityWithoutLogin(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher()
        );

        self::assertInstanceOf(GuestIdentity::class, $user->getIdentity());
        self::assertNull($user->getId());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        $identity = $this->createIdentity('test-id');

        self::assertTrue($user->login($identity));
        self::assertEquals(
            [BeforeLogin::class, AfterLogin::class],
            $eventDispatcher->getClassesEvents()
        );

        self::assertSame($identity, $user->getIdentity());
        self::assertSame($identity->getId(), $user->getId());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );
        $user->login($this->createIdentity('test-id'));
        $eventDispatcher->clear();

        self::assertTrue($user->logout());
        self::assertEquals(
            [BeforeLogout::class, AfterLogout::class],
            $eventDispatcher->getClassesEvents()
        );
        self::assertTrue($user->isGuest());
    }

    public function testGuestLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        self::assertFalse($user->logout());
        self::assertEmpty($eventDispatcher->getClassesEvents());
        self::assertTrue($user->isGuest());
    }

    public function testCanWithoutAccessChecker(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        self::assertFalse($user->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $user->setAccessChecker($this->createAccessChecker(true));
        self::assertTrue($user->can('permission'));

        $user->setAccessChecker($this->createAccessChecker(false));
        self::assertFalse($user->can('permission'));
    }

    private function createAccessChecker(bool $userHasPermission): MockAccessChecker
    {
        return new MockAccessChecker($userHasPermission);
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }

    private function createCurrentIdentityStorage(?string $id = null): CurrentIdentityStorageInterface
    {
        return new FakeCurrentIdentityStorage($id);
    }

    private function createIdentityRepository(?IdentityInterface $identity = null): IdentityRepositoryInterface
    {
        return new MockIdentityRepository($identity);
    }

    private function createEventDispatcher(): MockEventDispatcher
    {
        return new MockEventDispatcher();
    }
}
