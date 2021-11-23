<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use DateInterval;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

final class CookieLoginIdentity implements CookieLoginIdentityInterface
{
    public const ID = '42';
    public const KEY_CORRECT = 'auto-login-key-correct';
    public const KEY_INCORRECT = 'auto-login-key-incorrect';
    public bool $rememberMe = false;

    public function getCookieLoginKey(): string
    {
        return self::KEY_CORRECT;
    }

    public function validateCookieLoginKey(string $key): bool
    {
        return $key === $this->getCookieLoginKey();
    }

    public function getId(): ?string
    {
        return self::ID;
    }

    public function getCookieLoginDuration(): ?DateInterval
    {
        return $this->rememberMe ? new DateInterval('P2W') : null;
    }
}
