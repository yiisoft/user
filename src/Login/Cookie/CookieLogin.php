<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use DateInterval;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Cookies\Cookie;

/**
 * The service is used to send or remove auto-login cookie.
 *
 * @see CookieLoginIdentityInterface
 * @see CookieLoginMiddleware
 */
final class CookieLogin
{
    private string $cookieName = 'autoLogin';
    private DateInterval $duration;

    public function __construct(DateInterval $duration)
    {
        $this->duration = $duration;
    }

    public function withCookieName(string $name): self
    {
        $new = clone $this;
        $new->cookieName = $name;
        return $new;
    }

    /**
     * Add auto-login cookie to response so the user is logged in automatically based on cookie even if session
     * is expired.
     */
    public function addCookie(CookieLoginIdentityInterface $identity, ResponseInterface $response): ResponseInterface
    {
        $data = json_encode([
            $identity->getId(),
            $identity->getCookieLoginKey(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return (new Cookie($this->cookieName, $data))
            ->withMaxAge($identity->getCookieLoginDuration() ?? $this->duration)
            ->addToResponse($response);
    }

    /**
     * Expire auto-login cookie so user is not logged in automatically anymore.
     */
    public function expireCookie(ResponseInterface $response): ResponseInterface
    {
        return (new Cookie($this->cookieName))
            ->expire()
            ->addToResponse($response);
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
