<?php

declare(strict_types=1);

namespace Yiisoft\User\CurrentIdentity;

use Yiisoft\Auth\IdentityInterface;

interface CurrentIdentityInterface
{
    /**
     * Returns the identity object associated with the currently logged-in user.
     *
     * @param bool $autoRenew whether to automatically renew authentication status if it has not been done so before.
     *
     * @return IdentityInterface the identity object associated with the currently logged-in user.
     */
    public function get(bool $autoRenew = true): IdentityInterface;

    public function set(IdentityInterface $identity): void;

    public function save(): void;

    public function clear(): void;
}
