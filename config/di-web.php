<?php

declare(strict_types=1);

use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityFactory;
use Yiisoft\User\Guest\GuestIdentityFactoryInterface;
use Yiisoft\User\Login\Cookie\CookieLoginMiddleware;
use Yiisoft\User\Login\Cookie\CookieLogin;
use Yiisoft\User\UserAuth;

/** @var array $params */

return [
    CurrentUser::class => [
        'reset' => function () {
            $this->clear();
        },
    ],

    UserAuth::class => [
        'class' => UserAuth::class,
        'withAuthUrl()' => [$params['yiisoft/user']['authUrl']],
    ],

    AuthenticationMethodInterface::class => UserAuth::class,
    GuestIdentityFactoryInterface::class => GuestIdentityFactory::class,

    CookieLoginMiddleware::class => [
        '__construct()' => [
            'forceAddCookie' => $params['yiisoft/user']['cookieLogin']['forceAddCookie'],
        ],
    ],

    CookieLogin::class => [
        '__construct()' => [
            'duration' => new DateInterval($params['yiisoft/user']['cookieLogin']['duration']),
            'cookieName' => $params['yiisoft/user']['cookieLogin']['cookieName'],
            'cookieParams' => $params['yiisoft/user']['cookieLogin']['cookieParams'],
        ],
    ],
];
