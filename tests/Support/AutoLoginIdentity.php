<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\User\AutoLoginIdentityInterface;

final class AutoLoginIdentity implements AutoLoginIdentityInterface
{
    public const ID = '42';
    public const KEY_CORRECT = 'auto-login-key-correct';
    public const KEY_INCORRECT = 'auto-login-key-incorrect';
    public bool $rememberMe = false;

    public function getAutoLoginKey(): string
    {
        return self::KEY_CORRECT;
    }

    public function validateAutoLoginKey(string $key): bool
    {
        return $key === $this->getAutoLoginKey();
    }

    public function getId(): ?string
    {
        return self::ID;
    }

    public function getAutoLoginDuration(): ?\DateInterval
    {
        return $this->rememberMe ? new \DateInterval('P2W') : null;
    }
}
