<?php

declare(strict_types=1);

namespace Yiisoft\User;

use BackedEnum;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\BeforeLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Guest\GuestIdentity;
use Yiisoft\User\Guest\GuestIdentityFactory;
use Yiisoft\User\Guest\GuestIdentityFactoryInterface;
use Yiisoft\User\Guest\GuestIdentityInterface;

use function time;

/**
 * Maintains current identity and allows logging in and out using it.
 */
final class CurrentUser
{
    private const SESSION_AUTH_ID = '__auth_id';
    private const SESSION_AUTH_EXPIRE = '__auth_expire';
    private const SESSION_AUTH_ABSOLUTE_EXPIRE = '__auth_absolute_expire';
    private GuestIdentityFactoryInterface $guestIdentityFactory;
    private ?AccessCheckerInterface $accessChecker = null;
    private ?SessionInterface $session = null;

    private ?IdentityInterface $identity = null;
    private ?IdentityInterface $identityOverride = null;

    private ?int $authTimeout = null;
    private ?int $absoluteAuthTimeout = null;

    public function __construct(
        private IdentityRepositoryInterface $identityRepository,
        private EventDispatcherInterface $eventDispatcher,
        ?GuestIdentityFactoryInterface $guestIdentityFactory = null
    ) {
        $this->guestIdentityFactory = $guestIdentityFactory ?? new GuestIdentityFactory();
    }

    /**
     * Returns a new instance with the specified session to store current user ID and auth timeouts.
     *
     * @param SessionInterface $session The session instance.
     */
    public function withSession(SessionInterface $session): self
    {
        $new = clone $this;
        $new->session = $session;
        return $new;
    }

    /**
     * Returns a new instance with the specified access checker to check user permissions {@see can()}.
     *
     * @param AccessCheckerInterface $accessChecker The access checker instance.
     */
    public function withAccessChecker(AccessCheckerInterface $accessChecker): self
    {
        $new = clone $this;
        $new->accessChecker = $accessChecker;
        return $new;
    }

    /**
     * Returns a new instance with the specified number of seconds in which
     * the user will be logged out automatically in case of remaining inactive.
     *
     * @param int $timeout The number of seconds in which the user will be logged out automatically in case of
     * remaining inactive. Default is `null`, the user will be logged out after the current session expires.
     */
    public function withAuthTimeout(int $timeout): self
    {
        $new = clone $this;
        $new->authTimeout = $timeout;
        return $new;
    }

    /**
     * Returns a new instance with the specified number of seconds in which
     * the user will be logged out automatically regardless of activity.
     *
     * @param int $timeout The number of seconds in which the user will be logged out automatically regardless
     * of activity. Default is `null`, the user will be logged out after the current session expires.
     */
    public function withAbsoluteAuthTimeout(int $timeout): self
    {
        $new = clone $this;
        $new->absoluteAuthTimeout = $timeout;
        return $new;
    }

    /**
     * Returns the identity object associated with the currently logged-in user.
     */
    public function getIdentity(): IdentityInterface
    {
        $identity = $this->identityOverride ?? $this->identity;

        if ($identity === null) {
            $id = $this->getSavedId();

            if ($id !== null) {
                $identity = $this->identityRepository->findIdentity($id);
            }

            $identity ??= $this->guestIdentityFactory->create();
            $this->identity = $identity;
        }

        return $identity;
    }

    /**
     * Returns a value that uniquely represents the user.
     *
     * @return string|null The unique identifier for the user. If `null`, it means the user is a guest.
     *
     * @see getIdentity()
     */
    public function getId(): ?string
    {
        return $this->getIdentity()->getId();
    }

    /**
     * Returns a value indicating whether the user is a guest (not authenticated).
     *
     * @return bool Whether the current user is a guest.
     *
     * @see getIdentity()
     */
    public function isGuest(): bool
    {
        return $this->getIdentity() instanceof GuestIdentityInterface;
    }

    /**
     * Checks if the user can perform the operation as specified by the given permission.
     *
     * Note that you must provide access checker via {@see withAccessChecker()} in order to use this
     * method. Otherwise, it will always return `false`.
     *
     * @param BackedEnum|string $permissionName The name of the permission (e.g. "edit post") that needs access check.
     * You can use backed enumerations as permission name, in this case the value of the enumeration will be used.
     * @param array $params Name-value pairs that would be passed to the rules associated with the roles and
     * permissions assigned to the user.
     *
     * @return bool Whether the user can perform the operation as specified by the given permission.
     */
    public function can(string|BackedEnum $permissionName, array $params = []): bool
    {
        if ($this->accessChecker === null) {
            return false;
        }

        if ($permissionName instanceof BackedEnum) {
            $permissionName = (string) $permissionName->value;
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
            $this->switchIdentity($this->guestIdentityFactory->create());
            $this->afterLogout($identity);
        }

        return $this->isGuest();
    }

    /**
     * Overrides identity.
     *
     * @param IdentityInterface $identity The identity instance to overriding.
     */
    public function overrideIdentity(IdentityInterface $identity): void
    {
        $this->identityOverride = $identity;
    }

    /**
     * Clears the identity override.
     */
    public function clearIdentityOverride(): void
    {
        $this->identityOverride = null;
    }

    /**
     * Clears the data for working with the event loop.
     */
    public function clear(): void
    {
        $this->identity = null;
        $this->identityOverride = null;
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
        /** @var BeforeLogin $event */
        $event = $this->eventDispatcher->dispatch($event);
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
     * This method is invoked when calling {@see logout()} to log out a user.
     *
     * @param IdentityInterface $identity The user identity information.
     *
     * @return bool Whether the user should continue to be logged out.
     */
    private function beforeLogout(IdentityInterface $identity): bool
    {
        $event = new BeforeLogout($identity);
        /** @var BeforeLogout $event */
        $event = $this->eventDispatcher->dispatch($event);
        return $event->isValid();
    }

    /**
     * This method is invoked right after a user is logged out via {@see logout()}.
     *
     * @param IdentityInterface $identity The user identity information.
     */
    private function afterLogout(IdentityInterface $identity): void
    {
        $this->eventDispatcher->dispatch(new AfterLogout($identity));
    }

    /**
     * Switches to a new identity for the current user.
     *
     * This method is called by {@see login()} and {@see logout()} when the current
     * user needs to be associated with the corresponding identity information.
     *
     * @param IdentityInterface $identity The identity information to be associated with the current user.
     * In order to indicate that the user is guest, use {@see GuestIdentityInterface, GuestIdentity}.
     */
    private function switchIdentity(IdentityInterface $identity): void
    {
        $this->identity = $identity;
        $this->saveId($identity instanceof GuestIdentityInterface ? null : $identity->getId());
    }

    private function getSavedId(): ?string
    {
        if ($this->session === null) {
            return null;
        }

        /** @var mixed $id */
        $id = $this->session->get(self::SESSION_AUTH_ID);

        if ($id !== null && ($this->authTimeout !== null || $this->absoluteAuthTimeout !== null)) {
            $expire = $this->getExpire();
            $expireAbsolute = $this->getExpireAbsolute();

            if (($expire !== null && $expire < time()) || ($expireAbsolute !== null && $expireAbsolute < time())) {
                $this->saveId(null);
                return null;
            }

            if ($this->authTimeout !== null) {
                $this->session->set(self::SESSION_AUTH_EXPIRE, time() + $this->authTimeout);
            }
        }

        return $id === null ? null : (string) $id;
    }

    private function getExpire(): ?int
    {
        /**
         * @var mixed $expire
         *
         * @psalm-suppress PossiblyNullReference
         */
        $expire = $this->authTimeout !== null
            ? $this->session->get(self::SESSION_AUTH_EXPIRE)
            : null
        ;

        return $expire !== null ? (int) $expire : null;
    }

    private function getExpireAbsolute(): ?int
    {
        /**
         * @var mixed $expire
         *
         * @psalm-suppress PossiblyNullReference
         */
        $expire = $this->absoluteAuthTimeout !== null
            ? $this->session->get(self::SESSION_AUTH_ABSOLUTE_EXPIRE)
            : null
        ;

        return $expire !== null ? (int) $expire : null;
    }

    private function saveId(?string $id): void
    {
        if ($this->session === null) {
            return;
        }

        $this->session->regenerateID();

        $this->session->remove(self::SESSION_AUTH_ID);
        $this->session->remove(self::SESSION_AUTH_EXPIRE);
        $this->session->remove(self::SESSION_AUTH_ABSOLUTE_EXPIRE);

        if ($id === null) {
            return;
        }

        $this->session->set(self::SESSION_AUTH_ID, $id);

        if ($this->authTimeout !== null) {
            $this->session->set(self::SESSION_AUTH_EXPIRE, time() + $this->authTimeout);
        }

        if ($this->absoluteAuthTimeout !== null) {
            $this->session->set(self::SESSION_AUTH_ABSOLUTE_EXPIRE, time() + $this->absoluteAuthTimeout);
        }
    }
}
