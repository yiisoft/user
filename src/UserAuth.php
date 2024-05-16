<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\Auth\IdentityInterface;

/**
 * Implementation of the authentication interface for the user.
 */
final class UserAuth implements AuthenticationMethodInterface
{
    private string $authUrl = '/login';

    public function __construct(private CurrentUser $currentUser)
    {
    }

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        if ($this->currentUser->isGuest()) {
            return null;
        }

        return $this->currentUser->getIdentity();
    }

    /**
     * {@inheritDoc}
     *
     * Creates a new instance of the response and adds a `Location` header with a temporary redirect.
     */
    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    /**
     * Returns a new instance with the specified authentication URL.
     *
     * @param string $url The authentication URL.
     */
    public function withAuthUrl(string $url): self
    {
        $new = clone $this;
        $new->authUrl = $url;
        return $new;
    }
}
