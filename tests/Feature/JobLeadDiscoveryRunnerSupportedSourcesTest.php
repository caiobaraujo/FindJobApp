<?php

use App\Services\JobDiscovery\JobLeadDiscoveryRunner;

it('does not include remotive in the default supported sources list', function (): void {
    config()->set('job_discovery.use_fixture_responses', false);
    config()->set('job_discovery.supported_sources', [
        'python-job-board',
        'django-community-jobs',
        'we-work-remotely',
        'larajobs',
        'company-career-pages',
    ]);

    expect(app(JobLeadDiscoveryRunner::class)->supportedSources())
        ->not->toContain('remotive')
        ->not->toContain('brazilian-tech-job-boards')
        ->not->toContain('gupy-public-jobs')
        ->toContain('larajobs')
        ->toContain('company-career-pages');
});

it('includes brazilian tech job boards when explicitly enabled in local discovery config', function (): void {
    config()->set('job_discovery.use_fixture_responses', false);
    config()->set('job_discovery.supported_sources', [
        'python-job-board',
        'django-community-jobs',
        'we-work-remotely',
        'larajobs',
        'company-career-pages',
        'brazilian-tech-job-boards',
        'gupy-public-jobs',
    ]);

    expect(app(JobLeadDiscoveryRunner::class)->supportedSources())
        ->not->toContain('remotive')
        ->toContain('brazilian-tech-job-boards')
        ->toContain('gupy-public-jobs')
        ->toContain('larajobs')
        ->toContain('company-career-pages');
});
