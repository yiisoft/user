<?php

declare(strict_types=1);

namespace Yiisoft\User;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Http\Status;
use Yiisoft\User\CurrentIdentity\CurrentIdentityService;

final class UserAuth implements AuthenticationMethodInterface
{
    private string $authUrl = '/login';
    private CurrentIdentityService $currentIdentityService;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(CurrentIdentityService $currentIdentityService, ResponseFactoryInterface $responseFactory)
    {
        $this->currentIdentityService = $currentIdentityService;
        $this->responseFactory = $responseFactory;
    }

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        if ($this->currentIdentityService->isGuest()) {
            return null;
        }

        return $this->currentIdentityService->get();
    }

    public function challenge(ResponseInterface $response): ResponseInterface
    {
        return $this->responseFactory->createResponse(Status::FOUND)->withHeader('Location', $this->authUrl);
    }

    public function withAuthUrl(string $url): self
    {
        $new = clone $this;
        $new->authUrl = $url;
        return $new;
    }
}
