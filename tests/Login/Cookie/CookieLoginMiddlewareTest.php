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

    public function testCorrectLogin(): void
    {
        $currentUser = $this->createCurrentUser();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $currentUser
            ->getIdentity()
            ->getId());
    }

    public function testCorrectProcessWithNonGuestUser(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $currentUser = new CurrentUser(
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithAutoLoginCookie();

        $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $currentUser
            ->getIdentity()
            ->getId());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());

        $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $currentUser
            ->getIdentity()
            ->getId());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testInvalidKey(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::KEY_INCORRECT);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid key.', $this->getLastLogMessage());
    }

    public function testInvalidExpires(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::KEY_CORRECT, time() -1);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Lifetime has expired.', $this->getLastLogMessage());
    }

    public function testNoCookie(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithCookies([]);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testEmptyCookie(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithCookies(['autoLogin' => '']);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testInvalidCookie(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
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
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getIncorrectIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
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
        $middleware = new CookieLoginMiddleware(
            $this->createCurrentUser(),
            $this->getEmptyIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame(
            'Unable to authenticate user by cookie. Identity "' . CookieLoginIdentity::ID . '" not found.',
            $this->getLastLogMessage(),
        );
    }

    public function forceAddCookieDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * @dataProvider forceAddCookieDataProvider
     */
    public function testForceAddCookieAfterLoginAndNotManualLogin(bool $forceAddCookie): void
    {
        $currentUser = $this->createCurrentUser();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
            $forceAddCookie,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());
        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertFalse($currentUser->isGuest());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    /**
     * @dataProvider forceAddCookieDataProvider
     */
    public function testForceAddCookieAfterLoginAndManualLoginAndManualAddCookie(bool $forceAddCookie): void
    {
        $cookieLogin = $this->createCookieLogin();
        $currentUser = $this->createCurrentUser();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
            $forceAddCookie,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($cookieLogin, $currentUser) {
                $identity = new CookieLoginIdentity();
                $currentUser->login($identity);
                return $cookieLogin->addCookie($identity, new Response());
            });

        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertFalse($currentUser->isGuest());
        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    /**
     * @dataProvider forceAddCookieDataProvider
     */
    public function testForceAddCookieAfterLoginAndManualLoginAndNotManualAddCookie(bool $forceAddCookie): void
    {
        $currentUser = $this->createCurrentUser();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
            $forceAddCookie,
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
        $this->assertFalse($currentUser->isGuest());

        if ($forceAddCookie) {
            $this->assertMatchesRegularExpression(
                '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
                . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
                $response->getHeaderLine('Set-Cookie'),
            );
        } else {
            $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        }
    }

    public function testRemoveCookieAfterLogout(): void
    {
        $currentUser = $this->createCurrentUser();

        $middleware = new CookieLoginMiddleware(
            $currentUser,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
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

    private function createCookieLogin(): CookieLogin
    {
        return new CookieLogin(new DateInterval('P1W'));
    }

    private function createCurrentUser(): CurrentUser
    {
        return (new CurrentUser($this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withSession($this->createSession());
    }

    private function createSession(): MockArraySessionStorage
    {
        return new MockArraySessionStorage();
    }

    private function createIdentityRepository(): IdentityRepositoryInterface
    {
        return new MockIdentityRepository();
    }

    private function createEventDispatcher(): SimpleEventDispatcher
    {
        return new SimpleEventDispatcher();
    }

    private function getLastLogMessage(): ?string
    {
        return $this->logger->getLastMessage();
    }
}
