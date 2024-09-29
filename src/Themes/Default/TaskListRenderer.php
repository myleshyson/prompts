<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\Support\TaskStatus;
use Laravel\Prompts\TaskList;
use Laravel\Prompts\Themes\Default\Renderer;

class TaskListRenderer extends Renderer
{
    protected array $frames = [
        "⣾",
        "⣽",
        "⣻",
        "⢿",
        "⡿",
        "⣟",
        "⣯",
        "⣷"
    ];

    public function __invoke(TaskList $manager): string
    {
        if (empty($manager->tasks)) {
            return $this;
        }

        $taskCount = count($manager->tasks);

        $this->line($this->underline(" Running {$taskCount} Tasks"));

        foreach ($manager->tasks as $task) {
            $frame = $this->frames[$manager->count % count($this->frames)];

            $this->output .= match ($task->status()) {
                TaskStatus::SUCCESS => " {$this->green('✔︎')} {$task->label()} {$this->green('done!')}",
                TaskStatus::FAILED => " 💩 {$task->label()}",
                default => " {$this->cyan($frame)} {$task->label()}"
            } . PHP_EOL;
        }

        return $this->output;
    }
}
