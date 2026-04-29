<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::delete(storage_path('app/discovery-diagnostics/latest.md'));
    File::deleteDirectory(storage_path('app/discovery-diagnostics'));
});

it('creates a markdown diagnostics report with all predefined scenarios', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs']);
    config()->set('job_discovery.fixture_supported_sources', ['larajobs']);
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->expectsOutputToContain($reportPath)
        ->assertSuccessful();

    expect(File::exists($reportPath))->toBeTrue();

    $report = File::get($reportPath);

    expect($report)->toContain('# Discovery Diagnostics')
        ->and($report)->toContain('## Scenario Summary')
        ->and($report)->toContain('## Source Performance')
        ->and($report)->toContain('Imported | Deduplicated')
        ->and($report)->toContain('Visible by default | Hidden by default | Ready analysis | Limited analysis')
        ->and($report)->toContain('Batch observability:')
        ->and($report)->toContain('Imported vs deduplicated:')
        ->and($report)->toContain('Hidden by default filters:')
        ->and($report)->toContain('Analysis coverage:')
        ->and($report)->toContain('## Warnings')
        ->and($report)->toContain('## Recommendations');

    foreach ([
        'laravel',
        'php',
        'javascript',
        'vue',
        'python',
        'django',
        'docker',
        'laravel remoto',
        'javascript belo horizonte',
        'php laravel remoto brasil',
    ] as $scenario) {
        expect($report)->toContain($scenario);
    }
});

it('adds warnings for zero-result searches and hidden-by-default leads', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs']);
    config()->set('job_discovery.fixture_supported_sources', ['larajobs']);
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('Search "python" created 0 leads.')
        ->and($report)->toContain('hidden from the default Brazil workspace');
});

it('includes zero-safe observability details for empty scenarios', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs']);
    config()->set('job_discovery.fixture_supported_sources', ['larajobs']);
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('### python')
        ->and($report)->toContain('Imported vs deduplicated: 0 imported, 0 deduplicated')
        ->and($report)->toContain('Analysis coverage: 0 ready, 0 limited, 0 missing description, 0 missing keywords');
});

it('reports company career page usefulness per curated target', function (): void {
    config()->set('job_discovery.supported_sources', ['company-career-pages']);
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('## Company Career Page Target Performance')
        ->and($report)->toContain('| Target | Bucket | Action | Fetched | Matched | Imported | Deduplicated | Skipped | Hidden by default | International hidden | Query skip rate | Import rate |')
        ->and($report)->toContain('Nubank')
        ->and($report)->toContain('Stone')
        ->and($report)->toContain('PagBank')
        ->and($report)->toContain('QuintoAndar')
        ->and($report)->toContain('VTEX')
        ->and($report)->toContain('Magazine Luiza')
        ->and($report)->toContain('strong')
        ->and($report)->toContain('promising')
        ->and($report)->toContain('no-signal')
        ->and($report)->toContain('keep strong targets')
        ->and($report)->toContain('review promising targets')
        ->and($report)->toContain('## Company Career Page Target Recommendations')
        ->and($report)->toContain('Keep strong targets:')
        ->and($report)->toContain('Review promising targets:')
        ->and($report)->toContain('Investigate no-signal targets:')
        ->and($report)->toContain('Company career page targets:')
        ->and($report)->toContain('100.0%')
        ->and($report)->toContain('0.0%');
});

it('reports source failures in the markdown output', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs', 'python-job-board']);
    config()->set('job_discovery.fixture_supported_sources', ['larajobs', 'python-job-board']);
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    Http::fake([
        'https://www.python.org/jobs/' => Http::response('Upstream error', 500),
    ]);

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('Source python-job-board failed')
        ->and($report)->toContain('inspect the source URL or parser');
});

it('does not delete real user leads when fresh is requested with an explicit user id', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs']);

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel PHP Vue JavaScript',
        'auto_discover_jobs' => false,
    ]);

    $existingLead = JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Existing Real Lead',
    ]);

    $this->artisan(sprintf('discovery:diagnose %d --fresh --fixture', $user->id))
        ->expectsOutputToContain('--fresh is ignored when a user_id is provided.')
        ->assertSuccessful();

    expect(JobLead::query()->whereKey($existingLead->id)->exists())->toBeTrue();
});
