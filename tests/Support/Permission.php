<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

enum Permission: string
{
    case EDIT = 'edit';
    case DELETE = 'delete';
}
