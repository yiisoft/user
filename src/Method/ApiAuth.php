<?php

declare(strict_types=1);

namespace Yiisoft\User\Method;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\UserAuthenticator;

/**
 * Implementation of the `AuthenticatorWithChallengeInterface` for authenticating users in the API clients.
 */
final class ApiAuth implements AuthenticatorWithChallengeInterface
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        return (new UserAuthenticator($this->currentUser))->authenticate($request);
    }

    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }
}
