<?php

declare(strict_types=1);

return [
    'yiisoft/user' => [
        'authUrl' => '/login',
        'cookieLogin' => [
            'forceAddCookie' => false,
            'duration' => 'P5D', // 5 days, see format on https://www.php.net/manual/dateinterval.construct.php
            'signatureKey' => null, // secret key to sign the auto-login cookie value; keep `null` to store it unsigned
        ],
    ],
];
