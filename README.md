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

The package ...

## Requirements

- PHP 7.4 or higher.

## Installation

The package could be installed with composer:

```
composer require yiisoft/user --prefer-dist
```

## General usage

## Auto login

Use middleware `AutoLoginMiddleware`.

Default you should set cookie for auto login manually in your application after login user:

```php
public function login(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
        \Yiisoft\User\AutoLogin $autoLogin
    ): \Psr\Http\Message\ResponseInterface {
    $body = $request->getParsedBody();
    // ...
    $response = $responseFactory->createResponse();
     if ($body['rememberMe'] ?? false) {
        $response = $autoLogin->addCookie($identity, $response);
    }
    // ...
}
```

Also you can enable automatically add cookie via `params.php`:

```php
return [
    'yiisoft/user' => [
        'autoLogin' => [
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
