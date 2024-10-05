<?php

namespace Laravel\Prompts\Support;

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Laravel\Prompts\Exceptions\ProcessFailedException;

class ProcessResult implements ProcessResultContract
{
    /**
     * The underlying process instance.
     */
    public Process $process;

    public static function from(Process $process): self
    {
        return new self($process);
    }

    public function __construct(Process $process)
    {
        $this->process = $process;
    }

    public function command(): string
    {
        return $this->process->getLabel();
    }

    public function successful(): bool
    {
        return $this->process->getStatus() === ProcessStatus::SUCCESS;
    }

    public function failed(): bool
    {
        return $this->process->getStatus() === ProcessStatus::FAILED;
    }

    public function exitCode(): ?int
    {
        return match ($this->process->getStatus()) {
            ProcessStatus::SUCCESS, ProcessStatus::WARNING => 0,
            ProcessStatus::FAILED => 1,
            default => null
        };
    }

    public function output(): ?string
    {
        return $this->process->getOutput();
    }

    public function seeInOutput(string $output): bool
    {
        return str_contains($this->output(), $output);
    }

    public function warningOutput(): ?string
    {
       return $this->process->getWarningMessage();
    }

    public function seeInWarningOutput(string $output): bool
    {
       return str_contains($this->warningOutput(), $output);
    }

    public function errorOutput(): ?string
    {
        return $this->process->getErrorMessage();
    }

    public function seeInErrorOutput(string $output): bool
    {
        return str_contains($this->errorOutput(), $output);
    }

    public function throw(?callable $callback = null): self
    {
        if ($this->successful()) {
            return $this;
        }

        if ($callback) {
            $callback();
        }

        throw new ProcessFailedException();
    }

    public function throwIf(bool $condition, ?callable $callback = null): self
    {
        if ($condition) {
            return $this->throw($callback);
        }

        return $this;
    }
}
