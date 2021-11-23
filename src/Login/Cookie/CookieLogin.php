<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use DateInterval;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Cookies\Cookie;

use function json_encode;

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

    /**
     * @param DateInterval $duration Interval until the auto-login cookie expires.
     */
    public function __construct(DateInterval $duration)
    {
        $this->duration = $duration;
    }

    /**
     * Returns a new instance with the specified auto-login cookie name.
     *
     * @param string $name The auto-login cookie name.
     *
     * @return self
     */
    public function withCookieName(string $name): self
    {
        $new = clone $this;
        $new->cookieName = $name;
        return $new;
    }

    /**
     * Adds auto-login cookie to response so the user is logged in automatically based on cookie even if session
     * is expired.
     *
     * @param CookieLoginIdentityInterface $identity The cookie login identity instance.
     * @param ResponseInterface $response Response for adding auto-login cookie.
     *
     * @throws JsonException If an error occurs during JSON encoding of the cookie value.
     *
     * @return ResponseInterface Response with added auto-login cookie.
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
     * Expires auto-login cookie so user is not logged in automatically anymore.
     *
     * @param ResponseInterface $response Response for adding auto-login cookie.
     *
     * @return ResponseInterface Response with added auto-login cookie.
     */
    public function expireCookie(ResponseInterface $response): ResponseInterface
    {
        return (new Cookie($this->cookieName))
            ->expire()
            ->addToResponse($response);
    }

    /**
     * Returns the auto-login cookie name.
     *
     * @return string The auto-login cookie name.
     */
    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
