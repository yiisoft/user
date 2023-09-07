<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use DateInterval;
use DateTimeImmutable;
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
    private const DEFAULT_COOKIE_PARAMS = [
        'domain' => null,
        'path' => '/',
        'secure' => true,
        'httpOnly' => true,
        'sameSite' => Cookie::SAME_SITE_LAX,
        'encodeValue' => true,
    ];

    private DateInterval $duration;
    private string $cookieName;
    private array $cookieParams;

    /**
     * @param DateInterval $duration Interval until auto-login cookie expires.
     * @param string $cookieName Auto-login cookie name.
     * @param array $cookieParams Parameters for auto-login cookie.
     */
    public function __construct(DateInterval $duration, string $cookieName, array $cookieParams = [])
    {
        $this->duration = $duration;
        $this->cookieName = $cookieName;
        $this->cookieParams = array_merge(self::DEFAULT_COOKIE_PARAMS, $cookieParams);
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
        $expires = (new DateTimeImmutable())->add($this->duration);

        $data = json_encode([
            $identity->getId(),
            $identity->getCookieLoginKey(),
            $expires->getTimestamp(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $cookie = new Cookie(
            name:  $this->cookieName,
            value: $data,
            domain: $this->cookieParams['domain'],
            path: $this->cookieParams['path'],
            secure: $this->cookieParams['secure'],
            httpOnly: $this->cookieParams['httpOnly'],
            sameSite: $this->cookieParams['sameSite'],
            encodeValue: $this->cookieParams['encodeValue'],
        );
        $cookie = $cookie->withExpires($expires);

        return $cookie->addToResponse($response);
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
