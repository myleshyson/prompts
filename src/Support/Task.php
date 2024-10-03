<?php

namespace Laravel\Prompts\Support;

use Closure;

class Task
{
    /**
     * The current status of the task.
     */
    private TaskStatus $status = TaskStatus::IDLE;

    /**
     * The task output, if there is any.
     */
    private ?string $output = null;

    /**
     * The error message, if there is one.
     */
    private ?string $errorMessage = null;

    /**
     * The spl_object_id of this instance.
     */
    private ?int $id = null;

    /**
     * The label that will display in the terminal.
     */
    private string $label;

    /**
     * The callback to fire when this task runs.
     */
    private Closure $callback;

    public function __construct(string $label, Closure $callback)
    {
        $this->label = $label;
        $this->callback = $callback;
        $this->id = spl_object_id($this);
    }

    public function __invoke(): TaskResult
    {
        return $this->run();
    }

    public function run(): TaskResult
    {
        $this->status = TaskStatus::RUNNING;

        $boundClosure = $this->callback->bindTo($this, $this);

        try {
            $result = $boundClosure();

            if ($result !== false) {
                $this->status = TaskStatus::SUCCESS;
            } else {
                $this->status = TaskStatus::FAILED;
            }
        } catch (\Throwable $error) {
            $this->errorMessage = $error->getMessage();
            $this->status = TaskStatus::FAILED;
        }

        return TaskResult::from($this);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function output(): ?string
    {
        return $this->output;
    }

    public function getError(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @param  array<string, string|int|TaskStatus>  $taskData
     */
    public function setValuesFrom(array $taskData): void
    {
        foreach ($taskData as $parameter => $value) {
            if (property_exists($this, $parameter)) {
                $this->{$parameter} = $value;
            }
        }
    }

    /**
     * @return array<string, string|int|TaskStatus>
     */
    public function __serialize(): array
    {
        return [
            'label' => trim($this->label ?? ''),
            'id' => $this->id,
            'status' => $this->status,
            'errorMessage' => trim($this->errorMessage ?? ''),
        ];
    }

    /**
     * @param  array<string, string|int|TaskStatus>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->setValuesFrom($data);
    }
}
