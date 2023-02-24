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
 * `LoginMiddleware` automatically logs user in if {@see IdentityInterface} instance presents in a request
 * attribute. It is usually put there by {@see Authentication}.
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
     * Before this middleware, there should be {@see Authentication} in the middleware stack.
     * It authenticates the user and places {@see IdentityInterface} instance in the corresponding request attribute.
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
            $this->logger->warning(sprintf(
                'Unable to authenticate user by token "%s". Identity not found.',
                is_scalar($identity) ? $identity : get_debug_type($identity),
            ));
        }

        return $handler->handle($request);
    }
}
