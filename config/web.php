<?php

declare(strict_types=1);

use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\AutoLoginMiddleware;
use Yiisoft\User\UserAuth;

/** @var array $params */

return [
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
];
