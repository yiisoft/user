<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\Access\AccessCheckerInterface;

final class AccessCheckerStub implements AccessCheckerInterface
{
    public function __construct(private array $allowPermissions = [])
    {
    }

    public function userHasPermission($userId, string $permissionName, array $parameters = []): bool
    {
        return in_array($permissionName, $this->allowPermissions);
    }
}
