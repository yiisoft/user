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
use Yiisoft\User\Tests\Support\MockAccessChecker;
use Yiisoft\User\Tests\Support\MockArraySessionStorage;
use Yiisoft\User\Tests\Support\MockIdentity;
use Yiisoft\User\Tests\Support\MockIdentityRepository;

final class CurrentUserTest extends TestCase
{
    public function testGetIdentityWithoutLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityWithoutLoginAndSession(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            null,
        );

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityFromStorage(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $this->createSession(['__auth_id' => $id]),
        );

        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertSame($id, $currentUser->getId());
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
            $session,
        );

        $currentUser->setAuthTimeout(60);

        $this->assertSame($identity, $currentUser->getIdentity());
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
            $session,
        );

        $currentUser->setAuthTimeout(60);

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
        $this->assertFalse($session->has('__auth_id'));
        $this->assertFalse($session->has('__auth_expire'));
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
            $session,
        );

        $currentUser->setAbsoluteAuthTimeout(60);

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
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
            $session,
        );

        $currentUser->setAbsoluteAuthTimeout(60);

        $this->assertSame($identity, $currentUser->getIdentity());
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
            $session,
        );

        $currentUser->setAuthTimeout(60);

        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertTrue($session->has('__auth_expire'));
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeoutAndWithoutId(): void
    {
        $session = $this->createSession([
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session,
        );

        $currentUser->setAuthTimeout(60);

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
        $this->assertTrue($session->has('__auth_expire'));
    }

    public function testGetOverriddenIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $this->createSession(['__auth_id' => $id]),
        );

        $overriddenIdentity = new MockIdentity('temp-id');
        $currentUser->overrideIdentity($overriddenIdentity);

        $this->assertSame($overriddenIdentity, $currentUser->getIdentity());
    }

    public function testLoginAndGetOverriddenIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $currentUser->login($identity);
        $overriddenIdentity = new MockIdentity('temp-id');
        $currentUser->overrideIdentity($overriddenIdentity);

        $this->assertSame($overriddenIdentity, $currentUser->getIdentity());
    }

    public function testClearIdentityOverride(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $this->createSession(['__auth_id' => $id]),
        );

        $currentUser->overrideIdentity(new MockIdentity('temp-id'));
        $currentUser->clearIdentityOverride();

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession(),
        );

        $identity = $this->createIdentity('test-id');

        $this->assertTrue($currentUser->login($identity));
        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testLoginWithoutSession(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            null,
        );

        $identity = $this->createIdentity('test-id');

        $this->assertTrue($currentUser->login($identity));
        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testLoginAndSetAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session,
        );

        $currentUser->setAbsoluteAuthTimeout(60);
        $currentUser->login($identity);

        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertSame($id, $session->get('__auth_id'));
        $this->assertTrue($session->has('__auth_absolute_expire'));

        // Second getting
        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session,
        );
        $currentUser->setAbsoluteAuthTimeout(60);
        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginAndSetAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session,
        );

        $currentUser->setAuthTimeout(60);
        $currentUser->login($identity);

        $this->assertTrue($session->has('__auth_expire'));
        $this->assertSame($identity, $currentUser->getIdentity());

        // Second getting
        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session,
        );

        $currentUser->setAuthTimeout(60);

        $this->assertSame($identity, $currentUser->getIdentity());

        // Third getting
        $currentUser = new CurrentUser(
            $this->createIdentityRepository($identity),
            $this->createEventDispatcher(),
            $session,
        );

        $currentUser->setAuthTimeout(60);

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession(),
        );

        $currentUser->login($this->createIdentity('test-id'));

        $this->assertTrue($currentUser->logout());
        $this->assertTrue($currentUser->isGuest());

        $this->assertCount(4, $eventDispatcher->getEvents());
        $this->assertSame(
            [
                BeforeLogin::class,
                AfterLogin::class,
                BeforeLogout::class,
                AfterLogout::class,
            ],
            $eventDispatcher->getEventClasses(),
        );
    }

    public function testGuestLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession(),
        );

        $this->assertFalse($currentUser->logout());
        $this->assertEmpty($eventDispatcher->getEvents());
        $this->assertTrue($currentUser->isGuest());
    }

    public function testLogoutWithSetAuthExpire(): void
    {
        $session = $this->createSession();
        $sessionId = $session->getId();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $session,
        );

        $currentUser->setAuthTimeout(60);
        $currentUser->login($this->createIdentity('test-id'));
        $currentUser->logout();

        $this->assertNotSame($sessionId, $session->getId());
        $this->assertFalse($session->has('__auth_id'));
        $this->assertFalse($session->has('__auth_expire'));
        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testCanWithoutAccessChecker(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $this->assertFalse($currentUser->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $currentUser->setAccessChecker(new MockAccessChecker(true));

        $this->assertTrue($currentUser->can('permission'));
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
