<?php

declare(strict_types=1);

namespace Yiisoft\User\CurrentIdentity\Storage;

/**
 * Defines how current identity ID is stored.
 */
interface CurrentIdentityStorageInterface
{
    /**
     * Get current identity ID.
     *
     * @return string|null Current identity ID or null if no identity is currently set.
     */
    public function get(): ?string;

    /**
     * Set current identity ID.
     *
     * @param string $id Current identity ID.
     */
    public function set(string $id): void;

    /**
     * Remove stored identity ID.
     */
    public function clear(): void;
}
