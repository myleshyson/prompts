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
     * The success message, if there is one.
     */
    private ?string $successMessage = null;

    /**
     * The error message, if there is one.
     */
    private ?string $errorMessage = null;

    /**
     * The warning message, if there is one.
     */
    private ?string $warningMessage = null;

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
     * @var Closure|string|string[]
     */
    private Closure|string|array $work;

    /**
     * @param Closure|string|string[] $work
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
        $this->status = ProcessStatus::RUNNING;

        try {
            $this->processResult($this->resolveWorkCallback());
        } catch (\Throwable $error) {
            $this->errorMessage = $error->getMessage();
            $this->status = ProcessStatus::FAILED;
        }

        return ProcessResult::from($this);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function warn(?string $message = null): void
    {
        $this->status = ProcessStatus::WARNING;
        $this->warningMessage = $message;
    }

    public function fail(?string $message = null): void
    {
        $this->status = ProcessStatus::FAILED;
        $this->errorMessage = $message;
    }

    public function succeed(?string $message = null): void
    {
        $this->status = ProcessStatus::SUCCESS;
        $this->successMessage = $message;
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

    public function setOutput(?string $output = null): void
    {
        $this->output = $output;
    }

    public function getOutput(): ?string
    {
        return trim($this->output);
    }

    public function getErrorMessage(): ?string
    {
        return trim($this->errorMessage);
    }

    public function getWarningMessage(): ?string
    {
       return trim($this->warningMessage);
    }

    public function getSuccessMessage(): ?string
    {
       return trim($this->successMessage);
    }

    /**
     * @param  array<string, string|int|ProcessStatus>  $processData
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
            return Closure::bind(fn() => $this->runCommandLineProcess(CommandLineProcess::fromShellCommandline($this->work)), $this, $this);
        }

        if (is_array($this->work)) {
            return Closure::bind(fn() => $this->runCommandLineProcess((new CommandLineProcess($this->work))), $this, $this);
        }

        return Closure::bind($this->work, $this, $this);
    }

    protected function runCommandLineProcess(CommandLineProcess $process): bool
    {
        $process->run();

        $this->output = $process->getOutput();
        $this->errorMessage = $process->getErrorOutput();

        return $process->isSuccessful();
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

        if ($result === false) {
            $this->status = ProcessStatus::FAILED;
            return;
        }

        $this->status = ProcessStatus::SUCCESS;
    }

    /**
     * @return array<string, string|int|ProcessStatus>
     */
    public function __serialize(): array
    {
        return [
            'label' => $this->label,
            'id' => $this->id,
            'status' => $this->status,
            'output' => $this->getOutput(),
            'warningMessage' => $this->getWarningMessage(),
            'successMessage' => $this->getSuccessMessage(),
            'errorMessage' => $this->getSuccessMessage(),
        ];
    }

    /**
     * @param  array<string, string|int|ProcessStatus>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->setValuesFrom($data);
    }
}
