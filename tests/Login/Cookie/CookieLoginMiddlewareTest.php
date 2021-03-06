<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Login\Cookie;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Login\Cookie\CookieLoginMiddleware;
use Yiisoft\User\Tests\Mock\MockArraySessionStorage;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;
use Yiisoft\User\Tests\Support\CookieLoginIdentity;
use Yiisoft\User\Tests\Support\LastMessageLogger;
use Yiisoft\User\CurrentUser;

final class CookieLoginMiddlewareTest extends TestCase
{
    private LastMessageLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new LastMessageLogger();
    }

    private function getLastLogMessage(): ?string
    {
        return $this->logger->getLastMessage();
    }

    public function testCorrectLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );
        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT);

        $middleware->process($request, $this->getRequestHandler());

        self::assertNull($this->getLastLogMessage());
        self::assertSame(CookieLoginIdentity::ID, $currentUser->getIdentity()->getId());
    }

    public function testInvalidKey(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );
        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::ID, CookieLoginIdentity::KEY_INCORRECT);

        $middleware->process($request, $this->getRequestHandler());

        self::assertSame('Unable to authenticate user by cookie. Invalid key.', $this->getLastLogMessage());
    }

    public function testNoCookie(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );
        $request = $this->getRequestWithCookies([]);

        $middleware->process($request, $this->getRequestHandler());

        self::assertNull($this->getLastLogMessage());
    }

    public function testEmptyCookie(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );
        $request = $this->getRequestWithCookies(['autoLogin' => '']);

        $middleware->process($request, $this->getRequestHandler());

        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testInvalidCookie(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );
        $request = $this->getRequestWithCookies(
            [
                'autoLogin' => json_encode([CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 'weird stuff']),
            ]
        );

        $middleware->process($request, $this->getRequestHandler());

        self::assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testIncorrectIdentity(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getIncorrectIdentityRepository(),
            $this->logger,
            $this->getCookieLogin()
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Identity repository must return an instance of Yiisoft\\User\\Login\\Cookie\\CookieLoginIdentityInterface '
            . 'in order for auto-login to function.'
        );

        $middleware->process($request, $this->getRequestHandlerThatIsNotCalled());
    }

    public function testIdentityNotFound(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getEmptyIdentityRepository(),
            $this->logger,
            $this->getCookieLogin()
        );

        $identityId = CookieLoginIdentity::ID;
        $request = $this->getRequestWithAutoLoginCookie($identityId, CookieLoginIdentity::KEY_CORRECT);

        $middleware->process($request, $this->getRequestHandler());

        self::assertSame("Unable to authenticate user by cookie. Identity \"$identityId\" not found.", $this->getLastLogMessage());
    }

    public function testAddCookieAfterLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
            true
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $currentUser->login(new CookieLoginIdentity());
                return new Response();
            });
        $response = $middleware->process(
            $this->getRequestWithCookies([]),
            $request
        );

        self::assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%5D; Expires=.*?; ' .
            'Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie')
        );
    }

    public function testNotAddCookieAfterLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $currentUser->login(new CookieLoginIdentity());
                return new Response();
            });
        $response = $middleware->process(
            $this->getRequestWithCookies([]),
            $request
        );

        self::assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testAddCookieAfterLoginUsingRememberMe(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
            true
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $identity = new CookieLoginIdentity();
                $identity->rememberMe = true;
                $currentUser->login($identity);
                return new Response();
            });
        $response = $middleware->process(
            $this->getRequestWithCookies([]),
            $request
        );

        self::assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%5D; Expires=.*?; ' .
            'Max-Age=1209600; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie')
        );
    }

    public function testRemoveCookieAfterLogout(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession()
        );

        $cookieLogin = $this->getCookieLogin();
        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $currentUser->logout();
                return new Response();
            });
        $response = $middleware->process(
            $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT),
            $request
        );

        self::assertMatchesRegularExpression(
            '#autoLogin=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie')
        );
    }

    private function getRequestHandler(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->once())
            ->method('handle');

        return $requestHandler;
    }

    private function getRequestHandlerThatIsNotCalled(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->never())
            ->method('handle');

        return $requestHandler;
    }

    private function getIncorrectIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->getIdentityRepository($this->createMock(IdentityInterface::class));
    }

    private function getCookieLoginIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->getIdentityRepository(new CookieLoginIdentity());
    }

    private function getEmptyIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->createMock(IdentityRepositoryInterface::class);
    }

    private function getIdentityRepository(IdentityInterface $identity): IdentityRepositoryInterface
    {
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $identityRepository
            ->expects($this->any())
            ->method('findIdentity')
            ->willReturn($identity);

        return $identityRepository;
    }

    private function getRequestWithAutoLoginCookie(string $userId, string $authKey): ServerRequestInterface
    {
        return $this->getRequestWithCookies(['autoLogin' => json_encode([$userId, $authKey])]);
    }

    private function getRequestWithCookies(array $cookies): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request
            ->expects($this->any())
            ->method('getCookieParams')
            ->willReturn($cookies);

        return $request;
    }

    private function getCookieLogin(): CookieLogin
    {
        return new CookieLogin(new \DateInterval('P1W'));
    }

    private function createSession(array $data = []): MockArraySessionStorage
    {
        return new MockArraySessionStorage($data);
    }

    private function createIdentityRepository(?IdentityInterface $identity = null): IdentityRepositoryInterface
    {
        return new MockIdentityRepository($identity);
    }

    private function createEventDispatcher(): MockEventDispatcher
    {
        return new MockEventDispatcher();
    }
}
