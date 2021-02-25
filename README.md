<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii User</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/user/v/stable.png)](https://packagist.org/packages/yiisoft/user)
[![Total Downloads](https://poser.pugx.org/yiisoft/user/downloads.png)](https://packagist.org/packages/yiisoft/user)
[![Build status](https://github.com/yiisoft/user/workflows/build/badge.svg)](https://github.com/yiisoft/user/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/user/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/user/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/user/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/user/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fuser%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/user/master)
[![static analysis](https://github.com/yiisoft/user/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/user/actions?query=workflow%3A%22static+analysis%22)

The package handles user-related functionality:

- Login and logout.
- Getting currently logged in identity.
- Changing current identity.
- Access checking for current user.
- Auto login or "remember me" based on request cookie.

## Requirements

- PHP 7.4 or higher.

## Installation

The package could be installed with composer:

```
composer require yiisoft/user --prefer-dist
```

## General usage

## Working with identity

...

## Auto login

In order to log user in automatically based on request cookie presence, use `AutoLoginMiddleware`.

By default, you should set cookie for auto login manually in your application after logging user in:

```php
public function login(
    \Psr\Http\Message\ServerRequestInterface $request,
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
    \Yiisoft\User\CookieLogin\CookieLogin $cookieLogin
): \Psr\Http\Message\ResponseInterface {
    $body = $request->getParsedBody();
    
    // Get user identity in based on body contents,
    // log user in.
    
    $response = $responseFactory->createResponse();
    if ($body['rememberMe'] ?? false) {
        $response = $cookieLogin->addCookie($identity, $response);
    }
    return $response;
}
```

In the above `rememberMe` in the body may come from a "remember me" checkbox in the form. End user decides if he wants
to be logged in automatically. If you do not need the user to be able to choose and want to always use "remember me",
you can enable it via `params.php`:

```php
return [
    'yiisoft/user' => [
        'cookieLogin' => [
            'addCookie' => true,
        ],
    ],
];
```

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```php
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```php
./vendor/bin/infection
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```php
./vendor/bin/psalm
```

## License

The Yii User is free software. It is released under the terms of the BSD License. Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).
