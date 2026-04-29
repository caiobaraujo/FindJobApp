<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use Inertia\Testing\AssertableInertia as Assert;

function useBrazilianTechJobBoardFixtures(): void
{
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.supported_sources', ['brazilian-tech-job-boards']);
    config()->set('job_discovery.brazilian_tech_job_board_targets', config('job_discovery.fixture_brazilian_tech_job_board_targets'));
}

it('imports deterministic leads from the curated brazilian tech job board fixtures', function (): void {
    useBrazilianTechJobBoardFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'brazilian-tech-job-boards',
    ])
        ->expectsOutput('Fetched: 6')
        ->expectsOutput('Created: 3')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $countsByPlatform = JobLead::query()
        ->where('user_id', $user->id)
        ->selectRaw('source_platform, count(*) as aggregate_count')
        ->groupBy('source_platform')
        ->pluck('aggregate_count', 'source_platform')
        ->all();

    expect($countsByPlatform)->toBe([
        'programathor' => 2,
        'remotar' => 1,
    ]);

    $lead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://programathor.com.br/jobs/7010-acme-backend-laravel')
        ->sole();

    expect($lead->company_name)->toBe('Acme Sistemas')
        ->and($lead->job_title)->toBe('Pessoa Desenvolvedora PHP Laravel')
        ->and($lead->location)->toBe('Belo Horizonte, Brasil')
        ->and($lead->work_mode)->toBe(JobLead::WORK_MODE_REMOTE)
        ->and($lead->source_platform)->toBe('programathor')
        ->and($lead->extracted_keywords)->toContain('php')
        ->and($lead->extracted_keywords)->toContain('laravel');
});

it('deduplicates deterministic brazilian tech job board leads across repeated runs', function (): void {
    useBrazilianTechJobBoardFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'brazilian-tech-job-boards',
    ])->assertExitCode(0);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'brazilian-tech-job-boards',
    ])
        ->expectsOutput('Fetched: 6')
        ->expectsOutput('Created: 0')
        ->expectsOutput('Duplicates skipped: 3')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('exposes per-platform diagnostics for the brazilian tech job boards source', function (): void {
    useBrazilianTechJobBoardFixtures();

    $user = User::factory()->create();

    $summary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $user->id,
        source: 'brazilian-tech-job-boards',
        discoveryBatchId: 'batch-brazil-tech-boards',
    );

    expect($summary['created'])->toBe(3)
        ->and($summary['duplicates'])->toBe(0)
        ->and($summary['target_diagnostics'])->toHaveCount(2)
        ->and($summary['target_diagnostics'][0]['target_name'])->toBe('ProgramaThor')
        ->and($summary['target_diagnostics'][0]['platform'])->toBe('programathor')
        ->and($summary['target_diagnostics'][0]['fetched_candidates'])->toBe(4)
        ->and($summary['target_diagnostics'][0]['matched_candidates'])->toBe(2)
        ->and($summary['target_diagnostics'][0]['imported'])->toBe(2)
        ->and($summary['target_diagnostics'][0]['deduplicated'])->toBe(0)
        ->and($summary['target_diagnostics'][0]['skipped_by_query'])->toBe(0)
        ->and($summary['target_diagnostics'][0]['skipped_expired'])->toBe(1)
        ->and($summary['target_diagnostics'][0]['skipped_missing_company'])->toBe(1)
        ->and($summary['target_diagnostics'][0]['failed'])->toBe(0)
        ->and($summary['target_diagnostics'][1]['target_name'])->toBe('Remotar')
        ->and($summary['target_diagnostics'][1]['platform'])->toBe('remotar')
        ->and($summary['target_diagnostics'][1]['fetched_candidates'])->toBe(2)
        ->and($summary['target_diagnostics'][1]['matched_candidates'])->toBe(1)
        ->and($summary['target_diagnostics'][1]['imported'])->toBe(1)
        ->and($summary['target_diagnostics'][1]['deduplicated'])->toBe(0)
        ->and($summary['target_diagnostics'][1]['skipped_by_query'])->toBe(0)
        ->and($summary['target_diagnostics'][1]['skipped_expired'])->toBe(1)
        ->and($summary['target_diagnostics'][1]['skipped_missing_company'])->toBe(0)
        ->and($summary['target_diagnostics'][1]['failed'])->toBe(0);
});

it('uses resume-derived query profiles with the brazilian tech job boards source', function (): void {
    useBrazilianTechJobBoardFixtures();
    config()->set('job_discovery.brazilian_tech_job_board_targets', [
        config('job_discovery.fixture_brazilian_tech_job_board_targets')[1],
    ]);

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Vue.js engineer with JavaScript SPA product experience.',
        'core_skills' => ['Vue.js'],
        'auto_discover_jobs' => false,
    ]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('job-leads.discover'), [
            'search_query' => 'frontend',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('flash.discovery.0.source', 'brazilian-tech-job-boards')
            ->where('flash.discovery.0.created', 1)
            ->where('flash.discovery.0.created_by_query_profiles', 1)
            ->where('flash.discovery.0.query_profile_keys', ['frontend_vue'])
            ->where('flash.discovery.0.target_diagnostics.0.target_name', 'Remotar')
            ->where('flash.discovery.0.target_diagnostics.0.platform', 'remotar')
            ->where('flash.discovery.0.target_diagnostics.0.skipped_expired', 1)
        );

    $lead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($lead->job_title)->toBe('Vue.js Product Engineer')
        ->and($lead->source_platform)->toBe('remotar');
});
