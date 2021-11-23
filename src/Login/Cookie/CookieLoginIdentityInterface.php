<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use DateInterval;
use Yiisoft\Auth\IdentityInterface;

/**
 * `CookieLoginIdentityInterface` should be implemented in order to automatically log user in based on a cookie.
 *
 * @see CookieLogin
 * @see CookieLoginMiddleware
 */
interface CookieLoginIdentityInterface extends IdentityInterface
{
    /**
     * Returns a key that can be used to check the validity of a given identity ID.
     *
     * The key should be unique for each individual user, and should be persistent
     * so that it can be used to check the validity of the user identity.
     *
     * The space of such keys should be big enough to defeat potential identity attacks.
     *
     * The returned key will be stored on the client side as part of a cookie and will be used to authenticate user even
     * if PHP session has been expired.
     *
     * Make sure to invalidate earlier issued keys when you implement force user logout, password change and
     * other scenarios, that require forceful access revocation for old sessions.
     *
     * @return string A key that is used to check the validity of a given identity ID.
     *
     * @see validateCookieLoginKey()
     */
    public function getCookieLoginKey(): string;

    /**
     * Returns an interval until the expires.
     *
     * @return DateInterval|null Interval until the expires.
     */
    public function getCookieLoginDuration(): ?DateInterval;

    /**
     * Validates the given key.
     *
     * @param string $key the given key
     *
     * @return bool whether the given key is valid.
     *
     * @see getCookieLoginKey()
     */
    public function validateCookieLoginKey(string $key): bool;
}
