<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use Inertia\Testing\AssertableInertia as Assert;

function useGupyPublicJobFixtures(): void
{
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.supported_sources', ['gupy-public-jobs']);
    config()->set('job_discovery.gupy_public_job_targets', config('job_discovery.fixture_gupy_public_job_targets'));
}

it('imports deterministic leads from curated gupy public job fixtures', function (): void {
    useGupyPublicJobFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'gupy-public-jobs',
    ])
        ->expectsOutput('Fetched: 9')
        ->expectsOutput('Created: 7')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $countsByCompany = JobLead::query()
        ->where('user_id', $user->id)
        ->selectRaw('company_name, count(*) as aggregate_count')
        ->groupBy('company_name')
        ->pluck('aggregate_count', 'company_name')
        ->all();

    expect($countsByCompany)->toBe([
        'Afya' => 2,
        'FCamara' => 1,
        'Gran' => 1,
        'Minsait' => 1,
        'Omie' => 1,
        'Positivo Tecnologia' => 1,
    ]);
});

it('deduplicates deterministic gupy public leads across repeated runs', function (): void {
    useGupyPublicJobFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'gupy-public-jobs',
    ])->assertExitCode(0);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'gupy-public-jobs',
    ])
        ->expectsOutput('Fetched: 9')
        ->expectsOutput('Created: 0')
        ->expectsOutput('Duplicates skipped: 7')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(7);
});

it('exposes per-company diagnostics for the gupy public jobs source', function (): void {
    useGupyPublicJobFixtures();

    $user = User::factory()->create();

    $summary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $user->id,
        source: 'gupy-public-jobs',
        discoveryBatchId: 'batch-gupy-public-jobs',
    );

    expect($summary['created'])->toBe(7)
        ->and($summary['duplicates'])->toBe(0)
        ->and($summary['target_diagnostics'])->toHaveCount(7)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Afya')['fetched_candidates'])->toBe(3)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Afya')['matched_candidates'])->toBe(2)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Afya')['imported'])->toBe(2)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Afya')['skipped_expired'])->toBe(1)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Afya')['detail_enrichment_succeeded'])->toBe(2)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Afya')['detail_enrichment_failed'])->toBe(0)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Omie')['imported'])->toBe(1)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Minsait')['imported'])->toBe(1)
        ->and(collect($summary['target_diagnostics'])->firstWhere('target_name', 'Positivo Tecnologia')['imported'])->toBe(1);

    $mystery = collect($summary['target_diagnostics'])
        ->firstWhere('target_identifier', 'https://mystery.gupy.io/');

    expect($mystery)->not->toBeNull()
        ->and($mystery['fetched_candidates'])->toBe(1)
        ->and($mystery['matched_candidates'])->toBe(0)
        ->and($mystery['imported'])->toBe(0)
        ->and($mystery['skipped_missing_company'])->toBe(1);
});

it('supports deterministic query filtering for gupy public jobs', function (): void {
    useGupyPublicJobFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'gupy-public-jobs',
        '--query' => 'python',
    ])
        ->expectsOutput('Fetched: 9')
        ->expectsOutput('Created: 3')
        ->expectsOutput('Skipped not matching query: 4')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->pluck('company_name')->all())
        ->toBe(['Afya', 'Gran', 'Positivo Tecnologia']);
});

it('supports javascript frontend backend and remoto queries for gupy public jobs', function (): void {
    useGupyPublicJobFixtures();

    $javascriptUser = User::factory()->create();
    $frontendUser = User::factory()->create();
    $backendUser = User::factory()->create();
    $remotoUser = User::factory()->create();

    $javascriptSummary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $javascriptUser->id,
        source: 'gupy-public-jobs',
        searchQuery: 'javascript',
        discoveryBatchId: 'batch-gupy-javascript',
    );

    $frontendSummary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $frontendUser->id,
        source: 'gupy-public-jobs',
        searchQuery: 'frontend',
        discoveryBatchId: 'batch-gupy-frontend',
    );

    $backendSummary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $backendUser->id,
        source: 'gupy-public-jobs',
        searchQuery: 'backend',
        discoveryBatchId: 'batch-gupy-backend',
    );

    $remotoSummary = app(JobLeadDiscoveryRunner::class)->discoverForUser(
        userId: $remotoUser->id,
        source: 'gupy-public-jobs',
        searchQuery: 'remoto',
        discoveryBatchId: 'batch-gupy-remoto',
    );

    expect($javascriptSummary['created'])->toBe(1)
        ->and($frontendSummary['created'])->toBe(1)
        ->and($backendSummary['created'])->toBe(3)
        ->and($remotoSummary['created'])->toBe(5);
});

it('uses resume-derived query profiles with gupy public jobs during discovery', function (): void {
    useGupyPublicJobFixtures();
    config()->set('job_discovery.gupy_public_job_targets', [
        config('job_discovery.fixture_gupy_public_job_targets')[0],
    ]);

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Python Django backend engineer with REST APIs and PostgreSQL experience.',
        'core_skills' => ['Python', 'Django'],
        'auto_discover_jobs' => false,
    ]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('job-leads.discover'), [
            'search_query' => 'backend',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('flash.discovery.0.source', 'gupy-public-jobs')
            ->where('flash.discovery.0.created', 1)
            ->where('flash.discovery.0.created_by_query_profiles', 1)
            ->where('flash.discovery.0.query_profile_keys', ['backend_python'])
        );

    $lead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($lead->company_name)->toBe('Afya')
        ->and($lead->job_title)->toBe('Pessoa Engenheira Python Django');
});
