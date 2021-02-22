<?php

declare(strict_types=1);

namespace Yiisoft\User\CurrentUser\Storage;

use Yiisoft\Session\SessionInterface;

/**
 * Stores current identity ID in a session.
 */
final class SessionCurrentIdentityIdStorage implements CurrentIdentityIdStorageInterface
{
    private const SESSION_AUTH_ID = '__auth_id';
    private const SESSION_AUTH_EXPIRE = '__auth_expire';
    private const SESSION_AUTH_ABSOLUTE_EXPIRE = '__auth_absolute_expire';

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

    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
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

    public function get(): ?string
    {
        /** @var mixed $id */
        $id = $this->session->get(self::SESSION_AUTH_ID);

        if (
            $id !== null &&
            ($this->authTimeout !== null || $this->absoluteAuthTimeout !== null)
        ) {
            $expire = $this->getExpire();
            $expireAbsolute = $this->getExpireAbsoulte();

            if (
                ($expire !== null && $expire < time()) ||
                ($expireAbsolute !== null && $expireAbsolute < time())
            ) {
                $this->clear();
                return null;
            }

            if ($this->authTimeout !== null) {
                $this->session->set(self::SESSION_AUTH_EXPIRE, time() + $this->authTimeout);
            }
        }

        return $id === null ? null : (string)$id;
    }

    private function getExpire(): ?int
    {
        /** @var mixed $expire */
        $expire = $this->authTimeout !== null
            ? $this->session->get(self::SESSION_AUTH_EXPIRE)
            : null;
        return $expire !== null ? (int)$expire : null;
    }

    private function getExpireAbsoulte(): ?int
    {
        /** @var mixed $expire */
        $expire = $this->absoluteAuthTimeout !== null
            ? $this->session->get(self::SESSION_AUTH_ABSOLUTE_EXPIRE)
            : null;
        return $expire !== null ? (int)$expire : null;
    }

    public function set(string $id): void
    {
        $this->switchId($id);
    }

    public function clear(): void
    {
        $this->switchId(null);
    }

    private function switchId(?string $id): void
    {
        $this->session->regenerateID();

        $this->session->remove(self::SESSION_AUTH_ID);
        $this->session->remove(self::SESSION_AUTH_EXPIRE);

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
