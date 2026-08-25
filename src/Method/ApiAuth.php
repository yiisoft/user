<?php

declare(strict_types=1);

namespace Yiisoft\User\Method;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\User\CurrentUser;

/**
 * Implementation of the `AuthenticatorInterface` for authenticating users in the API clients.
 *
 * @psalm-suppress DeprecatedInterface Implements the deprecated `AuthenticationMethodInterface` (instead of
 * `AuthenticatorInterface` directly) to avoid  a backward compatibility break. Will be switched
 * to `AuthenticatorInterface` in the next major version, see https://github.com/yiisoft/user/issues/130.
 */
final class ApiAuth implements AuthenticationMethodInterface
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        if ($this->currentUser->isGuest()) {
            return null;
        }

        return $this->currentUser->getIdentity();
    }

    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }
}
