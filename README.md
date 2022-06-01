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
[![type-coverage](https://shepherd.dev/github/yiisoft/user/coverage.svg)](https://shepherd.dev/github/yiisoft/user)

The package handles user-related functionality:

- Login and logout.
- Getting currently logged in identity.
- Changing current identity.
- Access checking for current user.
- Auto login based on identity from request attribute.
- Auto login or "remember me" based on request cookie.

## Requirements

- PHP 7.4 or higher.
- `JSON` PHP extension.

## Installation

The package could be installed with composer:

```shell
composer require yiisoft/user --prefer-dist
```

## General usage

This package is an addition to [yiisoft/auth](https://github.com/yiisoft/auth)
and provides additional functionality for interacting with user identity.

### Working with identity

The `CurrentUser` class is responsible for login and logout, as well as for providing data about the current user identity.

```php
/** 
 * @var \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
 * @var \Yiisoft\Auth\IdentityRepositoryInterface $identityRepository
 */

$currentUser = new \Yiisoft\User\CurrentUser($identityRepository, $eventDispatcher);
```

If the user has not been logged in, then the current user is a guest:

```php
$currentUser->getIdentity(); // \Yiisoft\User\Guest\GuestIdentity instance
$currentUser->getId(); // null
$currentUser->isGuest(); // bool
```

If you need to use a custom identity class to represent guest user, you should pass an instance
of `GuestIdentityFactoryInterface` as a third optional parameter when creating `CurrentUser`:

```php
/** 
 * @var \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
 * @var \Yiisoft\Auth\IdentityRepositoryInterface $identityRepository
 * @var \Yiisoft\User\Guest\GuestIdentityFactoryInterface $guestIdentityFactory
 */

$currentUser = new \Yiisoft\User\CurrentUser($identityRepository, $eventDispatcher, $guestIdentityFactory);
```

Also, you can override an identity instance in runtime:

```php
/** 
 * @var \Yiisoft\Auth\IdentityInterface $identity
 */

$currentUser->getIdentity(); // Original identity instance
$currentUser->overrideIdentity($identity);
$currentUser->getIdentity(); // Override identity instance
$currentUser->clearIdentityOverride();
$currentUser->getIdentity(); // Original identity instance
```

It can be useful to allow admin or developer to validate another user's problems.

#### Login and logout

There are two methods for login and logout:

```php
/**
 * @var \Yiisoft\Auth\IdentityInterface $identity
 */

$currentUser->getIdentity(); // GuestIdentityInterface instance

if ($currentUser->login($identity)) {
    $currentUser->getIdentity(); // $identity
    // Some actions
}

if ($currentUser->logout()) {
    $currentUser->getIdentity(); // GuestIdentityInterface instance
    // Some actions
}
```

Both methods trigger events. Events are of the following classes:

- `Yiisoft\User\Event\BeforeLogin` - triggered at the beginning of login process.
  Listeners of this event may call `$event->invalidate()` to cancel the login process.
- `Yiisoft\User\Event\AfterLogin` - triggered at the ending of login process.
- `Yiisoft\User\Event\BeforeLogout` - triggered at the beginning of logout process.
  Listeners of this event may call `$event->invalidate()` to cancel the logout process.
- `Yiisoft\User\Event\AfterLogout` - triggered at the ending of logout process.

Listeners of these events can get an identity instance participating in the process using `$event->getIdentity()`.
Events are dispatched by `Psr\EventDispatcher\EventDispatcherInterface` instance, which is specified in the
constructor when the `Yiisoft\User\CurrentUser` instance is initialized.

#### Checking user access

To be able to check whether the current user can perform an operation corresponding to a given permission,
you need to set an access checker (see [yiisoft/access](https://github.com/yiisoft/access)) instance:

```php
/** 
 * @var \Yiisoft\Access\AccessCheckerInterface $accessChecker
 */
 
$currentUser = $currentUser->withAccessChecker($accessChecker);
```

To perform the check, use `can()` method:

```php
// The name of the permission (e.g. "edit post") that needs access check.
$permissionName = 'edit-post'; // Required.

// Name-value pairs that would be passed to the rules associated with the roles and permissions assigned to the user.
$params = ['postId' => 42]; // Optional. Default is empty array.

if ($currentUser->can($permissionName, $params)) {
    // Some actions
}
```

Note that in case access checker is not provided via `withAccessChecker()` method, `can()` will always return `false`.

#### Session usage

The current user can store user ID and authentication timeouts for auto-login in the session.
To do this, you need to provide a session (see [yiisoft/session](https://github.com/yiisoft/session)) instance:

```php
/** 
 * @var \Yiisoft\Session\SessionInterface $session
 */
 
$currentUser = $currentUser->withSession($session);
```

You can set timeout (number of seconds), during which the user will be logged
out automatically in case of remaining inactive:

```php
$currentUser = $currentUser->withAuthTimeout(3600);
```

Also, an absolute timeout (number of seconds) could be used. The user will be logged
out automatically regardless of activity:

```php
$currentUser = $currentUser->withAbsoluteAuthTimeout(3600);
```

By default, timeouts are not used, so the user will be logged out after the current session expires.

#### Using with event loop

The `Yiisoft\User\CurrentUser` instance is stateful, so when you build long-running applications
with tools like [Swoole](https://www.swoole.co.uk/) or [RoadRunner](https://roadrunner.dev/) you should reset
the state at every request. For this purpose, you can use the `clear()` method.

### Auto login through identity from request attribute

For auto login, you can use the `Yiisoft\User\Login\LoginMiddleware`. This middleware automatically logs user
in if `Yiisoft\Auth\IdentityInterface` instance presents in a request attribute. It is usually put there by
`Yiisoft\Auth\Middleware\Authentication`. For more information about the authentication middleware and
authentication methods, see the [yiisoft/auth](https://github.com/yiisoft/auth).

> Please note that `Yiisoft\Auth\Middleware\Authentication` should be located before
> `Yiisoft\User\Login\LoginMiddleware` in the middleware stack.

### Auto login through cookie

In order to log user in automatically based on request cookie presence,
use `Yiisoft\User\Login\Cookie\CookieLoginMiddleware`.

To use a middleware, you need to implement `Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface`
and also implement and use an instance of `IdentityRepositoryInterface` in the `CurrentUser`,
which will return `CookieLoginIdentityInterface`:

```php
use App\CookieLoginIdentity;
use Yiisoft\Auth\IdentityRepositoryInterface;

final class CookieLoginIdentityRepository implements IdentityRepositoryInterface
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function findIdentity(string $id): ?Identity
    {   
        return new CookieLoginIdentity($this->storage->findOne($id));
    }
}
```

The `CookieLoginMiddleware` will check for the existence of a cookie in the request,
validate it and login the user automatically.

#### Creating a cookie

By default, you should set cookie for auto login manually in your application after logging user in:

```php
public function login(
    \Psr\Http\Message\ServerRequestInterface $request,
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
    \Yiisoft\User\Login\Cookie\CookieLogin $cookieLogin,
    \Yiisoft\User\CurrentUser $currentUser
): \Psr\Http\Message\ResponseInterface {
    $response = $responseFactory->createResponse();
    $body = $request->getParsedBody();
    
    // Get user identity based on body content.
    
    /** @var \Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface $identity */
    
    if ($currentUser->login($identity) && ($body['rememberMe'] ?? false)) {
        $response = $cookieLogin->addCookie($identity, $response);
    }
    
    return $response;
}
```

In the above `rememberMe` in the request body may come from a "remember me" checkbox in the form. End user decides
if he wants to be logged in automatically. If you do not need the user to be able to choose and want to always use
"remember me", you can enable it via the `forceAddCookie` in `params.php`:

```php
return [
    'yiisoft/user' => [
        'authUrl' => '/login',
        'cookieLogin' => [
            'forceAddCookie' => true,
            'duration' => 'P5D', // 5 days
        ],
    ],
];
```

#### Removing a cookie

The `Yiisoft\User\Login\Cookie\CookieLoginMiddleware` automatically removes the cookie after the logout.
But you can also remove the cookie manually:

```php
public function logout(
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
    \Yiisoft\User\Login\Cookie\CookieLogin $cookieLogin,
    \Yiisoft\User\CurrentUser $currentUser
): \Psr\Http\Message\ResponseInterface {
    $response = $responseFactory
        ->createResponse(302)
        ->withHeader('Location', '/');
    
    // Regenerate cookie login key to `Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface` instance.
    
    if ($currentUser->logout()) {
        $response = $cookieLogin->expireCookie($response);
    }
    
    return $response;
}
```

#### Preventing the substitution of cookies

The login cookie value is stored raw. To prevent the substitution of the cookie value,
you can use a `Yiisoft\Cookies\CookieMiddleware`. For more information, see
[Yii guide to cookies](https://github.com/yiisoft/docs/blob/master/guide/en/runtime/cookies.md).

> Please note that `Yiisoft\Cookies\CookieMiddleware` should be located before
> `Yiisoft\User\Login\Cookie\CookieLoginMiddleware` in the middleware stack.

You can find examples of the above features in the [yiisoft/demo](https://github.com/yiisoft/demo).

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## License

The Yii User is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
