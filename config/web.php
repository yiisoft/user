<?php

declare(strict_types=1);

use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\AutoLoginMiddleware;
use Yiisoft\User\AutoLogin;
use Yiisoft\User\CurrentIdentity\Storage\CurrentIdentityStorageInterface;
use Yiisoft\User\CurrentIdentity\Storage\SessionCurrentIdentityStorage;
use Yiisoft\User\UserAuth;

/** @var array $params */

return [
    CurrentIdentityStorageInterface::class => SessionCurrentIdentityStorage::class,

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
