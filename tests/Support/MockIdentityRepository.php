<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Exception;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;

final class MockIdentityRepository implements IdentityRepositoryInterface
{
    private bool $withException = false;

    public function __construct(private ?IdentityInterface $identity = null)
    {
    }

    public function findIdentity(string $id): ?IdentityInterface
    {
        if ($this->withException) {
            throw new Exception();
        }

        return $this->identity;
    }
}
