<?php

namespace Laravel\Prompts\Support;

use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Exceptions\ProcessFailedException;

use function Laravel\Prompts\table;

class ProcessResult
{
    use Colors;

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

    public function process(): string
    {
        return $this->process->getLabel();
    }

    public function successfulWithWarnings(): bool
    {
        return $this->process->getStatus() === ProcessStatus::WARNING;
    }

    public function successful(): bool
    {
        return $this->process->getStatus() === ProcessStatus::SUCCESS || $this->successfulWithWarnings();
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

    /**
     * @return array<int, string>
     */
    public function errorBag(): array
    {
        return $this->process->getErrorBag();
    }

    /**
     * @return array<int, string>
     */
    public function warningBag(): array
    {
        return $this->process->getWarningBag();
    }

    public function errorSummary(): void
    {
        if (empty($this->errorBag())) {
            return;
        }

        table(
            headers: [$this->reset($this->process())],
            rows: $this->getSummaryRowsForStatus(ProcessStatus::FAILED)
        );
    }

    public function warningSummary(): void
    {
        if (empty($this->warningBag())) {
            return;
        }

        table(
            headers: [$this->process()],
            rows: $this->getSummaryRowsForStatus(ProcessStatus::WARNING)
        );
    }

    public function summary(): void
    {
        if (empty($this->errorBag()) && empty($this->warningBag())) {
            return;
        }

        table(
            headers: [$this->process()],
            rows: [
                ...$this->getSummaryRowsForStatus(ProcessStatus::FAILED),
                ...$this->getSummaryRowsForStatus(ProcessStatus::WARNING)
            ]
        );
    }

    public function throw(?callable $callback = null): self
    {
        if ($this->successful()) {
            return $this;
        }

        if ($callback) {
            $callback();
        }

        throw new ProcessFailedException;
    }

    public function throwIf(bool $condition, ?callable $callback = null): self
    {
        if ($condition) {
            return $this->throw($callback);
        }

        return $this;
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function getSummaryRowsForStatus(ProcessStatus $status): array
    {
        $messages = match($status) {
            ProcessStatus::WARNING => $this->warningBag(),
            ProcessStatus::FAILED => $this->errorBag(),
            default => []
        };

        if (empty($messages)) {
            return [];
        }

        return [
            [$this->bold($status->format($status->heading()))],
            ...array_map(fn($message) => [$status->format("· {$message}")], $messages)
        ];
    }


}
