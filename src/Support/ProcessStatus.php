<?php

namespace Laravel\Prompts\Support;

use Laravel\Prompts\Concerns\Colors;

enum ProcessStatus
{
    use Colors;

    case WAITING;
    case RUNNING;
    case SUCCESS;
    case WARNING;
    case FAILED;

    public function isFinished(): bool
    {
        return $this !== self::WAITING && $this !== self::RUNNING;
    }

    public function format(string $message): string
    {
        return match($this) {
            self::WARNING => $this->yellow($message),
            self::FAILED => $this->red($message),
            default => $message
        };
    }

    public function heading(): string
    {
       return match($this) {
           self::WARNING => 'Warnings',
           self::FAILED => 'Errors',
           default => ''
       };
    }
}
