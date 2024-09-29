<?php

namespace Laravel\Prompts;

use Laravel\Prompts\Support\Task;
use Closure;
use Laravel\Prompts\Support\SharedMemory;
use Spatie\Fork\Fork;
use Symfony\Component\VarDumper\VarDumper;

class TaskList extends Prompt
{
    /**
     * @var Task[] $tasks
     */
    public array $tasks = [];

    public int $count = 0;

    public ?SharedMemory $memory = null;

    protected ?int $renderLoopPid = null;

    protected ?int $pid = null;

    protected int $interval = 80;

    public function value(): bool
    {
        return true;
    }

    /**
     * @param AsyncTask[] $tasks
     */
    public function run(array $tasks = []): void
    {
        $this->capturePreviousNewLines();

        $this->setTasks($tasks);

        /*if (! function_exists('pcntl_fork')) {*/
        /*    return $this->renderStatically($callback);*/
        /*}*/

        $this->initializeMemoryBlock($tasks);

        $originalAsync = pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn() => exit());

        try {
            $this->hideCursor();

            $this->pid = posix_getpid();

            $this->renderLoopPid = pcntl_fork();

            if ($this->isChildProcess($this->renderLoopPid)) {
                $this->renderLoop();
            } else {
                Fork::new()
                    ->after(parent: fn(Task $task) => $this->memory->set($task->id(), $task))
                    ->run(
                        ...$this->tasks
                    );
                $this->resetTerminal($originalAsync);
            }
        } catch (\Throwable $e) {
            $this->resetTerminal($originalAsync);

            throw $e;
        }
    }

    protected function setTasks(array $tasks = []): void
    {
        foreach ($tasks as $task) {
            $task->setId(spl_object_id($task));
            $this->tasks[$task->id()] = $task;
        }
    }

    protected function initializeMemoryBlock($tasks)
    {
        $totalTaskByteSize = array_sum(array_map(fn($task) => $task->getByteSize(), $tasks));

        $this->memory = new SharedMemory($totalTaskByteSize);

        foreach ($this->tasks as $task) {
            $this->memory->set($task->id(), $task);
        }
    }

    protected function isChildProcess(?int $id): bool
    {
        return $id === 0;
    }

    protected function renderStatically(Closure $callback): mixed
    {
        return true;
        //$this->static = true;
        //
        //try {
        //    $this->hideCursor();
        //    $this->render();
        //
        //    $result = $callback();
        //} finally {
        //    $this->eraseRenderedLines();
        //}
        //
        //return $result;
    }

    protected function resetTerminal(bool $originalAsync): void
    {
        // ensure our render loop has time to finish
        usleep($this->interval * 1000);

        pcntl_async_signals($originalAsync);
        pcntl_signal(SIGINT, SIG_DFL);
    }

    /**
     * @param AsyncTask[] $tasks
     */
    protected function renderLoop(): void
    {
        while (true) {

            foreach ($this->tasks as $task) {
                $task = $this->memory->get($task->id());
                $this->tasks[$task->id()] = $task;
            }

            $this->render();
            $this->count++;

            usleep($this->interval * 1000);
        }
    }

    /**
     * Clean up after the spinner.
     */
    public function __destruct()
    {
        if ($this->pid === posix_getpid()) {

            if ($this->renderLoopPid) {
                posix_kill($this->renderLoopPid, SIGHUP);
            }

            $this->memory->destroy();
        }

        parent::__destruct();
    }
}
