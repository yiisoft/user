<?php

namespace Yiisoft\User\Tests\CurrentIdentity\Event;

use Yiisoft\User\CurrentIdentity\Event\AfterLogout;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Tests\Mock\MockIdentity;

class AfterLogoutTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new AfterLogout($identity);

        self::assertSame($identity, $event->getIdentity());
    }
}
