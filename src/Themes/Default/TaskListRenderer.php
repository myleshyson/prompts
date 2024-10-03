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

    protected string $staticFrame = "⣷";

    public function __invoke(TaskList $manager): string
    {
        if (empty($manager->tasks)) {
            return $this;
        }

        if ($manager->static) {
            return $this->renderStatically($manager);
        }

        return $this->render($manager);
    }

    protected function renderStatically(TaskList $manager): self
    {
        foreach ($manager->tasks as $task) {
            $this->line(" {$this->cyan($this->staticFrame)} {$task->label()}");
        }

        return $this;
    }

    protected function render(TaskList $manager): self
    {
        foreach ($manager->tasks as $task) {
            $frame = $manager->static ? "⣷" : $this->frames[$manager->count % count($this->frames)];

            $this->line(match ($task->status()) {
                TaskStatus::SUCCESS => " {$this->green('✔︎')} {$task->label()} {$this->green('done!')}",
                TaskStatus::FAILED => " 💩 {$task->label()}",
                default => " {$this->cyan($frame)} {$task->label()}"
            });
        }

        return $this;
    }
}
