<?php

namespace Laravel\Prompts\Support;

use ArrayObject;
use Traversable;

class TaskCollection implements \IteratorAggregate
{
    protected array $tasks = [];

    protected function __construct(array $tasks = [])
    {
        foreach ($tasks as $task) {
            if (! $task instanceof Task) {
                throw new \Exception("Wrong class dude");
            }

            $this->tasks[] = $task;
        }
    }
    public function getIterator(): Traversable
    {
        return new ArrayObject($this->tasks);
    }
}
