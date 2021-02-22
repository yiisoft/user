<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\User\AutoLogin;
use Yiisoft\User\AutoLoginMiddleware;
use Yiisoft\User\CurrentUser\Storage\CurrentIdentityStorageInterface;
use Yiisoft\User\Tests\Mock\FakeCurrentIdentityStorage;
use Yiisoft\User\Tests\Mock\MockEventDispatcher;
use Yiisoft\User\Tests\Mock\MockIdentityRepository;
use Yiisoft\User\Tests\Support\AutoLoginIdentity;
use Yiisoft\User\Tests\Support\LastMessageLogger;
use Yiisoft\User\CurrentUser\CurrentUser;

final class AutoLoginMiddlewareTest extends TestCase
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
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $currentUser,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
        );
        $request = $this->getRequestWithAutoLoginCookie(AutoLoginIdentity::ID, AutoLoginIdentity::KEY_CORRECT);

        $middleware->process($request, $this->getRequestHandler());

        self::assertNull($this->getLastLogMessage());
        self::assertSame(AutoLoginIdentity::ID, $currentUser->getIdentity()->getId());
    }

    public function testInvalidKey(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $user,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
        );
        $request = $this->getRequestWithAutoLoginCookie(AutoLoginIdentity::ID, AutoLoginIdentity::KEY_INCORRECT);

        $middleware->process($request, $this->getRequestHandler());

        self::assertSame('Unable to authenticate user by cookie. Invalid key.', $this->getLastLogMessage());
    }

    public function testNoCookie(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $user,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
        );
        $request = $this->getRequestWithCookies([]);

        $middleware->process($request, $this->getRequestHandler());

        self::assertNull($this->getLastLogMessage());
    }

    public function testEmptyCookie(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $user,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
        );
        $request = $this->getRequestWithCookies(['autoLogin' => '']);

        $middleware->process($request, $this->getRequestHandler());

        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testInvalidCookie(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $user,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
        );
        $request = $this->getRequestWithCookies(
            [
                'autoLogin' => json_encode([AutoLoginIdentity::ID, AutoLoginIdentity::KEY_CORRECT, 'weird stuff']),
            ]
        );

        $middleware->process($request, $this->getRequestHandler());

        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testIncorrectIdentity(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $middleware = new AutoLoginMiddleware(
            $user,
            $this->getIncorrectIdentityRepository(),
            $this->logger,
            $this->getAutoLogin()
        );

        $request = $this->getRequestWithAutoLoginCookie(AutoLoginIdentity::ID, AutoLoginIdentity::KEY_CORRECT);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Identity repository must return an instance of \Yiisoft\User\AutoLoginIdentityInterface in order for auto-login to function.');

        $middleware->process($request, $this->getRequestHandlerThatIsNotCalled());
    }

    public function testIdentityNotFound(): void
    {
        $user = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $middleware = new AutoLoginMiddleware(
            $user,
            $this->getEmptyIdentityRepository(),
            $this->logger,
            $this->getAutoLogin()
        );

        $identityId = AutoLoginIdentity::ID;
        $request = $this->getRequestWithAutoLoginCookie($identityId, AutoLoginIdentity::KEY_CORRECT);

        $middleware->process($request, $this->getRequestHandler());

        $this->assertSame("Unable to authenticate user by cookie. Identity \"$identityId\" not found.", $this->getLastLogMessage());
    }

    public function testAddCookieAfterLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $currentUser,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin,
            true
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $currentUser->login(new AutoLoginIdentity());
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
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $currentUser,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $currentUser->login(new AutoLoginIdentity());
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
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $currentUser,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin,
            true
        );

        $request = $this->createMock(RequestHandlerInterface::class);
        $request
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $identity = new AutoLoginIdentity();
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
            $this->createCurrentIdentityStorage(),
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
        );

        $autoLogin = $this->getAutoLogin();
        $middleware = new AutoLoginMiddleware(
            $currentUser,
            $this->getAutoLoginIdentityRepository(),
            $this->logger,
            $autoLogin
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
            $this->getRequestWithAutoLoginCookie(AutoLoginIdentity::ID, AutoLoginIdentity::KEY_CORRECT),
            $request
        );

        self::assertMatchesRegularExpression(
            '#autoLogin=; Expires=.*?; Max-Age=-31622400; Path=/; Secure; HttpOnly; SameSite=Lax#',
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

    private function getRequestHandlerThatReturnsResponse(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);
        $response = new Response();

        $requestHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturn($response);

        return $requestHandler;
    }

    private function getIncorrectIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->getIdentityRepository($this->createMock(IdentityInterface::class));
    }

    private function getAutoLoginIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->getIdentityRepository(new AutoLoginIdentity());
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

    private function getAutoLogin(): AutoLogin
    {
        return new AutoLogin(new \DateInterval('P1W'));
    }

    private function createCurrentIdentityStorage(?string $id = null): CurrentIdentityStorageInterface
    {
        return new FakeCurrentIdentityStorage($id);
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
