<?php

use Laravel\Prompts\Support\Task;
use function Laravel\Prompts\tasks;


require __DIR__ . '/../vendor/autoload.php';

tasks([
    new Task("Task 1", fn() => sleep(random_int(1, 10))),
    new Task("Task 2", fn() => sleep(random_int(1, 10))),
    new Task("Task 3", fn() => sleep(random_int(1, 10))),
    new Task("Task 4", fn() => sleep(random_int(1, 10))),
    new Task("Task 5", fn() => sleep(random_int(1, 10))),
]);

/*task()*/
/*    ->label('label')*/
/*    ->tags(['one', 'two'])*/
/*    ->do(fn() => true)*/
/*    ->whenJobs('jobs')*/
/*    ->whenTags('are finished')*/
/*    ->areFinished()*/
/*    ->areNotFinished()*/
