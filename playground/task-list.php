<?php

use Laravel\Prompts\Support\Task;
use function Laravel\Prompts\tasks;


require __DIR__ . '/../vendor/autoload.php';

tasks([
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('Three', fn() => sleep(9)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('Three', fn() => sleep(9)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('Three', fn() => sleep(9)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('Three', fn() => sleep(9)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('Three', fn() => sleep(9)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('One', fn() => sleep(6)),
    new Task('Two', fn() => sleep(3)),
    new Task('Three', fn() => sleep(9)),
    new Task('Three', fn() => sleep(9)),
    new Task('Three', fn() => sleep(9)),
]);
