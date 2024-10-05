<?php

namespace Laravel\Prompts\Support;

use RuntimeException;

class SharedMemory
{
    protected string $filePath;

    protected int|false $pid = false;

    public function __construct()
    {
        $this->filePath = tempnam(sys_get_temp_dir(), '_shared_memory');
        $this->pid = getmypid();
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
            throw new RuntimeException('Failed to acquire lock');
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

    /**
     * @param  mixed[]  $data
     */
    protected function write(array $data): void
    {
        $serializedData = serialize($data);

        $fp = $this->openFile('w');

        fwrite($fp, $serializedData);

        fclose($fp);
    }

    /**
     * @return mixed[] array
     */
    protected function read(): array
    {
        $fp = $this->openFile('r');

        $content = '';

        while (! feof($fp)) {
            $content .= fread($fp, 8192);
        }

        fclose($fp);

        return $content ? unserialize($content) : [];
    }

    protected function openFile(string $mode): mixed
    {
        $attempts = 0;
        $maxAttempts = 5;
        $fp = false;

        while ($attempts < $maxAttempts) {
            /** @var resource|false $fp * */
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

    /**
     * @return void
     */
    public function __destruct()
    {
        if ($this->pid === getmypid()) {
            $this->destroy();
        }
    }
}
