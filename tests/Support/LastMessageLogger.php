<?php

declare(strict_types=1);

namespace Yiisoft\User\Tests\Support;

use Psr\Log\AbstractLogger;

final class LastMessageLogger extends AbstractLogger
{
    private ?string $lastMessage = null;

    public function log($level, $message, array $context = []): void
    {
        $this->lastMessage = $message;
    }

    public function getLastMessage(): ?string
    {
        return $this->lastMessage;
    }
}
