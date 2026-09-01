<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Login\Cookie;

use DateInterval;
use HttpSoft\Message\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\Tests\Support\CookieLoginIdentity;

use function explode;
use function hash_hmac;
use function json_encode;
use function rawurldecode;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

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

    public static function dataAddCookieWithCustomDuration(): array
    {
        return [
            'false' => [
                '#testName=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D; Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
                false,
            ],
            'null' => [
                '#testName=%5B%2242%22%2C%22auto-login-key-correct%22%2C0%5D; Path=/; Secure; HttpOnly; SameSite=Lax#',
                null,
            ],
            'p1d' => [
                '#testName=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D; Expires=.*?; Max-Age=86400; Path=/; Secure; HttpOnly; SameSite=Lax#',
                new DateInterval('P1D'),
            ],
        ];
    }

    #[DataProvider('dataAddCookieWithCustomDuration')]
    public function testAddCookieWithCustomDuration(string $expectedRegExp, DateInterval|false|null $duration): void
    {
        $cookieLogin = (new CookieLogin(new DateInterval('P1W')))->withCookieName('testName');

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response, $duration);

        $this->assertMatchesRegularExpression(
            $expectedRegExp,
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddSignedCookie(): void
    {
        $cookieLogin = new CookieLogin(signatureKey: 'secret-key');

        $response = $cookieLogin->addCookie(new CookieLoginIdentity(), new Response());

        $this->assertMatchesRegularExpression(
            '#autoLogin=[0-9a-f]{64}\.%5B%2242%22%2C%22auto-login-key-correct%22%2C0%5D;'
            . ' Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );

        $value = rawurldecode($this->extractCookieValue($response->getHeaderLine('Set-Cookie')));
        $payload = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $this->assertSame(hash_hmac('sha256', $payload, 'secret-key') . '.' . $payload, $value);
    }

    public function testParseValueRoundTrip(): void
    {
        $cookieLogin = new CookieLogin(signatureKey: 'secret-key');

        $value = rawurldecode(
            $this->extractCookieValue(
                $cookieLogin
                    ->addCookie(new CookieLoginIdentity(), new Response())
                    ->getHeaderLine('Set-Cookie'),
            ),
        );

        $this->assertSame(
            ['id' => CookieLoginIdentity::ID, 'key' => CookieLoginIdentity::KEY_CORRECT, 'expires' => 0],
            $cookieLogin->parseValue($value),
        );
    }

    public static function dataParseValueInvalid(): array
    {
        return [
            'not a string prefixed with signature' => ['["42","auto-login-key-correct",0]'],
            'invalid signature' => [str_repeat('0', 64) . '.["42","auto-login-key-correct",0]'],
            'malformed payload' => [hash_hmac('sha256', 'not-json', 'secret-key') . '.not-json'],
            'empty' => [''],
            'no separator' => [str_repeat('0', 64)],
        ];
    }

    #[DataProvider('dataParseValueInvalid')]
    public function testParseValueInvalidSigned(string $value): void
    {
        $cookieLogin = new CookieLogin(signatureKey: 'secret-key');

        $this->assertNull($cookieLogin->parseValue($value));
    }

    public function testParseValueTamperedExpires(): void
    {
        $cookieLogin = new CookieLogin(signatureKey: 'secret-key');
        $payload = '["42","auto-login-key-correct",1000000000]';
        $signature = hash_hmac('sha256', $payload, 'secret-key');

        $tampered = $signature . '.["42","auto-login-key-correct",0]';

        $this->assertNull($cookieLogin->parseValue($tampered));
    }

    public function testParseValueUnsigned(): void
    {
        $cookieLogin = new CookieLogin();

        $this->assertSame(
            ['id' => '42', 'key' => 'auto-login-key-correct', 'expires' => 0],
            $cookieLogin->parseValue('["42","auto-login-key-correct",0]'),
        );
    }

    public static function dataParseValueInvalidUnsigned(): array
    {
        return [
            'empty' => [''],
            'not json' => ['weird stuff'],
            'not an array' => ['"string"'],
            'wrong element count' => ['["42","auto-login-key-correct",0,"extra"]'],
        ];
    }

    #[DataProvider('dataParseValueInvalidUnsigned')]
    public function testParseValueInvalidUnsigned(string $value): void
    {
        $cookieLogin = new CookieLogin();

        $this->assertNull($cookieLogin->parseValue($value));
    }

    private function extractCookieValue(string $setCookieHeader): string
    {
        $pair = explode(';', $setCookieHeader, 2)[0];
        [, $value] = explode('=', $pair, 2);

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }
}
