<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\Auth\IdentityInterface;

final class MockIdentity implements IdentityInterface
{
    public function __construct(private string $id)
    {
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
