<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\CurrentIdentity\CurrentIdentityService;
use function array_key_exists;
use function is_array;

/**
 * AutoLoginMiddleware automatically logs user in based on cookie.
 */
final class AutoLoginMiddleware implements MiddlewareInterface
{
    private CurrentIdentityService $currentIdentityService;
    private IdentityRepositoryInterface $identityRepository;
    private LoggerInterface $logger;
    private AutoLogin $autoLogin;
    private bool $addCookie;

    public function __construct(
        CurrentIdentityService $currentIdentityService,
        IdentityRepositoryInterface $identityRepository,
        LoggerInterface $logger,
        AutoLogin $autoLogin,
        bool $addCookie = false
    ) {
        $this->currentIdentityService = $currentIdentityService;
        $this->identityRepository = $identityRepository;
        $this->logger = $logger;
        $this->autoLogin = $autoLogin;
        $this->addCookie = $addCookie;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->authenticateUserByCookieFromRequest($request);
        $guestBeforeHandle = $this->currentIdentityService->isGuest();
        $response = $handler->handle($request);
        $guestAfterHandle = $this->currentIdentityService->isGuest();

        if ($this->addCookie && $guestBeforeHandle && !$guestAfterHandle) {
            $identity = $this->currentIdentityService->get();
            if ($identity instanceof AutoLoginIdentityInterface) {
                $response = $this->autoLogin->addCookie($identity, $response);
            }
        }

        if (!$guestBeforeHandle && $guestAfterHandle) {
            $response = $this->autoLogin->expireCookie($response);
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
        $cookieName = $this->autoLogin->getCookieName();
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

        if (!$identity instanceof AutoLoginIdentityInterface) {
            throw new RuntimeException('Identity repository must return an instance of \Yiisoft\User\AutoLoginIdentityInterface in order for auto-login to function.');
        }

        if (!$identity->validateAutoLoginKey($key)) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid key.');
            return;
        }

        $this->currentIdentityService->login($identity);
    }
}
