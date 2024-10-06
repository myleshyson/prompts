<?php

namespace Laravel\Prompts\Support;

use Closure;
use Symfony\Component\Process\Process as CommandLineProcess;

class Process
{
    /**
     * The current status of the process.
     */
    private ProcessStatus $status = ProcessStatus::WAITING;

    /**
     * The process output, if there is any.
     */
    private ?string $output = null;

    /**
     * Error messages.
     *
     * @var array<int, string>
     */
    private array $errorBag = [];

    /**
     * The warning message, if there is one.
     *
     * @var array<int, string>
     */
    private array $warningBag = [];

    /**
     * The spl_object_id of this instance.
     */
    private ?int $id = null;

    /**
     * The label that will display in the terminal.
     */
    private ?string $label = null;

    /**
     * The callback to fire when this process runs. Can either be a closure
     * a command line command as a string, or a command line command
     * as an array of arguments.
     *
     * @var Closure|string|array<int, string>
     */
    private Closure|string|array $work;

    /**
     * @param  Closure|string| array<int, string>  $work
     *
     * @param-closure-this static $work
     */
    public function __construct(Closure|string|array $work)
    {
        $this->work = $work;
        $this->id = spl_object_id($this);
    }

    public function __invoke(): ProcessResult
    {
        return $this->run();
    }

    public function run(): ProcessResult
    {
        $this->setStatus(ProcessStatus::RUNNING);

        try {
            $this->processResult($this->resolveWorkCallback());
        } catch (\Throwable $error) {
            $this->addError($error->getMessage());
            $this->setStatus(ProcessStatus::FAILED);
        }

        return ProcessResult::from($this);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function addWarning(string $message): void
    {
        $this->warningBag[] = $message;
    }

    public function addError(string $message): void
    {
        $this->errorBag[] = $message;
    }

    public function fail(): void
    {
        $this->setStatus(ProcessStatus::FAILED);
    }

    public function warn(): void
    {
        $this->setStatus(ProcessStatus::WARNING);
    }

    public function succeed(): void
    {
        $this->setStatus(ProcessStatus::SUCCESS);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label ?? "Process {$this->id}";
    }

    public function getStatus(): ProcessStatus
    {
        return $this->status;
    }

    public function setStatus(ProcessStatus $status): void
    {
        $this->status = $status;
    }

    public function setOutput(?string $output = null): void
    {
        $this->output = $output;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * @return array<int, string>
     */
    public function getErrorBag(): array
    {
        return $this->errorBag;
    }

    /**
     * @return array<int, string>
     */
    public function getWarningBag(): array
    {
        return $this->warningBag;
    }

    /**
     * @param  array<string, string| array<int, string>|int|ProcessStatus>  $processData
     */
    public function setValuesFrom(array $processData): void
    {
        foreach ($processData as $parameter => $value) {
            if (property_exists($this, $parameter)) {
                $this->{$parameter} = $value;
            }
        }
    }

    protected function resolveWorkCallback(): Closure
    {
        if (is_string($this->work)) {
            return Closure::bind(fn () => $this->runCommandLineProcess(CommandLineProcess::fromShellCommandline($this->work)), $this, $this);
        }

        if (is_array($this->work)) {
            return Closure::bind(fn () => $this->runCommandLineProcess((new CommandLineProcess($this->work))), $this, $this);
        }

        return Closure::bind($this->work, $this, $this);
    }

    protected function runCommandLineProcess(CommandLineProcess $process): bool
    {
        $process->run();

        $this->setOutput($process->getOutput());

        if (! $process->isSuccessful()) {
            $this->addError($process->getErrorOutput());

            return false;
        }

        return true;
    }

    /**
     * @param-closure-this self $callback
     */
    protected function processResult(Closure $callback): void
    {
        $result = $callback();

        if ($this->status->isFinished()) {
            return;
        }

        if ($result === false || ! empty($this->getErrorBag())) {
            $this->setStatus(ProcessStatus::FAILED);

            return;
        }

        if (! empty($this->getWarningBag())) {
            $this->setStatus(ProcessStatus::WARNING);

            return;
        }

        $this->setStatus(ProcessStatus::SUCCESS);
    }

    /**
     * @return array<string, string|int| array<int, string>|ProcessStatus>
     */
    public function __serialize(): array
    {
        return [
            'label' => $this->label,
            'id' => $this->id,
            'status' => $this->status,
            'output' => $this->getOutput(),
            'warningBag' => $this->getWarningBag(),
            'errorBag' => $this->getErrorBag(),
        ];
    }

    /**
     * @param  array<string,  array<int, string>|string|int|ProcessStatus>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->setValuesFrom($data);
    }
}
