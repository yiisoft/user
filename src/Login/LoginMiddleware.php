<?php

declare(strict_types=1);

namespace Yiisoft\User\Login;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\User\CurrentUser;

/**
 * `LoginMiddleware` automatically logs user in based on identity {@see IdentityInterface}.
 */
final class LoginMiddleware implements MiddlewareInterface
{
    private CurrentUser $currentUser;
    private LoggerInterface $logger;

    /**
     * @param CurrentUser $currentUser The current user instance.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(CurrentUser $currentUser, LoggerInterface $logger)
    {
        $this->currentUser = $currentUser;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     *
     * Before this middleware, there should be an authentication middleware
     * {@see \Yiisoft\Auth\Middleware\Authentication} in the stack, that authenticates the user
     * by placing the identity {@see IdentityInterface} in the corresponding request attribute.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->currentUser->isGuest()) {
            return $handler->handle($request);
        }

        /** @var mixed $identity */
        $identity = $request->getAttribute(Authentication::class);

        if ($identity instanceof IdentityInterface) {
            $this->currentUser->login($identity);
        } else {
            $this->logger->warning('Unable to authenticate user by token. Identity not found.');
        }

        return $handler->handle($request);
    }
}
