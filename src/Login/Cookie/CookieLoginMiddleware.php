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
use Throwable;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentUser;

use function array_key_exists;
use function count;
use function is_array;
use function json_decode;
use function sprintf;

/**
 * `CookieLoginMiddleware` automatically logs user in based on cookie.
 */
final class CookieLoginMiddleware implements MiddlewareInterface
{
    private CurrentUser $currentUser;
    private IdentityRepositoryInterface $identityRepository;
    private LoggerInterface $logger;
    private CookieLogin $cookieLogin;
    private bool $addCookie;

    /**
     * @param CurrentUser $currentUser The current user instance.
     * @param IdentityRepositoryInterface $identityRepository The identity repository instance.
     * @param LoggerInterface $logger The logger instance.
     * @param CookieLogin $cookieLogin The cookie login instance.
     * @param bool $addCookie Whether to add a cookie.
     */
    public function __construct(
        CurrentUser $currentUser,
        IdentityRepositoryInterface $identityRepository,
        LoggerInterface $logger,
        CookieLogin $cookieLogin,
        bool $addCookie = false
    ) {
        $this->currentUser = $currentUser;
        $this->identityRepository = $identityRepository;
        $this->logger = $logger;
        $this->cookieLogin = $cookieLogin;
        $this->addCookie = $addCookie;
    }

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

        if ($this->addCookie && $guestBeforeHandle && !$guestAfterHandle) {
            $identity = $this->currentUser->getIdentity();

            if ($identity instanceof CookieLoginIdentityInterface && $identity->shouldLoginByCookie()) {
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

        try {
            $data = json_decode((string) $cookies[$cookieName], true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        if (!is_array($data) || count($data) !== 2) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        [$id, $key] = $data;
        $id = (string) $id;
        $key = (string) $key;

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
                )
            );
        }

        if (!$identity->validateCookieLoginKey($key)) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid key.');
            return;
        }

        $this->currentUser->login($identity);
    }
}
