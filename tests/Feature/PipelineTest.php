<?php

// it can perform CLI commands

use Laravel\Prompts\Prompt;
use Laravel\Prompts\Support\ProcessResult;

use function Laravel\Prompts\pipeline;
use function Laravel\Prompts\process;

it('it renders a list of process spinners while executing those processes and returns their results.', function () {
    Prompt::fake();

    $results = pipeline([
        process(fn () => usleep(1000))->label('Task 1'),
        process(fn () => usleep(1002))->label('Task 2'),
        process(fn () => usleep(1003))->label('Task 3'),
    ]);

    expect($results)
        ->toHaveCount(3)
        ->toContainOnlyInstancesOf(ProcessResult::class);

    Prompt::assertOutputContains('Task 1');
    Prompt::assertOutputContains('Task 2');
    Prompt::assertOutputContains('Task 3');
});

it('fails a process when an exception is thrown', function () {
    Prompt::fake();

    $results = pipeline([
        process(fn () => usleep(1000) || throw new Exception('Some error'))->label('Task 1'),
    ]);

    expect($results[0])
        ->toBeInstanceOf(ProcessResult::class)
        ->failed()->toBeTrue();
});

it('can update each process status from within the process', function () {
    Prompt::fake();

    $results = pipeline([
        process(function () {
            usleep(1000);
            $this->fail();
        })->label('Failed Job'),
        process(function () {
            usleep(1000);
            $this->succeed();
        })->label('Successful Job'),
        process(function () {
            usleep(1000);
            $this->warn();
        })->label('Warning Job'),
    ]);

    expect($results[0])->failed()->toBeTrue();
    expect($results[1])->successful()->toBeTrue();
    expect($results[2])->successfulWithWarnings()->toBeTrue();
});

it('shows a process with warnings if the warning bag is not empty', function () {
    Prompt::fake();

    $results = pipeline([
        process(function () {
            usleep(1000);
            $this->addWarning('some warning');
        }),
    ]);

    expect($results[0])->successfulWithWarnings()->toBeTrue();
});

it('shows a process as an error if the error bag is not empty', function () {
    Prompt::fake();

    $results = pipeline([
        process(function () {
            usleep(1000);
            $this->addError('some error');
        }),
    ]);

    expect($results[0])->failed()->toBeTrue();
});

it('can perform cli commands', function () {
    Prompt::fake();

    $results = pipeline([
        process('echo "hi"'),
        process(['echo', 'hello']),
    ]);

    expect($results[0])
        ->successful()->toBeTrue()
        ->output()->toContain('hi')
        ->and($results[1])
        ->successful()->toBeTrue()
        ->output()->toContain('hello');
});
