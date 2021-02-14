<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Throwable;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Auth\IdentityInterface;

class CurrentUser
{
    private ?AccessCheckerInterface $accessChecker = null;

    private Authenticator $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function setAccessChecker(AccessCheckerInterface $accessChecker): void
    {
        $this->accessChecker = $accessChecker;
    }

    /**
     * Returns the identity object associated with the currently logged-in user.
     */
    public function getIdentity(): IdentityInterface
    {
        return $this->authenticator->getIdentity();
    }

    /**
     * Returns a value indicating whether the user is a guest (not authenticated).
     *
     * @return bool whether the current user is a guest.
     */
    public function isGuest(): bool
    {
        return $this->authenticator->isGuest();
    }

    /**
     * Returns a value that uniquely represents the user.
     *
     * @throws Throwable
     *
     * @return string the unique identifier for the user. If `null`, it means the user is a guest.
     *
     * @see getIdentity()
     */
    public function getId(): ?string
    {
        return $this->authenticator->getIdentity()->getId();
    }

    /**
     * Checks if the user can perform the operation as specified by the given permission.
     *
     * Note that you must provide access checker via {{@see CurrentUser::setAccessChecker()}} in order to use this method.
     * Otherwise it will always return false.
     *
     * @param string $permissionName the name of the permission (e.g. "edit post") that needs access check.
     * @param array $params name-value pairs that would be passed to the rules associated
     * with the roles and permissions assigned to the user.
     *
     * @throws Throwable
     *
     * @return bool whether the user can perform the operation as specified by the given permission.
     */
    public function can(string $permissionName, array $params = []): bool
    {
        if ($this->accessChecker === null) {
            return false;
        }

        return $this->accessChecker->userHasPermission($this->getId(), $permissionName, $params);
    }
}
