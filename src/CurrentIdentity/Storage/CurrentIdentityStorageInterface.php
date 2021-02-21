<?php

declare(strict_types=1);

namespace Yiisoft\User\CurrentIdentity\Storage;

interface CurrentIdentityStorageInterface
{
    public function get(): ?string;

    public function set(string $id): void;

    public function clear(): void;
}
