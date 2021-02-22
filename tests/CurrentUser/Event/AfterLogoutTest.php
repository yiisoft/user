<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentUser\Event;

use Yiisoft\User\CurrentUser\Event\AfterLogout;
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
