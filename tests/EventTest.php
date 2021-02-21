<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Web\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\CurrentIdentity\Event\AfterLogin;
use Yiisoft\User\CurrentIdentity\Event\AfterLogout;
use Yiisoft\User\CurrentIdentity\Event\BeforeLogin;
use Yiisoft\User\CurrentIdentity\Event\BeforeLogout;
use Yiisoft\User\Tests\Mock\MockIdentity;

final class EventTest extends TestCase
{
    public function testUserAfterLoginEvent(): void
    {
        $event = new AfterLogin($this->createIdentity('test'));
        $this->assertEquals('test', $event->getIdentity()->getId());
    }

    public function testUserAfterLogoutEvent(): void
    {
        $event = new AfterLogout($this->createIdentity('test'));
        $this->assertEquals('test', $event->getIdentity()->getId());
    }

    public function testUserBeforeLogoutEvent(): void
    {
        $event = new BeforeLogout($this->createIdentity('test'));
        $this->assertEquals('test', $event->getIdentity()->getId());
        $this->assertTrue($event->isValid());
    }

    public function testUserBeforeLogoutEventInvalid(): void
    {
        $event = new BeforeLogout($this->createIdentity('test'));
        $event->invalidate();
        $this->assertFalse($event->isValid());
    }

    public function testUserBeforeLoginEvent(): void
    {
        $event = new BeforeLogin($this->createIdentity('test'));
        $this->assertEquals('test', $event->getIdentity()->getId());
        $this->assertTrue($event->isValid());
    }

    public function testUserBeforeLoginEventInvalid(): void
    {
        $event = new BeforeLogin($this->createIdentity('test'));
        $event->invalidate();
        $this->assertFalse($event->isValid());
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }
}
