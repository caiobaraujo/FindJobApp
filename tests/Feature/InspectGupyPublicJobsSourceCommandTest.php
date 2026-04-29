<?php

it('inspects the gupy public jobs fixture source without importing leads', function (): void {
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.gupy_public_job_targets', config('job_discovery.fixture_gupy_public_job_targets'));

    $this->artisan('discovery:inspect-source', [
        'source' => 'gupy-public-jobs',
        '--query' => 'python',
    ])
        ->expectsOutput('Source: Gupy Public Jobs')
        ->expectsOutput('Listing HTTP status: 200')
        ->expectsOutput('Candidate links found: 7')
        ->expectsOutput('Parsed jobs after filtering: 5')
        ->expectsOutput('Matched candidates: 2')
        ->expectsOutput('Skipped by query: 3')
        ->expectsOutput('Normalized query: python')
        ->expectsOutput('Target Afya (gupy): fetched 3 · matched 1 · query-skipped 1 · expired 1 · missing company 0 · failed 0 · detail ok 2 · detail failed 0')
        ->expectsOutput('Target Gran (gupy): fetched 1 · matched 1 · query-skipped 0 · expired 0 · missing company 0 · failed 0 · detail ok 1 · detail failed 0')
        ->expectsOutput('Target https://mystery.gupy.io/ (gupy): fetched 1 · matched 0 · query-skipped 0 · expired 0 · missing company 1 · failed 0')
        ->assertExitCode(0);
});
