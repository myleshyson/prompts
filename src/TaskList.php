<?php

namespace Laravel\Prompts;

use Laravel\Prompts\Support\SharedMemory;
use Laravel\Prompts\Support\Task;
use Laravel\Prompts\Support\TaskResult;
use Spatie\Fork\Fork;

class TaskList extends Prompt
{
    /**
     * The tasks we want to run.
     *
     * @var Task[]
     */
    public array $tasks = [];

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
    protected ?int $parentPid = null;

    /**
     * How long to wait between rendering each frame.
     */
    protected int $interval = 80;

    public function __construct(
        /**
         * The max number of concurrent processes to run at one time.
         */
        protected ?int $maxConcurrency = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * @param  Task[]  $tasks
     * @return TaskResult[]
     */
    public function run(array $tasks = []): array
    {
        $this->capturePreviousNewLines();

        $this->setTasks($tasks);

        if (! function_exists('pcntl_fork')) {
            return $this->renderStatically();
        }

        $this->initializeMemoryBlock();

        $originalAsync = pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn () => exit());

        try {
            $this->hideCursor();
            $this->render();

            $this->parentPid = posix_getpid();
            $this->renderLoopPid = pcntl_fork();

            if ($this->isChildProcess()) {
                $this->renderLoop();
            } else {
                $fork = Fork::new();

                if ($this->maxConcurrency !== null) {
                    $fork->concurrent($this->maxConcurrency);
                }

                $fork->after(parent: fn (TaskResult $result) => $this->memory->set($result->task->id(), $result));

                $fork->run(...$this->tasks);

                $this->resetTerminal($originalAsync);

                exit();
            }
        } catch (\Throwable $e) {
            $this->resetTerminal($originalAsync);

            throw $e;
        }

        return [];
    }

    /**
     * @param  Task[]  $tasks
     */
    protected function setTasks(array $tasks = []): void
    {
        foreach ($tasks as $task) {
            $this->tasks[$task->id()] = $task;
        }
    }

    protected function initializeMemoryBlock(): void
    {
        $this->memory = new SharedMemory;
    }

    protected function isChildProcess(): bool
    {
        return posix_getpid() !== $this->parentPid;
    }

    /**
     * @return TaskResult[]
     */
    protected function renderStatically(): array
    {
        $this->static = true;

        $results = [];

        $this->hideCursor();
        $this->render();

        foreach ($this->tasks as $task) {
            $results[] = $task->run();
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

            $this->retrieveUpdatedTasks();

            $this->render();

            $this->count++;

            usleep($this->interval * 1000);
        }
    }

    protected function retrieveUpdatedTasks(): void
    {
        foreach ($this->tasks as $task) {
            $result = $this->memory->get($task->id());

            if ($result) {
                $this->tasks[$result->task->id()] = $result->task;
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
