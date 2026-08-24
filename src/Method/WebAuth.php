<?php

declare(strict_types=1);

namespace Yiisoft\User\Method;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\Http\Status;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\UserAuthenticator;

/**
 * Implementation of the `AuthenticatorWithChallengeInterface` for authenticating users in the web applications.
 */
final class WebAuth extends UserAuthenticator implements AuthenticatorWithChallengeInterface
{
    private string $authUrl = '/login';

    public function __construct(
        CurrentUser $currentUser,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
        parent::__construct($currentUser);
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
