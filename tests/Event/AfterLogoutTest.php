<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Event;

use Yiisoft\User\Event\AfterLogout;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Tests\Support\MockIdentity;

final class AfterLogoutTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new AfterLogout($identity);

        $this->assertSame($identity, $event->getIdentity());
    }
}
