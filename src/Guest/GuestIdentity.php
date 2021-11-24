<?php

declare(strict_types=1);

namespace Yiisoft\User\Guest;

/**
 * Default implementation of the identity interface for a guest non-authenticated user.
 */
final class GuestIdentity implements GuestIdentityInterface
{
    public function getId(): ?string
    {
        return null;
    }
}
