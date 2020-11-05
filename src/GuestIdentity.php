<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Yiisoft\Auth\IdentityInterface;

final class GuestIdentity implements IdentityInterface
{
    public function getId(): ?string
    {
        return null;
    }
}
