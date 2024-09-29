<?php

namespace Laravel\Prompts\Support;

enum TaskStatus
{
    case IDLE;
    case RUNNING;
    case SUCCESS;
    case FAILED;
}
