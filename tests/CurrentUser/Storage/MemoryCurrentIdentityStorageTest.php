<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentUser\Storage;

use Yiisoft\User\CurrentUser\Storage\MemoryCurrentIdentityIdStorage;
use PHPUnit\Framework\TestCase;

class MemoryCurrentIdentityStorageTest extends TestCase
{
    public function testGetNull(): void
    {
        $storage = new MemoryCurrentIdentityIdStorage();

        self::assertNull($storage->get());
    }

    public function testSet(): void
    {
        $id = 'test-id';

        $storage = new MemoryCurrentIdentityIdStorage();
        $storage->set($id);

        self::assertSame($id, $storage->get());
    }

    public function testClear(): void
    {
        $storage = new MemoryCurrentIdentityIdStorage();
        $storage->set('test-id');
        $storage->clear();

        self::assertNull($storage->get());
    }
}
