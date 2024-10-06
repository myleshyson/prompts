<?php

use function Laravel\Prompts\pipeline;
use function Laravel\Prompts\process;

require __DIR__.'/../vendor/autoload.php';

$results = pipeline([
    process('ping -c 5 google.com')->label('Task 1'),
    process(['timeout', '2'])->label('Task 2'),
    process(fn () => usleep(1000)),
]);

$results[1]->errorSummary();
