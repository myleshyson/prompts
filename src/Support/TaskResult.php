<?php

namespace Laravel\Prompts\Support;

use Illuminate\Contracts\Process\ProcessResult;
use Laravel\Prompts\Exceptions\TaskFailedException;

class TaskResult implements ProcessResult
{
    /**
     * The underlying task instance
     */
    public Task $task;

    public static function from(Task $task): self
    {
        return new self($task);
    }

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function command()
    {
        return $this->task->label();
    }

    public function successful()
    {
        return $this->task->status() === TaskStatus::SUCCESS;
    }

    public function failed()
    {
        return $this->task->status() === TaskStatus::FAILED;
    }

    public function exitCode()
    {
        return match ($this->task->status()) {
            TaskStatus::SUCCESS => 0,
            TaskStatus::FAILED => 1,
            default => null
        };
    }

    public function output()
    {
        return $this->task->output();
    }

    public function seeInOutput(string $output)
    {
        return str_contains($this->output(), $output);
    }

    public function errorOutput()
    {
        return $this->task->getError();
    }

    public function seeInErrorOutput(string $output)
    {
        return str_contains($this->errorOutput(), $output);
    }

    public function throw(?callable $callback = null)
    {
        if ($this->successful()) {
            return $this;
        }

        if ($callback) {
            $callback();
        }

        throw new TaskFailedException;
    }

    public function throwIf(bool $condition, ?callable $callback = null)
    {
        if ($condition) {
            return $this->throw($callback);
        }

        return $this;
    }
}
