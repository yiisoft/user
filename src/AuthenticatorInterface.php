<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Yiisoft\Auth\IdentityInterface;

interface AuthenticatorInterface
{
    /**
     * Returns the identity object associated with the currently logged-in user.
     *
     * @param bool $autoRenew whether to automatically renew authentication status if it has not been done so before.
     *
     * @return IdentityInterface the identity object associated with the currently logged-in user.
     */
    public function getIdentity(bool $autoRenew = true): IdentityInterface;

    /**
     * Logs in a user.
     *
     * @param IdentityInterface $identity the user identity (which should already be authenticated)
     *
     * @return bool whether the user is logged in
     */
    public function login(IdentityInterface $identity): bool;

    /**
     * Logs out the current user.
     * This will remove authentication-related session data.
     * If `$destroySession` is true, all session data will be removed.
     *
     * @return bool whether the user is logged out
     */
    public function logout(): bool;
}
