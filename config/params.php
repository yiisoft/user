<?php

declare(strict_types=1);

return [
    'yiisoft/user' => [
        'authUrl' => '/login',
        'autoLogin' => [
            'addCookie' => true,
            'duration' => 'P5D', // 5 days, see format on http://php.net/manual/dateinterval.construct.php
        ],
    ],
];
