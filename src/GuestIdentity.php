<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Yiisoft\Auth\IdentityInterface;

/**
 * Implementation of the identity interface for a guest non-authenticated user.
 */
final class GuestIdentity implements IdentityInterface
{
    public function getId(): ?string
    {
        return null;
    }
}
