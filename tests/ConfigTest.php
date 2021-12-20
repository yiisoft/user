<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests;

use HttpSoft\Message\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Test\Support\Log\SimpleLogger;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityFactory;
use Yiisoft\User\Guest\GuestIdentityFactoryInterface;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Login\Cookie\CookieLoginMiddleware;
use Yiisoft\User\Login\LoginMiddleware;
use Yiisoft\User\Tests\Support\MockIdentityRepository;
use Yiisoft\User\UserAuth;

use function array_merge;
use function dirname;

final class ConfigTest extends TestCase
{
    public function testBase(): void
    {
        $container = $this->createContainer();

        $this->assertInstanceOf(CurrentUser::class, $container->get(CurrentUser::class));
        $this->assertInstanceOf(GuestIdentityFactory::class, $container->get(GuestIdentityFactoryInterface::class));
        $this->assertInstanceOf(LoginMiddleware::class, $container->get(LoginMiddleware::class));

        $userAuth = $container->get(AuthenticationMethodInterface::class);

        $this->assertInstanceOf(UserAuth::class, $userAuth);
        $this->assertSame('/login', $this->getInaccessibleProperty($userAuth, 'authUrl'));

        $cookieLogin = $container->get(CookieLogin::class);

        $this->assertInstanceOf(CookieLogin::class, $cookieLogin);
        $this->assertSame(5, $this->getInaccessibleProperty($cookieLogin, 'duration')->d);

        $cookieLoginMiddleware = $container->get(CookieLoginMiddleware::class);

        $this->assertInstanceOf(CookieLoginMiddleware::class, $cookieLoginMiddleware);
        $this->assertFalse($this->getInaccessibleProperty($cookieLoginMiddleware, 'addCookie'));
    }

    public function testOverrideParams(): void
    {
        $container = $this->createContainer([
            'yiisoft/user' => [
                'authUrl' => '/override',
                'cookieLogin' => [
                    'addCookie' => true,
                    'duration' => 'P2D',
                ],
            ],
        ]);

        $userAuth = $container->get(AuthenticationMethodInterface::class);

        $this->assertInstanceOf(UserAuth::class, $userAuth);
        $this->assertSame('/override', $this->getInaccessibleProperty($userAuth, 'authUrl'));

        $cookieLogin = $container->get(CookieLogin::class);

        $this->assertInstanceOf(CookieLogin::class, $cookieLogin);
        $this->assertSame(2, $this->getInaccessibleProperty($cookieLogin, 'duration')->d);

        $cookieLoginMiddleware = $container->get(CookieLoginMiddleware::class);

        $this->assertInstanceOf(CookieLoginMiddleware::class, $cookieLoginMiddleware);
        $this->assertTrue($this->getInaccessibleProperty($cookieLoginMiddleware, 'addCookie'));
    }

    private function createContainer(?array $params = null): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getWebDefinitions($params)
            ),
        );
    }

    private function getWebDefinitions(?array $params = null): array
    {
        if ($params === null) {
            $params = require dirname(__DIR__) . '/config/params.php';
        }

        return array_merge(require dirname(__DIR__) . '/config/web.php', [
            EventDispatcherInterface::class => SimpleEventDispatcher::class,
            IdentityRepositoryInterface::class => MockIdentityRepository::class,
            ResponseFactoryInterface::class => ResponseFactory::class,
            LoggerInterface::class => SimpleLogger::class,
        ]);
    }

    private function getInaccessibleProperty(object $object, string $propertyName)
    {
        $class = new ReflectionObject($object);
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $result = $property->getValue($object);
        $property->setAccessible(false);
        return $result;
    }
}
