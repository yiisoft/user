<?php

declare(strict_types=1);

namespace Yiisoft\User\Event;

use Yiisoft\Auth\IdentityInterface;

final class AfterLogout
{
    public function __construct(private IdentityInterface $identity)
    {
    }

    public function getIdentity(): IdentityInterface
    {
        return $this->identity;
    }
}
