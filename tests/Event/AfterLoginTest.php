<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Event;

use PHPUnit\Framework\TestCase;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Tests\Support\MockIdentity;

final class AfterLoginTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new AfterLogin($identity);

        $this->assertSame($identity, $event->getIdentity());
    }
}
