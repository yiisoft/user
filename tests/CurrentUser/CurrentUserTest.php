<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentUser;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\User\CurrentUser\Storage\CurrentIdentityStorageInterface;
use Yiisoft\User\CurrentUser\CurrentUser;
use Yiisoft\User\CurrentUser\Event\AfterLogin;
use Yiisoft\User\CurrentUser\Event\AfterLogout;
use Yiisoft\User\CurrentUser\Event\BeforeLogin;
use Yiisoft\User\CurrentUser\Event\BeforeLogout;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\FakeCurrentIdentityStorage;
use Yiisoft\User\Tests\Mock\MockAccessChecker;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;

final class CurrentUserTest extends TestCase
{
    public function testGetIdentityWithoutLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher()
        );

        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityFromStorage(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage($id),
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher()
        );

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testGetTemporaryIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage($id),
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher()
        );

        $temporaryIdentity = new MockIdentity('temp-id');
        $currentUser->setTemporaryIdentity($temporaryIdentity);

        self::assertSame($temporaryIdentity, $currentUser->getIdentity());
    }

    public function testClearTemporaryIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage($id),
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher()
        );
        $currentUser->setTemporaryIdentity(new MockIdentity('temp-id'));
        $currentUser->clearTemporaryIdentity();

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        $identity = $this->createIdentity('test-id');

        self::assertTrue($currentUser->login($identity));

        $events = $eventDispatcher->getEvents();
        self::assertInstanceOf(AfterLogin::class, array_pop($events));
        self::assertInstanceOf(BeforeLogin::class, array_pop($events));

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );
        $currentUser->login($this->createIdentity('test-id'));

        self::assertTrue($currentUser->logout());

        $events = $eventDispatcher->getEvents();
        self::assertInstanceOf(AfterLogout::class, array_pop($events));
        self::assertInstanceOf(BeforeLogout::class, array_pop($events));

        self::assertTrue($currentUser->isGuest());
    }

    public function testGuestLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        self::assertFalse($currentUser->logout());
        self::assertEmpty($eventDispatcher->getEvents());
        self::assertTrue($currentUser->isGuest());
    }

    public function testCanWithoutAccessChecker(): void
    {
        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        self::assertFalse($currentUser->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );
        $currentUser->setAccessChecker(new MockAccessChecker(true));

        self::assertTrue($currentUser->can('permission'));
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

    private function createEventDispatcher(): SimpleEventDispatcher
    {
        return new SimpleEventDispatcher();
    }
}
