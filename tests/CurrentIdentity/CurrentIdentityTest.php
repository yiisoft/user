<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentIdentity;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\User\CurrentIdentity\Storage\CurrentIdentityStorageInterface;
use Yiisoft\User\CurrentIdentity\CurrentIdentity;
use Yiisoft\User\CurrentIdentity\Event\AfterLogin;
use Yiisoft\User\CurrentIdentity\Event\AfterLogout;
use Yiisoft\User\CurrentIdentity\Event\BeforeLogin;
use Yiisoft\User\CurrentIdentity\Event\BeforeLogout;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\FakeCurrentIdentityStorage;
use Yiisoft\User\Tests\Mock\MockAccessChecker;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;

final class CurrentIdentityTest extends TestCase
{
    public function testGetWithoutLogin(): void
    {
        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher()
        );

        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    public function testGetFromStorage(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage($id),
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher()
        );

        self::assertSame($identity, $currentIdentity->get());
    }

    public function testGetTemporary(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage($id),
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher()
        );

        $temporaryIdentity = new MockIdentity('temp-id');
        $currentIdentity->setTemporaryIdentity($temporaryIdentity);

        self::assertSame($temporaryIdentity, $currentIdentity->get());
    }

    public function testClearTemporary(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage($id),
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher()
        );
        $currentIdentity->setTemporaryIdentity(new MockIdentity('temp-id'));
        $currentIdentity->clearTemporaryIdentity();

        self::assertSame($identity, $currentIdentity->get());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        $identity = $this->createIdentity('test-id');

        self::assertTrue($currentIdentity->login($identity));

        $events = $eventDispatcher->getEvents();
        self::assertInstanceOf(AfterLogin::class, array_pop($events));
        self::assertInstanceOf(BeforeLogin::class, array_pop($events));

        self::assertSame($identity, $currentIdentity->get());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );
        $currentIdentity->login($this->createIdentity('test-id'));

        self::assertTrue($currentIdentity->logout());

        $events = $eventDispatcher->getEvents();
        self::assertInstanceOf(AfterLogout::class, array_pop($events));
        self::assertInstanceOf(BeforeLogout::class, array_pop($events));

        self::assertTrue($currentIdentity->isGuest());
    }

    public function testGuestLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        self::assertFalse($currentIdentity->logout());
        self::assertEmpty($eventDispatcher->getEvents());
        self::assertTrue($currentIdentity->isGuest());
    }

    public function testCanWithoutAccessChecker(): void
    {
        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        self::assertFalse($currentIdentity->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $currentIdentity = new CurrentIdentity(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );
        $currentIdentity->setAccessChecker(new MockAccessChecker(true));

        self::assertTrue($currentIdentity->can('permission'));
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
