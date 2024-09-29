<?php

namespace Laravel\Prompts\Support;

use Closure;
use Laravel\Prompts\Support\TaskStatus;

class Task
{
    private TaskStatus $status = TaskStatus::IDLE;

    private ?string $errorMessage = null;

    private ?int $id = null;

    /**
     * @param string $label
     * @param Closure $callback
     */
    public function __construct(
        private string $label,
        private Closure $callback,
    ) {}

    public function __invoke(): static
    {
        return $this->run();
    }

    public function run(): static
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

        return $this;
    }

    public function getByteSize(): int
    {
        $memoryBefore = memory_get_usage();
        $instance = serialize(new self($this->label, $this->callback));
        $memoryAfter = memory_get_usage();

        return $memoryAfter - $memoryBefore;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }
    public function getError(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @param array $taskData
     * @return void
     */
    public function setValuesFrom(array $taskData): void
    {
        foreach ($taskData as $parameter => $value) {
            if (property_exists($this, $parameter)) {
                $this->{$parameter} = $value;
            }
        }
    }

    public function __serialize(): array
    {
        return [
            'label' => trim($this->label ?? ''),
            'id' => $this->id,
            'status' => $this->status,
            'errorMessage' => trim($this->errorMessage ?? '')
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->setValuesFrom($data);
    }
}
