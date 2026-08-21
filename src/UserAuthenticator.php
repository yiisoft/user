<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticatorInterface;
use Yiisoft\Auth\IdentityInterface;

/**
 * Implementation of the AuthenticatorInterface for the user.
 */
final class UserAuthenticator implements AuthenticatorInterface
{
    public function __construct(private CurrentUser $currentUser) {}

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        if ($this->currentUser->isGuest()) {
            return null;
        }

        return $this->currentUser->getIdentity();
    }
}
