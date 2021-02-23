<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Event;

use Yiisoft\User\Event\BeforeLogin;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Tests\Mock\MockIdentity;

class BeforeLoginTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new BeforeLogin($identity);

        self::assertSame($identity, $event->getIdentity());
    }

    public function testInvalidate(): void
    {
        $event = new BeforeLogin(new MockIdentity('test'));
        $event->invalidate();

        self::assertFalse($event->isValid());
    }
}
