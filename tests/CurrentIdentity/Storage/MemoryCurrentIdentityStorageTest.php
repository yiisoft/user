<?php

namespace Yiisoft\User\Tests\CurrentIdentity\Storage;

use Yiisoft\User\CurrentIdentity\Storage\MemoryCurrentIdentityStorage;
use PHPUnit\Framework\TestCase;

class MemoryCurrentIdentityStorageTest extends TestCase
{
    public function testGetNull(): void
    {
        $storage = new MemoryCurrentIdentityStorage();

        self::assertNull($storage->get());
    }

    public function testSet(): void
    {
        $id = 'test-id';

        $storage = new MemoryCurrentIdentityStorage();
        $storage->set($id);

        self::assertSame($id, $storage->get());
    }

    public function testClear(): void
    {
        $storage = new MemoryCurrentIdentityStorage();
        $storage->set('test-id');
        $storage->clear();

        self::assertNull($storage->get());
    }
}
