<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\Session\SessionInterface;

use function hash;
use function uniqid;

final class MockArraySessionStorage implements SessionInterface
{
    private ?string $id = '';

    private string $name = '';

    private bool $started = false;

    private bool $closed = false;

    public function __construct(private array $data = [])
    {
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->id = null;
    }

    public function open(): void
    {
        if ($this->isActive()) {
            return;
        }

        if (empty($this->id)) {
            $this->id = $this->generateId();
        }

        $this->started = true;
        $this->closed = false;
    }

    public function isActive(): bool
    {
        return $this->started && !$this->closed;
    }

    public function regenerateId(): void
    {
        $this->id = $this->generateId();
    }

    public function discard(): void
    {
        $this->close();
    }

    public function all(): array
    {
        return $this->data;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function pull(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;
        $this->remove($key);
        return $value;
    }

    public function destroy(): void
    {
        $this->close();
    }

    public function getCookieParameters(): array
    {
        return [];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $sessionId): void
    {
        $this->id = $sessionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    private function generateId(): string
    {
        return hash('sha256', uniqid('ss_mock_', true));
    }
}
