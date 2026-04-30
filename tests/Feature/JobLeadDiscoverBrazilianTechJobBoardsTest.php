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
        ->expectsOutput('Fetched: 8')
        ->expectsOutput('Created: 5')
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
        'programathor' => 3,
        'remotar' => 2,
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
        ->expectsOutput('Fetched: 8')
        ->expectsOutput('Created: 0')
        ->expectsOutput('Duplicates skipped: 5')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(5);
});

it('exposes per-platform diagnostics for the brazilian tech job boards source', function (): void {
    useBrazilianTechJobBoardFixtures();

    $user = User::factory()->create();

    $summary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $user->id,
        source: 'brazilian-tech-job-boards',
        discoveryBatchId: 'batch-brazil-tech-boards',
    );

    expect($summary['created'])->toBe(5)
        ->and($summary['duplicates'])->toBe(0)
        ->and($summary['target_diagnostics'])->toHaveCount(2)
        ->and($summary['target_diagnostics'][0]['target_name'])->toBe('ProgramaThor')
        ->and($summary['target_diagnostics'][0]['platform'])->toBe('programathor')
        ->and($summary['target_diagnostics'][0]['fetched_candidates'])->toBe(5)
        ->and($summary['target_diagnostics'][0]['matched_candidates'])->toBe(3)
        ->and($summary['target_diagnostics'][0]['imported'])->toBe(3)
        ->and($summary['target_diagnostics'][0]['deduplicated'])->toBe(0)
        ->and($summary['target_diagnostics'][0]['skipped_by_query'])->toBe(0)
        ->and($summary['target_diagnostics'][0]['skipped_expired'])->toBe(1)
        ->and($summary['target_diagnostics'][0]['skipped_missing_company'])->toBe(1)
        ->and($summary['target_diagnostics'][0]['failed'])->toBe(0)
        ->and($summary['target_diagnostics'][1]['target_name'])->toBe('Remotar')
        ->and($summary['target_diagnostics'][1]['platform'])->toBe('remotar')
        ->and($summary['target_diagnostics'][1]['fetched_candidates'])->toBe(3)
        ->and($summary['target_diagnostics'][1]['matched_candidates'])->toBe(2)
        ->and($summary['target_diagnostics'][1]['imported'])->toBe(2)
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
            ->where('flash.discovery.0.created', 2)
            ->where('flash.discovery.0.created_by_query_profiles', 2)
            ->where('flash.discovery.0.query_profile_keys', ['frontend_vue'])
            ->where('flash.discovery.0.target_diagnostics.0.target_name', 'Remotar')
            ->where('flash.discovery.0.target_diagnostics.0.platform', 'remotar')
            ->where('flash.discovery.0.target_diagnostics.0.fetched_candidates', 3)
            ->where('flash.discovery.0.target_diagnostics.0.skipped_expired', 1)
        );

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(JobLead::query()->where('user_id', $user->id)->where('job_title', 'Vue.js Product Engineer')->exists())->toBeTrue()
        ->and(JobLead::query()->where('user_id', $user->id)->where('source_platform', 'remotar')->count())->toBe(2);
});

it('imports a qa python programathor lead with full detail text and matches python without false javascript', function (): void {
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.supported_sources', ['brazilian-tech-job-boards']);
    config()->set('job_discovery.brazilian_tech_job_board_targets', [[
        'platform' => 'programathor',
        'name' => 'ProgramaThor QA',
        'parser_strategy' => 'programathor_cards',
        'listing_urls' => [
            'https://fixtures.programathor.com.br/jobs/qa-python',
        ],
    ]]);

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Python QA engineer with Pytest, Playwright, REST APIs, GitHub Actions, AWS, and microservices experience.',
        'core_skills' => ['Python', 'QA', 'Pytest', 'Playwright', 'AWS'],
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'brazilian-tech-job-boards',
    ])->assertExitCode(0);

    $lead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://programathor.com.br/jobs/9001-chronos-cap-qa-python')
        ->sole();

    expect(strlen((string) $lead->description_text))->toBeGreaterThan(400)
        ->and($lead->ats_hints[0] ?? null)->not->toBe('The description looks short. Add the full posting before tailoring your resume.')
        ->and($lead->description_text)->toContain('RESTful APIs com Postman')
        ->and($lead->extracted_keywords)->toContain('python')
        ->and($lead->extracted_keywords)->toContain('qa')
        ->and($lead->extracted_keywords)->toContain('pytest')
        ->and($lead->extracted_keywords)->toContain('selenium')
        ->and($lead->extracted_keywords)->toContain('playwright')
        ->and($lead->extracted_keywords)->toContain('rest_api')
        ->and($lead->extracted_keywords)->not->toContain('javascript');

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.company_name', 'Chronos Cap')
            ->where('matchedJobs.0.job_title', 'Engenheiro(a) de QA - Python - Pleno')
            ->where('matchedJobs.0.matched_keywords', fn ($keywords): bool => $keywords->contains('python') && ! $keywords->contains('javascript'))
            ->where('matchedJobs.0.missing_keywords', fn ($keywords): bool => ! $keywords->contains('python'))
        );
});
