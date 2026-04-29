<?php

it('inspects the brazilian tech job boards fixture source without importing leads', function (): void {
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.brazilian_tech_job_board_targets', config('job_discovery.fixture_brazilian_tech_job_board_targets'));

    $this->artisan('discovery:inspect-source', [
        'source' => 'brazilian-tech-job-boards',
        '--query' => 'laravel',
    ])
        ->expectsOutput('Source: Brazilian Tech Job Boards')
        ->expectsOutput('Listing HTTP status: 200')
        ->expectsOutput('Candidate links found: 6')
        ->expectsOutput('Parsed jobs after filtering: 3')
        ->expectsOutput('Matched candidates: 1')
        ->expectsOutput('Skipped by query: 2')
        ->expectsOutput('Normalized query: laravel')
        ->expectsOutput('Target ProgramaThor (programathor): fetched 4 · matched 1 · query-skipped 1 · expired 1 · missing company 1 · failed 0')
        ->expectsOutput('Target Remotar (remotar): fetched 2 · matched 0 · query-skipped 1 · expired 1 · missing company 0 · failed 0')
        ->assertExitCode(0);
});

it('uses resume-derived query profiles while inspecting the brazilian tech job boards fixture source', function (): void {
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.brazilian_tech_job_board_targets', [
        config('job_discovery.fixture_brazilian_tech_job_board_targets')[1],
    ]);

    $this->artisan('discovery:inspect-source', [
        'source' => 'brazilian-tech-job-boards',
        '--query' => 'frontend',
        '--resume-text' => 'Vue.js engineer with JavaScript SPA product experience.',
        '--skill' => ['Vue.js'],
    ])
        ->expectsOutput('Matched candidates: 1')
        ->expectsOutput('Normalized query: frontend')
        ->expectsOutput('Query profile keys: frontend_vue')
        ->expectsOutput('Target Remotar (remotar): fetched 2 · matched 1 · query-skipped 0 · expired 1 · missing company 0 · failed 0')
        ->assertExitCode(0);
});
