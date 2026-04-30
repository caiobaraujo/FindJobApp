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
        ->expectsOutput('Candidate links found: 19')
        ->expectsOutput('Parsed jobs after filtering: 17')
        ->expectsOutput('Normalized query: python')
        ->expectsOutput('Target Afya (gupy): fetched 3 · matched 1 · query-skipped 1 · expired 1 · missing company 0 · failed 0 · detail ok 2 · detail failed 0')
        ->expectsOutput('Target CIGAM (gupy): fetched 1 · matched 0 · query-skipped 1 · expired 0 · missing company 0 · failed 0 · detail ok 1 · detail failed 0')
        ->expectsOutput('Target Gran (gupy): fetched 1 · matched 1 · query-skipped 0 · expired 0 · missing company 0 · failed 0 · detail ok 1 · detail failed 0')
        ->expectsOutput('Target JBS (gupy): fetched 1 · matched 0 · query-skipped 1 · expired 0 · missing company 0 · failed 0 · detail ok 1 · detail failed 0')
        ->expectsOutputToContain('Target Gupy Tech (gupy): fetched 1')
        ->expectsOutputToContain('Target Pmweb (gupy): fetched 1')
        ->expectsOutputToContain('Target Grupo Autoglass Administrativo (gupy): fetched 1')
        ->expectsOutputToContain('Target Asaas (gupy): fetched 1')
        ->expectsOutputToContain('Target Atento TI (gupy): fetched 1')
        ->expectsOutputToContain('Target Zenvia (gupy): fetched 1')
        ->expectsOutput('Target Positivo Tecnologia (gupy): fetched 1 · matched 1 · query-skipped 0 · expired 0 · missing company 0 · failed 0 · detail ok 1 · detail failed 0')
        ->expectsOutput('Target https://mystery.gupy.io/ (gupy): fetched 1 · matched 0 · query-skipped 0 · expired 0 · missing company 1 · failed 0')
        ->assertExitCode(0);
});
