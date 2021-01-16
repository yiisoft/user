<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Event\BeforeLogout;
use Yiisoft\User\GuestIdentity;
use Yiisoft\User\Tests\Mock\MockAccessChecker;
use Yiisoft\User\Tests\Mock\MockArraySessionStorage;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentity;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;
use Yiisoft\User\User;

final class UserTest extends TestCase
{
    public function testGetIdentityMethodReturnsGuestWithoutSession(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher()
        );

        $this->assertInstanceOf(GuestIdentity::class, $user->getIdentity());
    }

    public function testGetIdentityMethodReturnsGuestWithSession(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher(),
            $this->createSessionStorage()
        );

        $this->assertInstanceOf(GuestIdentity::class, $user->getIdentity());
    }

    public function testGetIdentityMethodReturnsIdentitySet(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher()
        );

        $user->setIdentity($this->createIdentity('test-id'));
        $this->assertEquals('test-id', $user->getIdentity()->getId());
    }

    public function testGetIdentityReturnsGuestIfSessionHasExpiredAuthTimeout(): void
    {
        $repository = $this->createIdentityRepository(
            $this->createIdentity('test-id')
        );

        $sessionStorage = $this->createSessionStorage(
            [
                '__auth_id' => 'test-id',
                '__auth_expire' => strtotime('-1 day'),
            ]
        );

        $user = new User(
            $repository,
            $this->createDispatcher(),
            $sessionStorage
        );

        $user->setAuthTimeout(60);

        $this->assertInstanceOf(GuestIdentity::class, $user->getIdentity());
    }

    public function testGetIdentityReturnsGuestIfSessionHasExpiredAbsoluteAuthTimeout(): void
    {
        $repository = $this->createIdentityRepository(
            $this->createIdentity('test-id')
        );

        $sessionStorage = $this->createSessionStorage(
            [
                '__auth_id' => 'test-id',
                '__auth_absolute_expire' => strtotime('-1 day'),
            ]
        );

        $user = new User(
            $repository,
            $this->createDispatcher(),
            $sessionStorage
        );

        $user->setAbsoluteAuthTimeout(60);

        $this->assertInstanceOf(GuestIdentity::class, $user->getIdentity());
    }

    public function testGetIdentityExpectException(): void
    {
        $this->expectException(\Exception::class);

        $user = new User(
            $this->createIdentityRepositoryWithException(),
            $this->createDispatcher(),
            $this->createSessionStorage(['__auth_id' => '123456'])
        );

        $user->getIdentity();
    }

    public function testGetIdentityReturnsCorrectValueAndSetAuthExpire(): void
    {
        $repository = $this->createIdentityRepository(
            $this->createIdentity('test-id')
        );
        $sessionStorage = $this->createSessionStorage(['__auth_id' => 'test-id']);
        $user = new User($repository, $this->createDispatcher(), $sessionStorage);

        $user->setAuthTimeout(60);

        $this->assertEquals('test-id', $user->getIdentity()->getId());
        $this->assertTrue($sessionStorage->has('__auth_expire'));
    }

    public function testLogin(): void
    {
        $dispatcher = $this->createDispatcher();

        $user = new User(
            $this->createIdentityRepository(),
            $dispatcher,
            $this->createSessionStorage()
        );

        $this->assertTrue($user->login($this->createIdentity('test-id')));
        $this->assertEquals(
            [
                BeforeLogin::class,
                AfterLogin::class,
            ],
            $dispatcher->getClassesEvents()
        );

        $this->assertEquals('test-id', $user->getIdentity()->getId());
    }

    public function testGetIdReturnsCorrectValue(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher()
        );

        $user->setIdentity($this->createIdentity('test-id'));
        $this->assertEquals('test-id', $user->getId());
    }

    public function testGetIdReturnsNullIfGuest(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher()
        );

        $this->assertNull($user->getId());
    }

    public function testSuccessfulLogout(): void
    {
        $dispatcher = $this->createDispatcher();
        $user = new User(
            $this->createIdentityRepository(),
            $dispatcher
        );

        $user->setIdentity($this->createIdentity('test-id'));

        $this->assertTrue($user->logout());
        $this->assertEquals(
            [
                BeforeLogout::class,
                AfterLogout::class,
            ],
            $dispatcher->getClassesEvents()
        );
        $this->assertTrue($user->isGuest());
    }

    public function testGuestLogout(): void
    {
        $dispatcher = $this->createDispatcher();
        $repository = $this->createIdentityRepository(
            $this->createIdentity('test-id')
        );

        $user = new User($repository, $dispatcher);

        $this->assertFalse($user->logout());
        $this->assertEmpty($dispatcher->getClassesEvents());
        $this->assertTrue($user->isGuest());
    }

    public function testLogoutWithSession(): void
    {
        $identity = $this->createIdentity('test-id');

        $sessionStorage = $this->createSessionStorage();
        $sessionStorage->open();

        $user = new User(
            $this->createIdentityRepository($identity),
            $this->createDispatcher(),
            $sessionStorage
        );

        $user->setIdentity($identity);

        $this->assertTrue($user->logout());
        $this->assertFalse($sessionStorage->isActive());
    }

    public function testCanReturnsFalseIfCheckerNotSet(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher(),
            $this->createSessionStorage()
        );

        $this->assertFalse($user->can('permission'));
    }

    public function testCanWithAccessChecker(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher(),
            $this->createSessionStorage()
        );

        $user->setAccessChecker($this->createAccessChecker());

        $this->assertTrue($user->can('permission'));
    }

    public function testSwitchIdentity(): void
    {
        $expire = strtotime('+1 day');
        $sessionStorage = $this->createSessionStorage(
            [
                '__auth_id' => 'test-id',
                '__auth_expire' => $expire,
            ]
        );

        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher(),
            $sessionStorage
        );

        $user->setAuthTimeout(60);
        $user->setAbsoluteAuthTimeout(3600);

        $user->setIdentity($this->createIdentity('test-id'));
        $user->switchIdentity($this->createIdentity('test-id-2'));

        $this->assertEquals('test-id-2', $user->getIdentity()->getId());
        $this->assertNotEquals('test-id', $sessionStorage->get('__auth_id'));
        $this->assertNotEquals($expire, $sessionStorage->get('__auth_expire'));
        $this->assertTrue($sessionStorage->has('__auth_expire'));
        $this->assertTrue($sessionStorage->has('__auth_absolute_expire'));
    }

    public function testSwitchIdentityToGuest(): void
    {
        $user = new User(
            $this->createIdentityRepository(),
            $this->createDispatcher()
        );

        $user->setAuthTimeout(60);

        $user->setIdentity($this->createIdentity('test-id'));
        $user->switchIdentity(new GuestIdentity());

        $this->assertInstanceOf(GuestIdentity::class, $user->getIdentity());
    }

    private function createIdentityRepository(?IdentityInterface $identity = null): IdentityRepositoryInterface
    {
        return new MockIdentityRepository($identity);
    }

    private function createIdentityRepositoryWithException(): IdentityRepositoryInterface
    {
        $repository = new MockIdentityRepository();
        $repository->withException();

        return $repository;
    }

    private function createDispatcher(): EventDispatcherInterface
    {
        return new MockEventDispatcher();
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }

    private function createSessionStorage(array $data = []): SessionInterface
    {
        return new MockArraySessionStorage($data);
    }

    private function createAccessChecker(): AccessCheckerInterface
    {
        return new MockAccessChecker(true);
    }
}
