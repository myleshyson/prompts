<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\Support\ProcessStatus;
use Laravel\Prompts\Pipeline;

class PipelineRenderer extends Renderer
{
    /**
     * The frames of the spinner.
     *
     * @var array<string>
     */
    protected array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    /**
     * The frame to render when the spinner is static.
     */
    protected string $staticFrame = '⠶';

    /**
     * The interval between frames.
     */
    protected int $interval = 75;

    public function __invoke(Pipeline $manager): string
    {
        if (empty($manager->processes)) {
            return $this;
        }

        if ($manager->static) {
            return $this->renderStatically($manager);
        }

        return $this->render($manager);
    }

    protected function renderStatically(Pipeline $manager): self
    {
        foreach ($manager->processes as $process) {
            $this->line("  {$this->cyan($this->staticFrame)} {$process->getLabel()}");
        }

        return $this;
    }

    protected function render(Pipeline $manager): self
    {
        foreach ($manager->processes as $process) {
            $frame = $this->frames[$manager->count % count($this->frames)];

            match ($process->getStatus()) {
                ProcessStatus::SUCCESS => $this->success($process->getLabel()),
                ProcessStatus::WARNING => $this->warning($process->getLabel()),
                ProcessStatus::FAILED => $this->error($process->getLabel()),
                default => $this->line("  {$this->cyan($frame)} {$process->getLabel()}")
            };
        }

        return $this;
    }
}
