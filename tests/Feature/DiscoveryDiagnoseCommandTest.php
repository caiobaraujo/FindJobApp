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
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->expectsOutputToContain($reportPath)
        ->assertSuccessful();

    expect(File::exists($reportPath))->toBeTrue();

    $report = File::get($reportPath);

    expect($report)->toContain('# Discovery Diagnostics')
        ->and($report)->toContain('## Scenario Summary')
        ->and($report)->toContain('## Source Performance')
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
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('Search "python" created 0 leads.')
        ->and($report)->toContain('hidden from the default Brazil workspace');
});

it('reports source failures in the markdown output', function (): void {
    config()->set('job_discovery.supported_sources', ['larajobs', 'python-job-board']);
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
