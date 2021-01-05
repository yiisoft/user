<?php

declare(strict_types=1);

return [
    'yiisoft/user' => [
        'authUrl' => '/login',
        'autoLoginDuration' => new DateInterval('P5D'), // 5 days
    ],
];
