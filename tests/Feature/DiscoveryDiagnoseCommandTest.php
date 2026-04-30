<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    File::delete(storage_path('app/discovery-diagnostics/latest.md'));
    File::deleteDirectory(storage_path('app/discovery-diagnostics'));
});

it('creates a brazil calibration report with deterministic fixture scenarios', function (): void {
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture --brazil')
        ->expectsOutputToContain($reportPath)
        ->assertSuccessful();

    expect(File::exists($reportPath))->toBeTrue();

    $report = File::get($reportPath);

    expect($report)->toContain('# Discovery Diagnostics')
        ->and($report)->toContain('- Mode: brazil calibration')
        ->and($report)->toContain('## Scenario Summary')
        ->and($report)->toContain('## Source Performance')
        ->and($report)->toContain('## Target Performance')
        ->and($report)->toContain('## Target Recommendations')
        ->and($report)->toContain('| Search | Fetched | Parsed | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Brazil | International | Hidden by default | Limited analysis | Missing description | Missing keywords |')
        ->and($report)->toContain('| Source | Fetched | Parsed | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Hidden by default | Limited analysis | Missing description | Missing keywords |')
        ->and($report)->toContain('| Source | Target | Platform | Parser | Bucket | Recommendation | Fetched | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Import rate | Query skip rate |')
        ->and($report)->toContain('### no query')
        ->and($report)->toContain('### python')
        ->and($report)->toContain('### javascript')
        ->and($report)->toContain('### frontend')
        ->and($report)->toContain('### backend')
        ->and($report)->toContain('### remoto')
        ->and($report)->toContain('### data')
        ->and($report)->toContain('### devops');
});

it('includes brazil first source totals and target diagnostics in fixture mode', function (): void {
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture --brazil')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('| company-career-pages |')
        ->and($report)->toContain('| brazilian-tech-job-boards |')
        ->and($report)->toContain('| gupy-public-jobs |')
        ->and($report)->toContain('| company-career-pages | Nubank |')
        ->and($report)->toContain('| brazilian-tech-job-boards | ProgramaThor | programathor | programathor_cards |')
        ->and($report)->toContain('| brazilian-tech-job-boards | Remotar | remotar | remotar_cards |')
        ->and($report)->toContain('| gupy-public-jobs | Afya | gupy | gupy_listing |')
        ->and($report)->toContain('| gupy-public-jobs | Gran | gupy | gupy_listing |')
        ->and($report)->toContain('| gupy-public-jobs | https://mystery.gupy.io/ | gupy | gupy_listing |');
});

it('preserves gupy skipped missing company and expired counts in the calibration report', function (): void {
    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture --brazil')
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect((int) preg_match('/gupy-public-jobs \\\| Afya \\\| gupy \\\| gupy_listing \\\| weak \\\| deprioritize \\\| 30 \\\| 5 \\\| 2 \\\| 3 \\\| 15 \\\| 0 \\\| 10 \\\| 0 \\\|/', $report))
        ->toBe(1)
        ->and((int) preg_match('/gupy-public-jobs \\\| https:\/\/mystery\.gupy\.io\/ \\\| gupy \\\| gupy_listing \\\| no-signal \\\| investigate \\\| 10 \\\| 0 \\\| 0 \\\| 0 \\\| 0 \\\| 10 \\\| 0 \\\| 0 \\\|/', $report))
        ->toBe(1);
});

it('supports deterministic brazil query selection without network access in fixture mode', function (): void {
    Http::preventStrayRequests();

    $reportPath = storage_path('app/discovery-diagnostics/latest.md');

    $this->artisan('discovery:diagnose --fresh --fixture --brazil --query=python --query=frontend')
        ->expectsOutputToContain($reportPath)
        ->assertSuccessful();

    $report = File::get($reportPath);

    expect($report)->toContain('### python')
        ->and($report)->toContain('### frontend')
        ->and($report)->not->toContain('### javascript')
        ->and($report)->toContain('| python |')
        ->and($report)->toContain('| frontend |');
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
