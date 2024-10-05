<?php

namespace Laravel\Prompts\Support;

enum ProcessStatus
{
    case WAITING;
    case RUNNING;
    case SUCCESS;
    case WARNING;
    case FAILED;

    public function isFinished(): bool
    {
        return $this !== ProcessStatus::WAITING && $this !== ProcessStatus::RUNNING;
    }
}
