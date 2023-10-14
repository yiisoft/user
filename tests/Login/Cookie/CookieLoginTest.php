<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Login\Cookie;

use DateInterval;
use HttpSoft\Message\Response;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Tests\Support\CookieLoginIdentity;

final class CookieLoginTest extends TestCase
{
    public function testAddCookie(): void
    {
        $cookieLogin = new CookieLogin(new DateInterval('P1W'));

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddSessionCookie(): void
    {
        $cookieLogin = new CookieLogin();

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C0%5D;'
            . ' Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testRemoveCookie(): void
    {
        $cookieLogin = new CookieLogin(new DateInterval('P1W'));

        $response = new Response();
        $response = $cookieLogin->expireCookie($response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddCookieWithCustomName(): void
    {
        $cookieName = 'testName';
        $cookieLogin = (new CookieLogin(new DateInterval('P1W')))->withCookieName($cookieName);

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#' . $cookieName . '=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testRemoveCookieWithCustomName(): void
    {
        $cookieName = 'testName';
        $cookieLogin = (new CookieLogin(new DateInterval('P1W')))->withCookieName($cookieName);

        $response = new Response();
        $response = $cookieLogin->expireCookie($response);

        $this->assertMatchesRegularExpression(
            '#' . $cookieName . '=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }
}
