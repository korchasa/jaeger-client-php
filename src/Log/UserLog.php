<?php
declare(strict_types=1);

namespace Jaeger\Log;

use Jaeger\Tag\StringTag;

class UserLog extends AbstractLog
{
    public function __construct(string $level, string $message)
    {
        parent::__construct(
            [
                new StringTag('event', 'log'),
                new StringTag('level', $level),
                new StringTag('message', $message),
            ]
        );
    }
}
