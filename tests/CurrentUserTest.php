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
use Yiisoft\User\Guest\GuestIdentity;
use Yiisoft\User\Tests\Support\MockAccessChecker;
use Yiisoft\User\Tests\Support\MockArraySessionStorage;
use Yiisoft\User\Tests\Support\MockIdentity;
use Yiisoft\User\Tests\Support\MockIdentityRepository;

final class CurrentUserTest extends TestCase
{
    public function testGetIdentityWithoutLogin(): void
    {
        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withSession($this->createSession())
        ;

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityWithoutLoginAndSession(): void
    {
        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()));

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testGetIdentityFromStorage(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertSame($id, $currentUser->getId());
    }

    public function testGetIdentityIfSessionHasEqualAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id, '__auth_expire' => time()]))
            ->withAuthTimeout(60)
        ;

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeout(): void
    {
        $session = $this->createSession([
            '__auth_id' => 'test-id',
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

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

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

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

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testGetIdentityAndSetAuthExpire(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $session = $this->createSession([
            '__auth_id' => $id,
        ]);

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertTrue($session->has('__auth_expire'));
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeoutAndWithoutId(): void
    {
        $session = $this->createSession([
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
        $this->assertTrue($session->has('__auth_expire'));
    }

    public function testGetOverriddenIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $overriddenIdentity = new MockIdentity('temp-id');
        $currentUser->overrideIdentity($overriddenIdentity);

        $this->assertSame($overriddenIdentity, $currentUser->getIdentity());
    }

    public function testLoginAndGetOverriddenIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withSession($this->createSession())
        ;

        $currentUser->login($identity);
        $overriddenIdentity = new MockIdentity('temp-id');
        $currentUser->overrideIdentity($overriddenIdentity);

        $this->assertSame($overriddenIdentity, $currentUser->getIdentity());
    }

    public function testClearIdentityOverride(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $currentUser->overrideIdentity(new MockIdentity('temp-id'));
        $currentUser->clearIdentityOverride();

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testClear(): void
    {
        $id = 'test-id';
        $identity = new class ($id) implements IdentityInterface {
            private ?string $id;

            public function __construct(string $id)
            {
                $this->id = $id;
            }

            public function getId(): ?string
            {
                $id = $this->id;
                $this->id = null;
                return $id;
            }
        };

        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $this->assertSame($id, $currentUser->getId());

        $currentUser->clear();

        $this->assertNull($currentUser->getId());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $eventDispatcher))
            ->withSession($this->createSession())
        ;

        $identity = $this->createIdentity('test-id');

        $this->assertTrue($currentUser->login($identity));
        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testLoginWithoutSession(): void
    {
        $eventDispatcher = $this->createEventDispatcher();
        $currentUser = new CurrentUser($this->createIdentityRepository(), $eventDispatcher);
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

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $currentUser->login($identity);

        $this->assertSame($identity, $currentUser->getIdentity());
        $this->assertSame($id, $session->get('__auth_id'));
        $this->assertTrue($session->has('__auth_absolute_expire'));

        // Second getting
        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testLoginAndSetAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $currentUser->login($identity);

        $this->assertTrue($session->has('__auth_expire'));
        $this->assertSame($identity, $currentUser->getIdentity());

        // Second getting
        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $currentUser->getIdentity());

        // Third getting
        $currentUser = (new CurrentUser($this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $currentUser->getIdentity());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $eventDispatcher))
            ->withSession($this->createSession())
        ;

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

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $eventDispatcher))
            ->withSession($this->createSession())
        ;

        $this->assertFalse($currentUser->logout());
        $this->assertEmpty($eventDispatcher->getEvents());
        $this->assertTrue($currentUser->isGuest());
    }

    public function testLogoutWithSetAuthExpire(): void
    {
        $session = $this->createSession();
        $sessionId = $session->getId();

        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(3600)
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $currentUser->login($this->createIdentity('test-id'));
        $currentUser->logout();

        $this->assertNotSame($sessionId, $session->getId());
        $this->assertFalse($session->has('__auth_id'));
        $this->assertFalse($session->has('__auth_expire'));
        $this->assertFalse($session->has('__auth_absolute_expire'));
        $this->assertInstanceOf(GuestIdentity::class, $currentUser->getIdentity());
    }

    public function testCanWithoutAccessChecker(): void
    {
        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withSession($this->createSession())
        ;

        $this->assertFalse($currentUser->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $currentUser = (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAccessChecker($this->createAccessChecker(true))
            ->withSession($this->createSession())
        ;

        $this->assertTrue($currentUser->can('permission'));
    }

    public function testImmutable(): void
    {
        $currentUser = new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher());

        $this->assertNotSame($currentUser, $currentUser->withSession($this->createSession()));
        $this->assertNotSame($currentUser, $currentUser->withAccessChecker($this->createAccessChecker()));
        $this->assertNotSame($currentUser, $currentUser->withAbsoluteAuthTimeout(3600));
        $this->assertNotSame($currentUser, $currentUser->withAuthTimeout(60));
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }

    private function createSession(array $data = []): MockArraySessionStorage
    {
        return new MockArraySessionStorage($data);
    }

    private function createAccessChecker(bool $userHasPermission = false): MockAccessChecker
    {
        return new MockAccessChecker($userHasPermission);
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
