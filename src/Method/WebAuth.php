<?php

declare(strict_types=1);

namespace Yiisoft\User\Method;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Http\Status;
use Yiisoft\User\CurrentUser;

/**
 * Implementation of the `AuthenticationMethodInterface` for authenticating users in the web applications.
 */
final class WebAuth implements AuthenticationMethodInterface
{
    private string $authUrl = '/login';

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ResponseFactoryInterface $responseFactory
    ){
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
        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader('Location', $this->authUrl);
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
