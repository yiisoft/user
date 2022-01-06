<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Yiisoft\Access\AccessCheckerInterface;

final class MockAccessChecker implements AccessCheckerInterface
{
    private bool $userHasPermission;

    public function __construct(bool $userHasPermission)
    {
        $this->userHasPermission = $userHasPermission;
    }

    public function userHasPermission($userId, string $permissionName, array $parameters = []): bool
    {
        if (!is_string($userId) && !is_int($userId)) {
            throw new \InvalidArgumentException('User ID must be a string or integer.');
        }
        return $this->userHasPermission;
    }
}
