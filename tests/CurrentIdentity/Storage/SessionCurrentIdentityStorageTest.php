<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentIdentity\Storage;

use Yiisoft\User\CurrentIdentity\Storage\SessionCurrentIdentityStorage;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Tests\Mock\MockArraySessionStorage;

class SessionCurrentIdentityStorageTest extends TestCase
{
    public function testGetNull(): void
    {
        $storage = new SessionCurrentIdentityStorage($this->createSession());

        self::assertNull($storage->get());
    }

    public function testGetFromSession(): void
    {
        $id = 'test-id';

        $storage = new SessionCurrentIdentityStorage($this->createSession(['__auth_id' => $id]));

        self::assertSame($id, $storage->get());
    }

    public function testSet(): void
    {
        $id = 'test-id';

        $storage = new SessionCurrentIdentityStorage($this->createSession());
        $storage->set($id);

        self::assertSame($id, $storage->get());
    }

    public function testSetAndSetAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';

        $session = $this->createSession();
        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAbsoluteAuthTimeout(60);
        $storage->set($id);

        self::assertSame($id, $storage->get());
        self::assertTrue($session->has('__auth_absolute_expire'));
    }

    public function testSetAndSetAuthTimeout(): void
    {
        $id = 'test-id';

        $session = $this->createSession();
        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAuthTimeout(60);
        $storage->set($id);

        self::assertTrue($session->has('__auth_expire'));
        self::assertSame($id, $storage->get());
        self::assertSame($id, $storage->get()); // Test double get
    }

    public function testClear(): void
    {
        $storage = new SessionCurrentIdentityStorage($this->createSession(['__auth_id' => 'test-id']));
        $storage->clear();

        self::assertNull($storage->get());
    }

    public function testClearWithSetAuthExpire(): void
    {
        $session = $this->createSession();
        $sessionId = $session->getId();

        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAuthTimeout(60);
        $storage->set('test-id');
        $storage->clear();

        self::assertNotSame($sessionId, $session->getId());
        self::assertFalse($session->has('__auth_id'));
        self::assertFalse($session->has('__auth_expire'));
        self::assertNull($storage->get());
    }

    public function testGetIdIfSessionHasEqualAuthTimeout(): void
    {
        $id = 'test-id';

        $session = $this->createSession([
            '__auth_id' => $id,
            '__auth_expire' => time(),
        ]);

        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAuthTimeout(60);

        self::assertSame($id, $storage->get());
    }

    public function testGetNullIfSessionHasExpiredAuthTimeout(): void
    {
        $session = $this->createSession([
            '__auth_id' => 'test-id',
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAuthTimeout(60);

        self::assertNull($storage->get());
        self::assertFalse($session->has('__auth_id'));
        self::assertFalse($session->has('__auth_expire'));
    }

    public function testGetNullWithoutIdAndIfSessionHasExpiredAuthTimeout(): void
    {
        $session = $this->createSession([
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAuthTimeout(60);

        self::assertNull($storage->get());
        self::assertTrue($session->has('__auth_expire'));
    }

    public function testGetNullIfSessionHasExpiredAbsoluteAuthTimeout(): void
    {
        $storage = new SessionCurrentIdentityStorage($this->createSession([
            '__auth_id' => 'test-id',
            '__auth_absolute_expire' => strtotime('-1 day'),
        ]));
        $storage->setAbsoluteAuthTimeout(60);

        self::assertNull($storage->get());
    }

    public function testGetIdIfSessionHasEqualAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';

        $storage = new SessionCurrentIdentityStorage($this->createSession([
            '__auth_id' => $id,
            '__auth_absolute_expire' => time(),
        ]));
        $storage->setAbsoluteAuthTimeout(60);

        self::assertSame($id, $storage->get());
    }

    public function testGetIdAndSetAuthExpire(): void
    {
        $id = 'test-id';

        $session = $this->createSession(['__auth_id' => $id]);
        $storage = new SessionCurrentIdentityStorage($session);
        $storage->setAuthTimeout(60);

        self::assertSame($id, $storage->get());
        self::assertTrue($session->has('__auth_expire'));
    }

    private function createSession(array $data = []): MockArraySessionStorage
    {
        return new MockArraySessionStorage($data);
    }
}
