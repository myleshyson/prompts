<?php

use function Laravel\Prompts\pipeline;
use function Laravel\Prompts\process;

require __DIR__ . '/../vendor/autoload.php';

pipeline([
    process(fn() => sleep(random_int(1, 10)))->label('Task 1'),
    process(function() {
        sleep(4);
        $this->warn('ominous warning');
    }),
    process('ping -c 4 google.com')->label('Task 2'),
    process(['ping', '-c', '6', 'google.com'])->label('Task 3')
]);
