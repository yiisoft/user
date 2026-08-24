<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\Http\Status;
use Yiisoft\User\Method\WebAuth;

/**
 * Implementation of the authentication interface for the user.
 *
 * @deprecated Use {@see WebAuth}. This class will be removed in the next major version.
 */
final class UserAuth extends UserAuthenticator implements AuthenticatorWithChallengeInterface
{
    private string $authUrl = '/login';

    public function __construct(CurrentUser $currentUser, private ResponseFactoryInterface $responseFactory) {
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
