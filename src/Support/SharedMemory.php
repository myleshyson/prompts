<?php

namespace Laravel\Prompts\Support;

use Laravel\Prompts\Exceptions\MemoryLimitExceededException;
use Shmop;

class SharedMemory
{
    protected Shmop|false|null $sharedMemory = null;

    protected ?int $memoryKey = null;

    protected ?int $semaphoreKey = null;

    protected $semaphore;

    public ?int $memorySize = null;

    protected int $memoryLimit = 128000000;

    /**
     * @param Task[] $tasks
     */
    public function __construct(int $initialSize = 16000)
    {
        $this->memorySize = $initialSize;

        $this->createStore();

        $this->createLock();

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

        try {
            $this->write($data);
        } catch (MemoryLimitExceededException $e) {
            $this->resize();
            $this->write($data);
        }

        $this->releaseLock();
    }

    public function destroy(): void
    {
        shmop_delete($this->sharedMemory);
    }

    protected function createStore(): void
    {
        $this->memoryKey = ftok(__FILE__, 'm');
        $this->sharedMemory = shmop_open($this->memoryKey, 'c', 0644, $this->memorySize);

        if (!$this->sharedMemory) {
            throw new \Exception("Could not create shared memory.");
        }
    }

    protected function createLock(): void
    {
        $this->semaphoreKey = ftok(__FILE__, 's');
        $this->semaphore = sem_get($this->semaphoreKey);

        if ($this->semaphore === false) {
            throw new \Exception("Could not create semaphore");
        }
    }

    protected function resize(): void
    {
        $this->destroy();

        $this->memorySize *= 2;

        if ($this->memorySize > $this->memoryLimit) {
            throw new MemoryLimitExceededException("Exceeded max shared memory limit of {$this->memoryLimit}");
        }

        $this->createStore();
    }

    protected function write(array $data): void
    {
        $serialized = serialize($data);

        $length = strlen($serialized);

        if ($length > $this->memorySize - 4) {
            $this->resize();
            $this->write($data);
        }

        shmop_write($this->sharedMemory, pack('N', $length), 0);
        shmop_write($this->sharedMemory, $serialized, 4);
    }

    protected function read(): array
    {
        $length = unpack('N', shmop_read($this->sharedMemory, 0, 4))[1];

        $data = shmop_read($this->sharedMemory, 4, $length);

        return unserialize($data);
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
