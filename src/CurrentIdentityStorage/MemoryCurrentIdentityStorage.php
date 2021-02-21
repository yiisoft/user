<?php

declare(strict_types=1);

namespace Yiisoft\User\CurrentIdentityStorage;

final class MemoryCurrentIdentityStorage implements CurrentIdentityStorageInterface
{
    private ?string $id = null;

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
