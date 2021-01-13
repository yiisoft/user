<?php

declare(strict_types=1);

use Yiisoft\Auth\AuthenticationMethodInterface;
use Yiisoft\User\AutoLogin;
use Yiisoft\User\UserAuth;

/**
 * @var array $params
 */

return [
    UserAuth::class => [
        '__class' => UserAuth::class,
        'withAuthUrl()' => [$params['yiisoft/user']['authUrl']],
    ],

    AuthenticationMethodInterface::class => UserAuth::class,

    AutoLogin::class => [
        '__construct()' => [
            'duration' => new \DateInterval($params['yiisoft/user']['autoLogin']['duration']),
        ],
    ],
];
