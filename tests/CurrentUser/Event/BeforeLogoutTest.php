<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentUser\Event;

use Yiisoft\User\CurrentUser\Event\BeforeLogout;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Tests\Mock\MockIdentity;

class BeforeLogoutTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new BeforeLogout($identity);

        self::assertSame($identity, $event->getIdentity());
    }

    public function testInvalidate(): void
    {
        $event = new BeforeLogout(new MockIdentity('test'));
        $event->invalidate();

        self::assertFalse($event->isValid());
    }
}
