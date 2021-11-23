<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\Auth\IdentityInterface;

final class MockIdentity implements IdentityInterface
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
