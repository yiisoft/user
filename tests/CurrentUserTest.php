<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Event\BeforeLogout;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\MockAccessChecker;
use Yiisoft\User\Tests\Mock\MockArraySessionStorage;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;

final class CurrentUserTest extends TestCase
{
    public function testGetIdentityWithoutLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityWithoutLoginAndSession(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            null
        );

        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityFromStorage(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $this->createSession(['__auth_id' => $id])
        );

        self::assertSame($identity, $currentUser->getIdentity());
        self::assertSame($id, $currentUser->getId());
    }

    public function testGetIdentityIfSessionHasEqualAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);
        $session = $this->createSession([
            '__auth_id' => $id,
            '__auth_expire' => time(),
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeout(): void
    {
        $session = $this->createSession([
            '__auth_id' => 'test-id',
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);

        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
        self::assertFalse($session->has('__auth_id'));
        self::assertFalse($session->has('__auth_expire'));
    }

    public function testGetIdentityIfSessionHasExpiredAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);
        $session = $this->createSession([
            '__auth_id' => $id,
            '__auth_absolute_expire' => strtotime('-1 day'),
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAbsoluteAuthTimeout(60);

        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityIfSessionHasEqualAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);
        $session = $this->createSession([
            '__auth_id' => $id,
            '__auth_absolute_expire' => time(),
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAbsoluteAuthTimeout(60);

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testGetIdentityAndSetAuthExpire(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);
        $session = $this->createSession([
            '__auth_id' => $id,
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);

        self::assertSame($identity, $currentUser->getIdentity());
        self::assertTrue($session->has('__auth_expire'));
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeoutAndWithoutId(): void
    {
        $session = $this->createSession([
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);

        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
        self::assertTrue($session->has('__auth_expire'));
    }

    public function testGetTemporaryIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $this->createSession(['__auth_id' => $id])
        );

        $temporaryIdentity = new MockIdentity('temp-id');
        $currentUser->overrideIdentity($temporaryIdentity);

        self::assertSame($temporaryIdentity, $currentUser->getIdentity());
    }

    public function testLoginAndGetTemporaryIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );
        $currentUser->login($identity);

        $temporaryIdentity = new MockIdentity('temp-id');
        $currentUser->overrideIdentity($temporaryIdentity);

        self::assertSame($temporaryIdentity, $currentUser->getIdentity());
    }

    public function testClearTemporaryIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $this->createSession(['__auth_id' => $id])
        );
        $currentUser->overrideIdentity(new MockIdentity('temp-id'));
        $currentUser->clearIdentityOverride();

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession()
        );

        $identity = $this->createIdentity('test-id');

        self::assertTrue($currentUser->login($identity));

        $events = $eventDispatcher->getEvents();
        self::assertInstanceOf(AfterLogin::class, array_pop($events));
        self::assertInstanceOf(BeforeLogin::class, array_pop($events));

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginWithoutSession(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            null
        );

        $identity = $this->createIdentity('test-id');

        self::assertTrue($currentUser->login($identity));

        $events = $eventDispatcher->getEvents();
        self::assertInstanceOf(AfterLogin::class, array_pop($events));
        self::assertInstanceOf(BeforeLogin::class, array_pop($events));

        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginAndSetAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAbsoluteAuthTimeout(60);
        $currentUser->login($identity);

        self::assertSame($identity, $currentUser->getIdentity());
        self::assertSame($id, $session->get('__auth_id'));
        self::assertTrue($session->has('__auth_absolute_expire'));

        // Second getting
        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAbsoluteAuthTimeout(60);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginAndSetAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);
        $currentUser->login($identity);

        self::assertTrue($session->has('__auth_expire'));
        self::assertSame($identity, $currentUser->getIdentity());

        // Second getting
        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);
        self::assertSame($identity, $currentUser->getIdentity());

        // Third getting
        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);
        self::assertSame($identity, $currentUser->getIdentity());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession()
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
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession()
        );

        self::assertFalse($currentUser->logout());
        self::assertEmpty($eventDispatcher->getEvents());
        self::assertTrue($currentUser->isGuest());
    }

    public function testLogoutWithSetAuthExpire(): void
    {
        $session = $this->createSession();
        $sessionId = $session->getId();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session
        );
        $currentUser->setAuthTimeout(60);
        $currentUser->login($this->createIdentity('test-id'));
        $currentUser->logout();

        self::assertNotSame($sessionId, $session->getId());
        self::assertFalse($session->has('__auth_id'));
        self::assertFalse($session->has('__auth_expire'));
        self::assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testCanWithoutAccessChecker(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        self::assertFalse($currentUser->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );
        $currentUser->setAccessChecker(new MockAccessChecker(true));

        self::assertTrue($currentUser->can('permission'));
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }

    private function createSession(array $data = []): MockArraySessionStorage
    {
        return new MockArraySessionStorage($data);
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
