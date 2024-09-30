<?php

namespace Laravel\Prompts\Support;

use RuntimeException;

class SharedMemory
{
    protected string $filePath;

    public function __construct()
    {
        $this->filePath = tempnam(sys_get_temp_dir(), '_shared_memory');

        $this->createStore();
    }

    public function get(int $key): mixed
    {
        $data = $this->read();

        return $data[$key] ?? null;
    }

    public function set(int $key, mixed $value): void
    {
        $fp = $this->openFile('c+');

        if (flock($fp, LOCK_EX)) {
            $data = $this->read();

            $data[$key] = $value;

            $this->write($data);

            flock($fp, LOCK_UN);
        } else {
            throw new RuntimeException("Failed to acquire lock");
        }

        fclose($fp);
    }

    public function destroy(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    protected function createStore(): void
    {
        $fp = $this->openFile('w');

        fwrite($fp, serialize([]));

        fclose($fp);

        chmod($this->filePath, 0666);
    }

    protected function write(array $data): void
    {
        $serializedData = serialize($data);

        $fp = $this->openFile('w');

        fwrite($fp, $serializedData);

        fclose($fp);
    }

    protected function read(): array
    {
        $fp = $this->openFile('r');

        $content = '';

        while (!feof($fp)) {
            $content .= fread($fp, 8192);
        }

        fclose($fp);

        return $content ? unserialize($content) : [];
    }

    protected function openFile(string $mode)
    {
        $attempts = 0;
        $maxAttempts = 5;
        $fp = false;

        while ($attempts < $maxAttempts) {
            $fp = fopen($this->filePath, $mode);

            if ($fp !== false) {
                break;
            }

            $attempts++;

            usleep(10000); // Wait for 10ms before trying again
        }

        if ($fp === false) {
            throw new RuntimeException("Failed to open file after $maxAttempts attempts");
        }

        return $fp;
    }

    public function __destruct()
    {
        // Only destroy the file if it's the parent process
        if (getmypid() === posix_getpgid(getmypid())) {
            $this->destroy();
        }
    }
}
