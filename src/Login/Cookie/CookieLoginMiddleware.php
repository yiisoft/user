<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Cookies\CookieMiddleware;
use Yiisoft\User\CurrentUser;

use function array_key_exists;
use function sprintf;
use function time;

/**
 * `CookieLoginMiddleware` automatically logs user in based on cookie.
 *
 * The auto-login cookie value must be protected against tampering: either configure a signature key for
 * {@see CookieLogin}, or sign/encrypt the cookie separately (for example with {@see CookieMiddleware}).
 * Otherwise anyone able to edit the cookie (the end user, or an attacker who obtained it) can change the identity
 * or the expiration timestamp.
 */
final class CookieLoginMiddleware implements MiddlewareInterface
{
    /**
     * @param CurrentUser $currentUser The current user instance.
     * @param IdentityRepositoryInterface $identityRepository The identity repository instance.
     * @param LoggerInterface $logger The logger instance.
     * @param CookieLogin $cookieLogin The cookie login instance.
     * @param bool $forceAddCookie Whether to force add a cookie.
     */
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly IdentityRepositoryInterface $identityRepository,
        private readonly LoggerInterface $logger,
        private readonly CookieLogin $cookieLogin,
        private readonly bool $forceAddCookie = false,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws JsonException If an error occurs when JSON encoding the cookie value while adding the cookie file.
     * @throws RuntimeException If during authentication, the identity repository {@see IdentityRepositoryInterface}
     * does not return an instance of {@see CookieLoginIdentityInterface}.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->currentUser->isGuest()) {
            $this->authenticateUserByCookieFromRequest($request);
        }

        $guestBeforeHandle = $this->currentUser->isGuest();
        $response = $handler->handle($request);
        $guestAfterHandle = $this->currentUser->isGuest();

        if ($this->forceAddCookie && $guestBeforeHandle && !$guestAfterHandle) {
            $identity = $this->currentUser->getIdentity();

            if ($identity instanceof CookieLoginIdentityInterface) {
                $response = $this->cookieLogin->addCookie($identity, $response);
            }
        }

        if (!$guestBeforeHandle && $guestAfterHandle) {
            $response = $this->cookieLogin->expireCookie($response);
        }

        return $response;
    }

    /**
     * Authenticate user by auto-login cookie from request.
     *
     * @param ServerRequestInterface $request Request instance containing auto-login cookie.
     *
     * @throws RuntimeException If the identity repository {@see IdentityRepositoryInterface}
     * does not return an instance of {@see CookieLoginIdentityInterface}.
     */
    private function authenticateUserByCookieFromRequest(ServerRequestInterface $request): void
    {
        $cookieName = $this->cookieLogin->getCookieName();
        $cookies = $request->getCookieParams();

        if (!array_key_exists($cookieName, $cookies)) {
            return;
        }

        $data = $this->cookieLogin->parseValue((string) $cookies[$cookieName]);

        if ($data === null) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        ['id' => $id, 'key' => $key, 'expires' => $expires] = $data;

        $identity = $this->identityRepository->findIdentity($id);

        if ($identity === null) {
            $this->logger->warning("Unable to authenticate user by cookie. Identity \"$id\" not found.");
            return;
        }

        if (!$identity instanceof CookieLoginIdentityInterface) {
            throw new RuntimeException(
                sprintf(
                    'Identity repository must return an instance of %s in order for auto-login to function.',
                    CookieLoginIdentityInterface::class,
                ),
            );
        }

        if (!$identity->validateCookieLoginKey($key)) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid key.');
            return;
        }

        if ($expires !== 0 && $expires < time()) {
            $this->logger->warning('Unable to authenticate user by cookie. Lifetime has expired.');
            return;
        }

        $this->currentUser->login($identity);
    }
}
