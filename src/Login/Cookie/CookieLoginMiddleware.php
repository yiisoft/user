<?php

declare(strict_types=1);

namespace Yiisoft\User\Login\Cookie;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Yiisoft\Auth\IdentityRepositoryInterface;

use Yiisoft\User\CurrentUser;

use function array_key_exists;
use function is_array;

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

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->authenticateUserByCookieFromRequest($request);
        $guestBeforeHandle = $this->currentUser->isGuest();
        $response = $handler->handle($request);
        $guestAfterHandle = $this->currentUser->isGuest();

        if ($this->addCookie && $guestBeforeHandle && !$guestAfterHandle) {
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
     * Authenticate user by auto login cookie from request.
     *
     * @param ServerRequestInterface $request Request instance containing auto login cookie.
     */
    private function authenticateUserByCookieFromRequest(ServerRequestInterface $request): void
    {
        $cookieName = $this->cookieLogin->getCookieName();
        $cookies = $request->getCookieParams();

        if (!array_key_exists($cookieName, $cookies)) {
            return;
        }

        try {
            $data = json_decode((string)$cookies[$cookieName], true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        if (!is_array($data) || count($data) !== 2) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        [$id, $key] = $data;
        $id = (string)$id;
        $key = (string)$key;

        $identity = $this->identityRepository->findIdentity($id);

        if ($identity === null) {
            $this->logger->warning("Unable to authenticate user by cookie. Identity \"$id\" not found.");
            return;
        }

        if (!$identity instanceof CookieLoginIdentityInterface) {
            throw new RuntimeException('Identity repository must return an instance of \Yiisoft\User\CookieLoginIdentityInterface in order for auto-login to function.');
        }

        if (!$identity->validateCookieLoginKey($key)) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid key.');
            return;
        }

        $this->currentUser->login($identity);
    }
}
