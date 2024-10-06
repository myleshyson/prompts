<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\Pipeline;
use Laravel\Prompts\Support\ProcessStatus;

class PipelineRenderer extends Renderer
{
    /**
     * The frames of the spinner.
     *
     * @var array<string>
     */
    public static array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    /**
     * The frame to render when the spinner is static.
     */
    public static string $staticFrame = '⠶';

    /**
     * How long to wait between rendering each frame.
     */
    public static int $interval = 75;

    public function __invoke(Pipeline $manager): string
    {
        $manager->interval = self::$interval;

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
            $this->line("  {$this->cyan(self::$staticFrame)} {$process->getLabel()}");
        }

        return $this;
    }

    protected function render(Pipeline $manager): self
    {
        foreach ($manager->processes as $process) {
            $frame = self::$frames[$manager->count % count(self::$frames)];

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
