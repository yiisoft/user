<?php

declare(strict_types=1);

use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\AutoLoginMiddleware;
use Yiisoft\User\AutoLogin;
use Yiisoft\User\CurrentUser\Storage\CurrentIdentityIdStorageInterface;
use Yiisoft\User\CurrentUser\Storage\SessionCurrentIdentityIdStorage;
use Yiisoft\User\UserAuth;

/** @var array $params */

return [
    CurrentIdentityIdStorageInterface::class => SessionCurrentIdentityIdStorage::class,

    UserAuth::class => [
        '__class' => UserAuth::class,
        'withAuthUrl()' => [$params['yiisoft/user']['authUrl']],
    ],

    AuthenticationMethodInterface::class => UserAuth::class,

    AutoLoginMiddleware::class => [
        '__construct()' => [
            'addCookie' => $params['yiisoft/user']['autoLogin']['addCookie'],
        ],
    ],

    AutoLogin::class => [
        '__construct()' => [
            'duration' => new \DateInterval($params['yiisoft/user']['autoLogin']['duration']),
        ],
    ],
];
