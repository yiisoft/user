<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Mock;

use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\CurrentIdentity\CurrentIdentityInterface;
use Yiisoft\User\GuestIdentity;

final class StubCurrentIdentity implements CurrentIdentityInterface
{
    private IdentityInterface $identity;

    public function __construct(IdentityInterface $identity = null)
    {
        $this->identity = $identity ?? new GuestIdentity();
    }

    public function get(bool $autoRenew = true): IdentityInterface
    {
        return $this->identity;
    }

    public function set(IdentityInterface $identity): void
    {
        $this->identity = $identity;
    }

    public function save(): void
    {
    }

    public function clear(): void
    {
        $this->identity = new GuestIdentity();
    }
}
