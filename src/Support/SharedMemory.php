<?php

namespace Laravel\Prompts\Support;

use Shmop;

class SharedMemory
{
    protected Shmop|false|null $sharedMemory = null;

    protected ?int $memoryKey = null;

    protected ?int $semaphoreKey = null;

    protected $semaphore;

    protected ?int $memorySize = null;

    public function __construct(int $size = 1024)
    {
        $this->memorySize = $size + 1024;
        $this->semaphoreKey = ftok(__FILE__, 's');
        $this->semaphore = sem_get($this->semaphoreKey);

        if ($this->semaphore === false) {
            throw new \Exception("Could not create semaphore");
        }

        $this->memoryKey = ftok(__FILE__, 'm');
        $this->sharedMemory = shmop_open($this->memoryKey, 'c', 0644, $this->memorySize);

        if (!$this->sharedMemory) {
            throw new \Exception("Could not create shared memory.");
        }

        $this->aquireLock();

        $this->write([]);

        $this->releaseLock();
    }

    public function get(int $key): mixed
    {
        $this->aquireLock();

        $data = $this->read();

        $this->releaseLock();

        return $data[$key] ?? null;
    }

    public function set(int $key, mixed $value): void
    {
        $this->aquireLock();

        $data = $this->read();

        $data[$key] = $value;

        $this->write($data);

        $this->releaseLock();
    }

    public function destroy(): void
    {
        shmop_delete($this->sharedMemory);
    }

    protected function write(array $data): void
    {
        $serialized = serialize($data);

        $length = strlen($serialized);

        if ($length > $this->memorySize - 4) {
            throw new \Exception("Data exceeds memory size");
        }

        shmop_write($this->sharedMemory, pack('N', $length), 0);
        shmop_write($this->sharedMemory, $serialized, 4);
    }

    protected function read(): array
    {
        $length = unpack('N', shmop_read($this->sharedMemory, 0, 4))[1];

        $data = shmop_read($this->sharedMemory, 4, $length);

        return unserialize($data, ['allowed_classes' => [Task::class, TaskStatus::class]]);
    }

    protected function aquireLock(): void
    {
        if (!sem_acquire($this->semaphore)) {
            throw new \Exception("Failed to aquire semaphore");
        }
    }

    protected function releaseLock(): void
    {
        sem_release($this->semaphore);
    }
}
