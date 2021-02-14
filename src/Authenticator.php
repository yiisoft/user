<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Event\BeforeLogout;

final class Authenticator
{
    private const SESSION_AUTH_ID = '__auth_id';
    private const SESSION_AUTH_EXPIRE = '__auth_expire';
    private const SESSION_AUTH_ABSOLUTE_EXPIRE = '__auth_absolute_expire';

    private ?IdentityInterface $identity = null;

    /**
     * @var int|null the number of seconds in which the user will be logged out automatically in case of
     * remaining inactive. If this property is not set, the user will be logged out after
     * the current session expires.
     */
    private ?int $authTimeout = null;

    /**
     * @var int|null the number of seconds in which the user will be logged out automatically
     * regardless of activity.
     */
    private ?int $absoluteAuthTimeout = null;

    private IdentityRepositoryInterface $identityRepository;
    private EventDispatcherInterface $eventDispatcher;
    private ?SessionInterface $session;

    /**
     * @param SessionInterface|null $session session to persist authentication status across multiple requests.
     * If not set, authentication has to be performed on each request, which is often the case for stateless
     * application such as RESTful API.
     */
    public function __construct(
        IdentityRepositoryInterface $identityRepository,
        EventDispatcherInterface $eventDispatcher,
        SessionInterface $session = null
    ) {
        $this->identityRepository = $identityRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;
    }

    public function setAuthTimeout(int $timeout = null): self
    {
        $this->authTimeout = $timeout;
        return $this;
    }

    public function setAbsoluteAuthTimeout(int $timeout = null): self
    {
        $this->absoluteAuthTimeout = $timeout;
        return $this;
    }

    /**
     * Returns the identity object associated with the currently logged-in user.
     * This method read the user's authentication data
     * stored in session and reconstruct the corresponding identity object, if it has not done so before.
     *
     * @param bool $autoRenew whether to automatically renew authentication status if it has not been done so before.
     *
     * @throws Throwable
     *
     * @return IdentityInterface the identity object associated with the currently logged-in user.
     *
     * @see logout()
     * @see login()
     */
    public function getIdentity(bool $autoRenew = true): IdentityInterface
    {
        if ($this->identity !== null) {
            return $this->identity;
        }
        if ($this->session === null || !$autoRenew) {
            return new GuestIdentity();
        }
        try {
            $this->renewAuthStatus();
        } catch (Throwable $e) {
            $this->identity = null;
            throw $e;
        }

        /** @psalm-suppress TypeDoesNotContainType */
        return $this->identity ?? new GuestIdentity();
    }

    /**
     * Switches to a new identity for the current user.
     *
     * This method use session to store the user identity information.
     * Please refer to {@see login()} for more details.
     *
     * This method is mainly called by {@see login()} and {@see logout()}
     * when the current user needs to be associated with the corresponding identity information.
     *
     * @param IdentityInterface $identity the identity information to be associated with the current user.
     * In order to indicate that the user is guest, use {{@see GuestIdentity}}.
     */
    public function setIdentity(IdentityInterface $identity): void
    {
        $this->identity = $identity;
        if ($this->session === null) {
            return;
        }

        $this->session->regenerateID();

        $this->session->remove(self::SESSION_AUTH_ID);
        $this->session->remove(self::SESSION_AUTH_EXPIRE);

        if ($identity->getId() === null) {
            return;
        }
        $this->session->set(self::SESSION_AUTH_ID, $identity->getId());
        if ($this->authTimeout !== null) {
            $this->session->set(self::SESSION_AUTH_EXPIRE, time() + $this->authTimeout);
        }
        if ($this->absoluteAuthTimeout !== null) {
            $this->session->set(self::SESSION_AUTH_ABSOLUTE_EXPIRE, time() + $this->absoluteAuthTimeout);
        }
    }

    /**
     * Returns a value indicating whether the user is a guest (not authenticated).
     *
     * @return bool whether the current user is a guest.
     *
     * @see getIdentity()
     */
    private function isGuest(): bool
    {
        return $this->getIdentity() instanceof GuestIdentity;
    }

    /**
     * Logs in a user.
     *
     * After logging in a user:
     * - the user's identity information is obtainable from the {@see getIdentity()}
     * - the identity information will be stored in session and be available in the next requests as long as the session
     *   remains active or till the user closes the browser. Some browsers, such as Chrome, are keeping session when
     *   browser is re-opened.
     *
     * @param IdentityInterface $identity the user identity (which should already be authenticated)
     *
     * @return bool whether the user is logged in
     */
    public function login(IdentityInterface $identity): bool
    {
        if ($this->beforeLogin($identity)) {
            $this->setIdentity($identity);
            $this->afterLogin($identity);
        }
        return !$this->isGuest();
    }

    /**
     * This method is called before logging in a user.
     * The default implementation will trigger the {@see BeforeLogin} event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     *
     * @param IdentityInterface $identity the user identity information
     *
     * @return bool whether the user should continue to be logged in
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
     * @param IdentityInterface $identity the user identity information
     */
    private function afterLogin(IdentityInterface $identity): void
    {
        $this->eventDispatcher->dispatch(new AfterLogin($identity));
    }

    /**
     * Logs out the current user.
     * This will remove authentication-related session data.
     * If `$destroySession` is true, all session data will be removed.
     *
     * @param bool $destroySession whether to destroy the whole session. Defaults to true.
     *
     * @throws Throwable
     *
     * @return bool whether the user is logged out
     */
    public function logout(bool $destroySession = true): bool
    {
        $identity = $this->getIdentity();
        if ($this->isGuest()) {
            return false;
        }
        if ($this->beforeLogout($identity)) {
            $this->setIdentity(new GuestIdentity());
            if ($destroySession && $this->session) {
                $this->session->destroy();
            }

            $this->afterLogout($identity);
        }
        return $this->isGuest();
    }

    /**
     * This method is invoked when calling {@see logout()} to log out a user.
     *
     * @param IdentityInterface $identity the user identity information
     *
     * @return bool whether the user should continue to be logged out
     */
    private function beforeLogout(IdentityInterface $identity): bool
    {
        $event = new BeforeLogout($identity);
        $this->eventDispatcher->dispatch($event);
        return $event->isValid();
    }

    /**
     * This method is invoked right after a user is logged out via {@see logout()}.
     *
     * @param IdentityInterface $identity the user identity information
     */
    private function afterLogout(IdentityInterface $identity): void
    {
        $this->eventDispatcher->dispatch(new AfterLogout($identity));
    }

    /**
     * Updates the authentication status using the information from session.
     *
     * This method will try to determine the user identity using a session variable.
     *
     * If {@see authTimeout} is set, this method will refresh the timer.
     *
     * @throws Throwable
     */
    private function renewAuthStatus(): void
    {
        if ($this->session === null) {
            $this->identity = new GuestIdentity();
            return;
        }

        /** @var mixed $id */
        $id = $this->session->get(self::SESSION_AUTH_ID);

        $identity = null;
        if ($id !== null) {
            $identity = $this->identityRepository->findIdentity((string)$id);
        }
        if ($identity === null) {
            $identity = new GuestIdentity();
        }
        $this->identity = $identity;

        if (
            !($identity instanceof GuestIdentity) &&
            ($this->authTimeout !== null || $this->absoluteAuthTimeout !== null)
        ) {
            /** @var mixed $expire */
            $expire = $this->authTimeout !== null ? $this->session->get(self::SESSION_AUTH_EXPIRE) : null;
            if ($expire !== null) {
                $expire = (int)$expire;
            }

            /** @var mixed $expireAbsolute */
            $expireAbsolute = $this->absoluteAuthTimeout !== null
                ? $this->session->get(self::SESSION_AUTH_ABSOLUTE_EXPIRE)
                : null;
            if ($expireAbsolute !== null) {
                $expireAbsolute = (int)$expire;
            }

            if (($expire !== null && $expire < time()) || ($expireAbsolute !== null && $expireAbsolute < time())) {
                $this->logout(false);
            } elseif ($this->authTimeout !== null) {
                $this->session->set(self::SESSION_AUTH_EXPIRE, time() + $this->authTimeout);
            }
        }
    }
}
