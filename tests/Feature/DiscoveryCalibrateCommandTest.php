<?php

use App\Models\JobLead;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('app/discovery-diagnostics'));
});

it('creates a dry-run calibration report without creating job leads', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs']);

    Http::fake([
        'https://larajobs.com/' => Http::response(
            File::get(base_path('tests/Fixtures/larajobs_listing.html')),
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $diagnosticUser = User::factory()->create([
        'email' => 'discovery-diagnostics@example.com',
    ]);

    JobLead::factory()->for($diagnosticUser)->saved()->create([
        'source_url' => 'https://larajobs.com/jobs/acme-senior-laravel-engineer',
        'normalized_source_url' => 'https://larajobs.com/jobs/acme-senior-laravel-engineer',
        'source_host' => 'larajobs.com',
        'source_name' => 'LaraJobs',
        'job_title' => 'Senior Laravel Engineer',
        'company_name' => 'Acme Labs',
    ]);

    $jobLeadCountBefore = JobLead::query()->count();
    $reportPath = storage_path('app/discovery-diagnostics/calibration-laravel.md');

    $this->artisan('discovery:calibrate laravel')
        ->expectsOutputToContain('Source: larajobs')
        ->expectsOutputToContain('Senior Laravel Engineer')
        ->expectsOutputToContain('reason: no match with query terms: laravel')
        ->expectsOutputToContain($reportPath)
        ->assertSuccessful();

    expect(JobLead::query()->count())->toBe($jobLeadCountBefore)
        ->and(File::exists($reportPath))->toBeTrue();

    $report = File::get($reportPath);

    expect($report)->toContain('# Discovery Calibration')
        ->and($report)->toContain('## Source: larajobs')
        ->and($report)->toContain('Senior Laravel Engineer')
        ->and($report)->toContain('duplicate for diagnostic user')
        ->and($report)->toContain('no match with query terms: laravel');
});

it('writes rejection reasons when a source fails to fetch', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs']);

    Http::fake([
        'https://larajobs.com/' => Http::response('Upstream error', 500),
    ]);

    $reportPath = storage_path('app/discovery-diagnostics/calibration-javascript.md');

    $this->artisan('discovery:calibrate javascript')
        ->expectsOutputToContain('Failed: Failed to fetch the LaraJobs listing page (HTTP 500).')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('Failed: Failed to fetch the LaraJobs listing page (HTTP 500).');
});
