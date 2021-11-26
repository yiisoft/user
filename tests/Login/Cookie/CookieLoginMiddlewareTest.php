<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Login\Cookie;

use DateInterval;
use HttpSoft\Message\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Login\Cookie\CookieLoginMiddleware;
use Yiisoft\User\Tests\Support\MockArraySessionStorage;
use Yiisoft\User\Tests\Support\MockIdentityRepository;
use Yiisoft\User\Tests\Support\CookieLoginIdentity;
use Yiisoft\User\Tests\Support\LastMessageLogger;
use Yiisoft\User\CurrentUser;

use function json_encode;
use function time;

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
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $currentUser->getIdentity()->getId());
    }

    public function testCorrectProcessWithNonGuestUser(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $request = $this->getRequestWithAutoLoginCookie();

        $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $currentUser->getIdentity()->getId());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());

        $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $currentUser->getIdentity()->getId());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testInvalidKey(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::KEY_INCORRECT);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid key.', $this->getLastLogMessage());
    }

    public function testInvalidExpires(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::KEY_CORRECT, time() -1);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Lifetime has expired.', $this->getLastLogMessage());
    }

    public function testNoCookie(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $request = $this->getRequestWithCookies([]);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testEmptyCookie(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $request = $this->getRequestWithCookies(['autoLogin' => '']);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testInvalidCookie(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $request = $this->getRequestWithCookies([
            'autoLogin' => json_encode([
                CookieLoginIdentity::ID,
                CookieLoginIdentity::KEY_CORRECT,
                time() + 3600,
                'weird stuff',
            ]),
        ]);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testIncorrectIdentity(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getIncorrectIdentityRepository(),
            $this->logger,
            $this->getCookieLogin(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Identity repository must return an instance of Yiisoft\\User\\Login\\Cookie\\CookieLoginIdentityInterface'
            . ' in order for auto-login to function.',
        );

        $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandlerThatIsNotCalled());
    }

    public function testIdentityNotFound(): void
    {
        $user = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $middleware = new CookieLoginMiddleware(
            $user,
            $this->getEmptyIdentityRepository(),
            $this->logger,
            $this->getCookieLogin(),
        );

        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame(
            'Unable to authenticate user by cookie. Identity "' . CookieLoginIdentity::ID . '" not found.',
            $this->getLastLogMessage(),
        );
    }

    public function testAddCookieAfterLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
            true,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($currentUser) {
                $currentUser->login(new CookieLoginIdentity());
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithCookies([]), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testNotAddCookieAfterLogin(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($currentUser) {
                $currentUser->login(new CookieLoginIdentity());
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithCookies([]), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testNotAddCookieAfterLoginUsingRememberMe(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($currentUser) {
                $identity = new CookieLoginIdentity();
                $identity->rememberMe = true;
                $currentUser->login($identity);
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithCookies([]), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testAddCookieAfterLoginUsingRememberMe(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
            true,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($currentUser) {
                $identity = new CookieLoginIdentity();
                $identity->rememberMe = true;
                $currentUser->login($identity);
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithCookies([]), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testRemoveCookieAfterLogout(): void
    {
        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $this->createEventDispatcher(),
            $this->createSession(),
        );

        $cookieLogin = $this->getCookieLogin();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () use ($currentUser) {
                $currentUser->logout();
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertMatchesRegularExpression(
            '#autoLogin=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    private function getRequestHandler(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->once())
            ->method('handle')
        ;

        return $requestHandler;
    }

    private function getRequestHandlerThatIsNotCalled(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->never())
            ->method('handle')
        ;

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
            ->willReturn($identity)
        ;

        return $identityRepository;
    }

    private function getRequestWithAutoLoginCookie(
        string $authKey = CookieLoginIdentity::KEY_CORRECT,
        int $expires = null
    ): ServerRequestInterface {
        return $this->getRequestWithCookies([
            'autoLogin' => json_encode([CookieLoginIdentity::ID, $authKey, $expires ?? time() + 3600]),
        ]);
    }

    private function getRequestWithCookies(array $cookies): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request
            ->expects($this->any())
            ->method('getCookieParams')
            ->willReturn($cookies)
        ;

        return $request;
    }

    private function getCookieLogin(): CookieLogin
    {
        return new CookieLogin(new DateInterval('P1W'));
    }

    private function createSession(array $data = []): MockArraySessionStorage
    {
        return new MockArraySessionStorage($data);
    }

    private function createIdentityRepository(): IdentityRepositoryInterface
    {
        return new MockIdentityRepository();
    }

    private function createEventDispatcher(): SimpleEventDispatcher
    {
        return new SimpleEventDispatcher();
    }
}
