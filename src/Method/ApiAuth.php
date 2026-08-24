<?php

declare(strict_types=1);

namespace Yiisoft\User\Method;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\UserAuthenticator;

/**
 * Implementation of the `AuthenticatorWithChallengeInterface` for authenticating users in the API clients.
 */
final class ApiAuth extends UserAuthenticator implements AuthenticatorWithChallengeInterface
{
    public function __construct(CurrentUser $currentUser) {
        parent::__construct($currentUser);
    }

    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }
}
