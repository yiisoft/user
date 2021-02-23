<?php

declare(strict_types=1);

namespace Yiisoft\User\Event;

use Yiisoft\Auth\IdentityInterface;

final class AfterLogin
{
    private IdentityInterface $identity;

    public function __construct(IdentityInterface $identity)
    {
        $this->identity = $identity;
    }

    public function getIdentity(): IdentityInterface
    {
        return $this->identity;
    }
}
