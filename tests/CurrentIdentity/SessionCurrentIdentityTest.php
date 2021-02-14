<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentIdentity;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentIdentity\SessionCurrentIdentity;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\BeforeLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\MockArraySessionStorage;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;

final class SessionCurrentIdentityTest extends TestCase
{
    public function testGetReturnsGuestWithoutSession(): void
    {
        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    public function testGetReturnsGuestWithSession(): void
    {
        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSessionStorage(),
        );

        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    public function testGetReturnsIdentitySet(): void
    {
        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $identity = $this->createIdentity('test-id');
        $currentIdentity->set($identity);

        self::assertSame($identity, $currentIdentity->get());
    }

    public function testGetReturnsGuestIfSessionHasExpiredAuthTimeout(): void
    {
        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(
                $this->createIdentity('test-id')
            ),
            $this->createEventDispatcher(),
            $this->createSessionStorage(
                [
                    '__auth_id' => 'test-id',
                    '__auth_expire' => strtotime('-1 day'),
                ]
            ),
        );
        $currentIdentity->setAuthTimeout(60);

        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    public function testGetReturnsGuestIfSessionHasExpiredAbsoluteAuthTimeout(): void
    {
        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(
                $this->createIdentity('test-id')
            ),
            $this->createEventDispatcher(),
            $this->createSessionStorage(
                [
                    '__auth_id' => 'test-id',
                    '__auth_absolute_expire' => strtotime('-1 day'),
                ]
            )
        );
        $currentIdentity->setAbsoluteAuthTimeout(60);

        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    public function testGetReturnsCorrectValueAndSetAuthExpire(): void
    {
        $sessionStorage = $this->createSessionStorage([
            '__auth_id' => 'test-id',
        ]);

        $identity = $this->createIdentity('test-id');

        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $sessionStorage,
        );
        $currentIdentity->setAuthTimeout(60);

        self::assertSame($identity, $currentIdentity->get());
        self::assertTrue($sessionStorage->has('__auth_expire'));
    }

    public function testSave(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSessionStorage()
        );

        $identity = $this->createIdentity('test-id');
        $currentIdentity->set($identity);
        $currentIdentity->save();

        self::assertEquals(
            [
                BeforeLogin::class,
                AfterLogin::class,
            ],
            $eventDispatcher->getClassesEvents()
        );
        self::assertSame($identity, $currentIdentity->get());
    }

    public function testSuccessfulClear(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(),
            $eventDispatcher
        );
        $currentIdentity->set($this->createIdentity('test-id'));
        $currentIdentity->clear();

        self::assertEquals(
            [
                BeforeLogout::class,
                AfterLogout::class,
            ],
            $eventDispatcher->getClassesEvents()
        );
        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    public function testGuestClear(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentIdentity = new SessionCurrentIdentity(
            $this->createIdentityRepository(
                $this->createIdentity('test-id')
            ),
            $eventDispatcher
        );
        $currentIdentity->clear();

        self::assertEmpty($eventDispatcher->getClassesEvents());
        self::assertInstanceOf(GuestIdentity::class, $currentIdentity->get());
    }

    private function createIdentityRepository(?IdentityInterface $identity = null): IdentityRepositoryInterface
    {
        return new MockIdentityRepository($identity);
    }

    private function createEventDispatcher(): MockEventDispatcher
    {
        return new MockEventDispatcher();
    }

    private function createSessionStorage(array $data = []): SessionInterface
    {
        return new MockArraySessionStorage($data);
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }
}
