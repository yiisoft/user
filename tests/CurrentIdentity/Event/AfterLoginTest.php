<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\CurrentIdentity\Event;

use PHPUnit\Framework\TestCase;
use Yiisoft\User\CurrentIdentity\Event\AfterLogin;
use Yiisoft\User\Tests\Mock\MockIdentity;

final class AfterLoginTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new AfterLogin($identity);

        self::assertSame($identity, $event->getIdentity());
    }
}
