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
        ->toContain('larajobs')
        ->toContain('company-career-pages');
});
