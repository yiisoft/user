<?php

declare(strict_types=1);

namespace Yiisoft\User\CurrentUser;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser\Storage\CurrentIdentityIdStorageInterface;
use Yiisoft\User\CurrentUser\Event\AfterLogout;
use Yiisoft\User\CurrentUser\Event\AfterLogin;
use Yiisoft\User\CurrentUser\Event\BeforeLogout;
use Yiisoft\User\CurrentUser\Event\BeforeLogin;
use Yiisoft\User\GuestIdentity;

/**
 * Maintains current identity and allows logging in and out using it.
 */
final class CurrentUser
{
    private CurrentIdentityIdStorageInterface $currentIdentityIdStorage;
    private IdentityRepositoryInterface $identityRepository;
    private EventDispatcherInterface $eventDispatcher;
    private ?AccessCheckerInterface $accessChecker = null;

    private ?IdentityInterface $identity = null;
    private ?IdentityInterface $temporaryIdentity = null;

    public function __construct(
        CurrentIdentityIdStorageInterface $currentIdentityIdStorage,
        IdentityRepositoryInterface $identityRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->currentIdentityIdStorage = $currentIdentityIdStorage;
        $this->identityRepository = $identityRepository;
        $this->eventDispatcher = $eventDispatcher;
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
        $identity = $this->temporaryIdentity ?? $this->identity;

        if ($identity === null) {
            $identity = $this->determineIdentity();
            $this->identity = $identity;
        }

        return $identity;
    }

    private function determineIdentity(): IdentityInterface
    {
        $identity = null;

        $id = $this->currentIdentityIdStorage->get();
        if ($id !== null) {
            $identity = $this->identityRepository->findIdentity($id);
        }

        return $identity ?? new GuestIdentity();
    }

    /**
     * Returns a value that uniquely represents the user.
     *
     * @see CurrentUser::getIdentity()
     *
     * @return string|null The unique identifier for the user. If `null`, it means the user is a guest.     *
     */
    public function getId(): ?string
    {
        return $this->getIdentity()->getId();
    }

    /**
     * Returns a value indicating whether the user is a guest (not authenticated).
     *
     * @see getIdentity()
     *
     * @return bool Whether the current user is a guest.
     */
    public function isGuest(): bool
    {
        return $this->getIdentity() instanceof GuestIdentity;
    }

    /**
     * Checks if the user can perform the operation as specified by the given permission.
     *
     * Note that you must provide access checker via {@see CurrentUser::setAccessChecker()} in order to use this
     * method. Otherwise it will always return `false`.
     *
     * @param string $permissionName The name of the permission (e.g. "edit post") that needs access check.
     * @param array $params Name-value pairs that would be passed to the rules associated with the roles and
     * permissions assigned to the user.
     *
     * @return bool Whether the user can perform the operation as specified by the given permission.
     */
    public function can(string $permissionName, array $params = []): bool
    {
        if ($this->accessChecker === null) {
            return false;
        }

        return $this->accessChecker->userHasPermission($this->getId(), $permissionName, $params);
    }

    /**
     * Logs in a user.
     *
     * @param IdentityInterface $identity The user identity (which should already be authenticated).
     *
     * @return bool Whether the user is logged in.
     */
    public function login(IdentityInterface $identity): bool
    {
        if ($this->beforeLogin($identity)) {
            $this->switchIdentity($identity);
            $this->afterLogin($identity);
        }
        return !$this->isGuest();
    }

    /**
     * This method is called before logging in a user.
     * The default implementation will trigger the {@see BeforeLogin} event.
     *
     * @param IdentityInterface $identity The user identity information.
     *
     * @return bool Whether the user should continue to be logged in.
     */
    private function beforeLogin(IdentityInterface $identity): bool
    {
        $event = new BeforeLogin($identity);
        $this->eventDispatcher->dispatch($event);
        return $event->isValid();
    }

    /**
     * This method is called after the user is successfully logged in.
     *
     * @param IdentityInterface $identity The user identity information.
     */
    private function afterLogin(IdentityInterface $identity): void
    {
        $this->eventDispatcher->dispatch(new AfterLogin($identity));
    }

    /**
     * Logs out the current user.
     *
     * @return bool Whether the user is logged out.
     */
    public function logout(): bool
    {
        if ($this->isGuest()) {
            return false;
        }

        $identity = $this->getIdentity();
        if ($this->beforeLogout($identity)) {
            $this->switchIdentity(new GuestIdentity());
            $this->afterLogout($identity);
        }

        return $this->isGuest();
    }

    /**
     * This method is invoked when calling {@see CurrentUser::logout()} to log out a user.
     *
     * @param IdentityInterface $identity The user identity information.
     *
     * @return bool Whether the user should continue to be logged out.
     */
    private function beforeLogout(IdentityInterface $identity): bool
    {
        $event = new BeforeLogout($identity);
        $this->eventDispatcher->dispatch($event);
        return $event->isValid();
    }

    /**
     * This method is invoked right after a user is logged out via {@see CurrentUser::logout()}.
     *
     * @param IdentityInterface $identity The user identity information.
     */
    private function afterLogout(IdentityInterface $identity): void
    {
        $this->eventDispatcher->dispatch(new AfterLogout($identity));
    }

    public function setTemporaryIdentity(IdentityInterface $identity): void
    {
        $this->temporaryIdentity = $identity;
    }

    public function clearTemporaryIdentity(): void
    {
        $this->temporaryIdentity = null;
    }

    /**
     * Switches to a new identity for the current user.
     *
     * This method is called by {@see CurrentUser::login()} and {@see CurrentUser::logout()}
     * when the current user needs to be associated with the corresponding identity information.
     *
     * @param IdentityInterface $identity The identity information to be associated with the current user.
     * In order to indicate that the user is guest, use {@see GuestIdentity}.
     */
    private function switchIdentity(IdentityInterface $identity): void
    {
        $this->identity = $identity;

        $id = $identity->getId();
        if ($id === null) {
            $this->currentIdentityIdStorage->clear();
        } else {
            $this->currentIdentityIdStorage->set($id);
        }
    }
}
