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
final class ApiAuth extends UserAuthenticator implements AuthenticatorWithChallengeInterface
{
    public function __construct(private readonly CurrentUser $currentUser) {
        parent::__construct($this->currentUser);
    }

    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }
}
