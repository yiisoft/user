<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentIdentity;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentIdentity\Storage\CurrentIdentityStorageInterface;
use Yiisoft\User\CurrentIdentity\CurrentIdentity;
use Yiisoft\User\CurrentIdentity\Event\AfterLogin;
use Yiisoft\User\CurrentIdentity\Event\AfterLogout;
use Yiisoft\User\CurrentIdentity\Event\BeforeLogin;
use Yiisoft\User\CurrentIdentity\Event\BeforeLogout;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\FakeCurrentIdentityStorage;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;

final class CurrentIdentityTest extends TestCase
{
    public function testIdentityWithoutLogin(): void
    {
        $user = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher()
        );

        self::assertInstanceOf(GuestIdentity::class, $user->get());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $user = new CurrentIdentity(
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

        self::assertSame($identity, $user->get());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $user = new CurrentIdentity(
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

        $user = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        self::assertFalse($user->logout());
        self::assertEmpty($eventDispatcher->getClassesEvents());
        self::assertTrue($user->isGuest());
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
