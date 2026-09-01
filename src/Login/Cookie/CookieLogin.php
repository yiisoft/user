<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use DateInterval;
use DateTimeImmutable;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\Cookies\Cookie;

use function count;
use function hash_equals;
use function hash_hmac;
use function is_array;
use function json_decode;
use function json_encode;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The service is used to send or remove auto-login cookie.
 *
 * The auto-login cookie value must be protected against tampering: either set a signature key here, or sign/encrypt
 * the cookie separately (for example with `Yiisoft\Cookies\CookieMiddleware`). When a signature key is set, the
 * value is signed with HMAC-SHA256, so anyone able to edit the cookie can no longer change the identity or the
 * expiration timestamp without invalidating the signature.
 *
 * @see CookieLoginIdentityInterface
 * @see CookieLoginMiddleware
 */
final class CookieLogin
{
    /**
     * Length of a hexadecimal HMAC-SHA256 signature that prefixes a signed cookie value.
     */
    private const SIGNATURE_LENGTH = 64;

    private string $cookieName = 'autoLogin';

    /**
     * @param DateInterval|null $duration Interval until the auto-login cookie expires. If it isn't set it means
     * the auto-login cookie is session cookie that expires when browser is closed.
     * @param string|null $signatureKey Secret key used to sign the auto-login cookie value with HMAC-SHA256. If it
     * isn't set, the cookie value is stored without a signature and isn't protected against tampering.
     */
    public function __construct(
        private readonly ?DateInterval $duration = null,
        private readonly ?string $signatureKey = null,
    ) {}

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
     * @param DateInterval|false|null $duration Interval until the auto-login cookie expires. If it is null it means
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
        DateInterval|false|null $duration = false,
    ): ResponseInterface {
        $duration = $duration === false ? $this->duration : $duration;

        $expires = $duration === null ? null : (new DateTimeImmutable())->add($duration);

        $cookieValue = $this->createValue((string) $identity->getId(), $identity->getCookieLoginKey(), $expires);

        return (new Cookie(name: $this->cookieName, value: $cookieValue, expires: $expires))
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

    /**
     * Parses the auto-login cookie value produced by {@see createValue()} back into the identity data.
     *
     * When a signature key is set, a value without a valid signature is rejected.
     *
     * @param string $value The auto-login cookie value.
     *
     * @return array|null The identity data, or `null` if the value is malformed or has an invalid signature.
     *
     * @psalm-return array{id: string, key: string, expires: int}|null
     */
    public function parseValue(string $value): ?array
    {
        $payload = $this->signatureKey === null ? $value : $this->getVerifiedPayload($value, $this->signatureKey);
        if ($payload === null) {
            return null;
        }

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($data) || count($data) !== 3) {
            return null;
        }

        [$id, $key, $expires] = $data;

        return [
            'id' => (string) $id,
            'key' => (string) $key,
            'expires' => (int) $expires,
        ];
    }

    /**
     * Builds the auto-login cookie value from the identity data, optionally prefixing it with an HMAC signature.
     *
     * @param string $id The identity ID.
     * @param string $key The cookie login key.
     * @param DateTimeImmutable|null $expiresDate Expiration date, or `null` for a session cookie.
     *
     * @throws JsonException If an error occurs during JSON encoding of the cookie value.
     *
     * @return string The auto-login cookie value.
     */
    private function createValue(string $id, string $key, ?DateTimeImmutable $expiresDate): string
    {
        $payload = json_encode(
            [$id, $key, $expiresDate?->getTimestamp() ?? 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $this->signatureKey === null ? $payload : $this->sign($payload, $this->signatureKey);
    }

    /**
     * Prefixes the payload with its HMAC-SHA256 signature.
     *
     * @param string $payload The cookie payload to sign.
     * @param string $signatureKey The secret key used to sign the payload.
     *
     * @return string The signed cookie value.
     */
    private function sign(string $payload, string $signatureKey): string
    {
        return hash_hmac('sha256', $payload, $signatureKey) . '.' . $payload;
    }

    /**
     * Verifies the signature of a cookie value and returns its payload.
     *
     * @param string $value The cookie value to verify.
     * @param string $signatureKey The secret key the value is expected to be signed with.
     *
     * @return string|null The cookie payload, or `null` if the signature is missing or invalid.
     */
    private function getVerifiedPayload(string $value, string $signatureKey): ?string
    {
        if (strlen($value) <= self::SIGNATURE_LENGTH || $value[self::SIGNATURE_LENGTH] !== '.') {
            return null;
        }

        $signature = substr($value, 0, self::SIGNATURE_LENGTH);
        $payload = substr($value, self::SIGNATURE_LENGTH + 1);

        return hash_equals(hash_hmac('sha256', $payload, $signatureKey), $signature) ? $payload : null;
    }
}
