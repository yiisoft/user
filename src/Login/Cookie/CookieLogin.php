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
    private string $cookieName = 'autoLogin';

    /**
     * @param DateInterval|null $duration Interval until the auto-login cookie expires. If it isn't set it means
     * the auto-login cookie is session cookie that expires when browser is closed.
     */
    public function __construct(private ?DateInterval $duration = null)
    {
    }

    /**
     * Returns a new instance with the specified auto-login cookie name.
     *
     * @param string $name The auto-login cookie name.
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
     * @param DateInterval|null|false $duration Interval until the auto-login cookie expires. If it is null it means
     * the auto-login cookie is session cookie that expires when browser is closed. If it is false (by default) will be
     * used default value of duration.
     *
     * @throws JsonException If an error occurs during JSON encoding of the cookie value.
     *
     * @return ResponseInterface Response with added auto-login cookie.
     */
    public function addCookie(
        CookieLoginIdentityInterface $identity,
        ResponseInterface $response,
        DateInterval|null|false $duration = false,
    ): ResponseInterface
    {
        $duration = $duration === false ? $this->duration : $duration;

        $data = [$identity->getId(), $identity->getCookieLoginKey()];

        if ($duration !== null) {
            $expires = (new DateTimeImmutable())->add($duration);
            $data[] = $expires->getTimestamp();
        } else {
            $expires = null;
            $data[] = 0;
        }

        $cookieValue = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return (new Cookie(name: $this->cookieName, value: $cookieValue, expires: $expires))->addToResponse($response);
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
