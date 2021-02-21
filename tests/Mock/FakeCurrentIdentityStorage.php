<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Mock;

use Yiisoft\User\CurrentIdentityStorage\CurrentIdentityStorageInterface;

final class FakeCurrentIdentityStorage implements CurrentIdentityStorageInterface
{
    private ?string $id;

    public function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    public function get(): ?string
    {
        return $this->id;
    }

    public function set(string $id): void
    {
        $this->id = $id;
    }

    public function clear(): void
    {
        $this->id = null;
    }
}
