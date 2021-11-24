<?php

declare(strict_types=1);

namespace Yiisoft\User\Guest;

/**
 * Creates a default implementation of the identity interface for a guest non-authenticated user.
 */
final class GuestIdentityFactory implements GuestIdentityFactoryInterface
{
    public function create(): GuestIdentityInterface
    {
        return new GuestIdentity();
    }
}
