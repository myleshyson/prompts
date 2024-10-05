<?php

namespace Laravel\Prompts;

use Laravel\Prompts\Support\SharedMemory;
use Laravel\Prompts\Support\Process;
use Laravel\Prompts\Support\ProcessResult;
use Spatie\Fork\Fork;

class Pipeline extends Prompt
{
    /**
     * The processes we want to run.
     *
     * @var Process[]
     */
    public array $processes = [];

    /**
     * Whether the spinner can only be rendered once.
     */
    public bool $static = false;

    /**
     * The number of times the list as rendered.
     */
    public int $count = 0;

    /**
     * The shared space processes use to communicate.
     */
    public ?SharedMemory $memory = null;

    /**
     * The process responsible for rendering the list.
     */
    protected ?int $renderLoopPid = null;

    /**
     * The parent process id.
     */
    protected int|false $parentPid = false;

    /**
     * How long to wait between rendering each frame.
     */
    protected int $interval = 80;

    /**
     * The max number of concurrent processes to run at one time.
     */
    protected ?int $maxConcurrency = null;

    public function __construct(?int $maxConcurrency)
    {
        $this->maxConcurrency = $maxConcurrency;
        $this->parentPid = getmypid();
    }

    /**
     * {@inheritdoc}
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * @param Process[] $processes
     *
     * @return ProcessResult[]
     */
    public function run(array $processes = []): array
    {
        $this->capturePreviousNewLines();

        $this->setProcesses($processes);

        if (! function_exists('pcntl_fork')) {
            return $this->renderStatically();
        }

        $this->initializeMemoryBlock();

        $originalAsync = pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn() => exit());

        try {
            $this->hideCursor();
            $this->render();

            $this->renderLoopPid = pcntl_fork();

            if ($this->isChildProcess()) {
                $this->renderLoop();
            } else {
                $fork = Fork::new();

                if ($this->maxConcurrency !== null) {
                    $fork->concurrent($this->maxConcurrency);
                }

                $fork->after(parent: fn(ProcessResult $result) => $this->memory->set($result->process->getId(), $result));

                $results = $fork->run(...$this->processes);

                $this->resetTerminal($originalAsync);

                if ($this->isChildProcess()) {
                    exit();
                }

                return $results;
            }
        } catch (\Throwable $e) {
            $this->resetTerminal($originalAsync);

            throw $e;
        }
    }

    /**
     * @param  Process[]  $processes
     */
    protected function setProcesses(array $processes = []): void
    {
        foreach ($processes as $process) {
            $this->processes[$process->getId()] = $process;
        }
    }

    protected function initializeMemoryBlock(): void
    {
        $this->memory = new SharedMemory;
    }

    protected function isChildProcess(): bool
    {
        return getmypid() !== $this->parentPid;
    }

    /**
     * @return ProcessResult[]
     */
    protected function renderStatically(): array
    {
        $this->static = true;

        $results = [];

        $this->hideCursor();
        $this->render();

        foreach ($this->processes as $process) {
            $results[] = $process->run();
        }

        return $results;
    }

    protected function resetTerminal(bool $originalAsync): void
    {
        // ensure our render loop has time to finish
        usleep($this->interval * 1000);

        pcntl_async_signals($originalAsync);
        pcntl_signal(SIGINT, SIG_DFL);
    }

    protected function renderLoop(): void
    {
        while (true) { // @phpstan-ignore-line
            $this->hideCursor();

            $this->retrieveUpdatedProcesses();

            $this->render();

            $this->count++;

            usleep($this->interval * 1000);
        }
    }

    protected function retrieveUpdatedProcesses(): void
    {
        foreach ($this->processes as $process) {
            $result = $this->memory->get($process->getId());

            if ($result) {
                $this->processes[$result->process->getId()] = $result->process;
            }
        }
    }

    /**
     * Clean up after the spinner.
     */
    public function __destruct()
    {
        if (! $this->isChildProcess()) {

            if ($this->renderLoopPid) {
                posix_kill($this->renderLoopPid, SIGHUP);
            }

            $this->memory->destroy();

            parent::__destruct();
        }
    }
}
