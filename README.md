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

The `CurrentUser` class is responsible for login and logout, as well as receiving data about the current user.

```php
/** 
 * @var \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
 * @var \Yiisoft\Auth\IdentityRepositoryInterface $identityRepository
 */

$currentUser = new \Yiisoft\User\CurrentUser($identityRepository, $eventDispatcher);
```

If the user has not been logged in, then it is the guest user that will be used by default:

```php
$currentUser->getIdentity(); // \Yiisoft\User\Guest\GuestIdentity instance
$currentUser->getId(); // null
$currentUser->isGuest(); // bool
```

With the third optional parameter, you can pass the implementation of the `GuestIdentityFactoryInterface`
to create an identity interface for a guest non-authenticated user:

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

This can be useful to check the user's problems during administration.

#### Login and logout

Methods of the same name are provided for login and logout:

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

In the process of login and logout triggers several events. Events are classes:

- `Yiisoft\User\Event\BeforeLogin` - triggered at the beginning of login process.
  Listeners of this event may call `$event->invalidate()` to cancel the login process.
- `Yiisoft\User\Event\AfterLogin` - triggered at the ending of login process.
- `Yiisoft\User\Event\BeforeLogout` - triggered at the beginning of logout process.
  Listeners of this event may call `$event->invalidate()` to cancel the logout process.
- `Yiisoft\User\Event\AfterLogout` - triggered at the ending of logout process.

Listeners of all these events can get an identity instance participating in the process using `$event->getIdentity()`.
These events are passed to the `Psr\EventDispatcher\EventDispatcherInterface` implementation, which is specified in the
constructor when the `Yiisoft\User\CurrentUser` instance is initialized.

#### Checking user access

To check whether the user can perform the operation as specified by the given permission,
you need to set an access checker (see [yiisoft/access](https://github.com/yiisoft/access)) instance:

```php
/** 
 * @var \Yiisoft\Access\AccessCheckerInterface $accessChecker
 */
 
$currentUser = $currentUser->withAccessChecker($accessChecker);
```

And to check, you need to use the `can()` method:

```php
// The name of the permission (e.g. "edit post") that needs access check.
$permissionName = 'edit-post'; // Required.

// Name-value pairs that would be passed to the rules associated with the roles and permissions assigned to the user.
$params = ['postId' => 42]; // Optional. Default is empty array.

if ($currentUser->can($permissionName, $params)) {
    // Some actions
}
```

Note that you must first provide access checker via `withAccessChecker()` method.
Otherwise, the `can()` method will always return `false`.

#### Session usage

The current user can store in the session the necessary user ID and auth timeouts for auto login.
To do this, you need to set a session (see [yiisoft/session](https://github.com/yiisoft/session)) instance:

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

As well as an absolute timeout (number of seconds), during which the user will be logged
out automatically regardless of activity:

```php
$currentUser = $currentUser->withAbsoluteAuthTimeout(3600);
```

By default, timeouts are not used, the user will be logged out after the current session expires.

#### Using with event loop

The `Yiisoft\User\CurrentUser` instance are stateful, so when you build long-running applications
with tools like [Swoole](https://www.swoole.co.uk/) or [RoadRunner](https://roadrunner.dev/) you should reset
the state at every request. For this purpose, you can use the `clear()` method.

### Auto login through identity from request attribute

When using authentication methods and an authentication middleware that authenticates
the user and places an `Yiisoft\Auth\IdentityInterface` instance in the corresponding
request attribute(see [yiisoft/auth](https://github.com/yiisoft/auth)).
For auto login, use `Yiisoft\User\Login\LoginMiddleware`.

> Please note that is mandatory before `Yiisoft\User\Login\LoginMiddleware`,
> in the middleware stack should be located `Yiisoft\Auth\Middleware\Authentication`.

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

The login process itself when requested, the `CookieLoginMiddleware` will do automatically.

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
    
    // Get user identity in based on body contents.
    
    /** @var \Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface $identity */
    
    if ($currentUser->login($identity) && $body['rememberMe'] ?? false) {
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

#### Preventing the substitution of cookies

The login cookie value is stored raw. To prevent the substitution of the cookie value,
you can use a `Yiisoft\Cookies\CookieMiddleware`. For more information, see
[Yii guide to cookies ](https://github.com/yiisoft/docs/blob/master/guide/en/runtime/cookies.md).

> Please note that is mandatory before `Yiisoft\User\Login\Cookie\CookieLoginMiddleware`,
> in the middleware stack should be located `Yiisoft\Cookies\CookieMiddleware`.

You can see an example of all the above described use of the login functionality using cookies
in the [yiisoft/demo](https://github.com/yiisoft/demo).

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
