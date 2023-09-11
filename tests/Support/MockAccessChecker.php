<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\Access\AccessCheckerInterface;

final class MockAccessChecker implements AccessCheckerInterface
{
    public function __construct(private bool $userHasPermission)
    {
    }

    public function userHasPermission($userId, string $permissionName, array $parameters = []): bool
    {
        return $this->userHasPermission;
    }
}
