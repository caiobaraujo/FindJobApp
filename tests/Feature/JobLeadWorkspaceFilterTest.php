<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('hides ignored leads by default in the saved job workspace', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Saved Lead',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Hidden Ignored Lead',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.show_ignored', false)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Visible Saved Lead')
        )
        ->assertDontSee('Hidden Ignored Lead');
});

it('keeps url only leads visible in the saved job workspace and marks them as limited analysis', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'URL Only Lead Co',
        'job_title' => 'Imported job',
        'source_url' => 'https://example.com/jobs/url-only',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'URL Only Lead Co')
            ->where('matchedJobs.0.source_url', 'https://example.com/jobs/url-only')
            ->where('matchedJobs.0.has_limited_analysis', true)
        );
});

it('does not mark analyzed leads as limited analysis', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Analyzed Lead Co',
        'job_title' => 'Laravel Engineer',
        'description_text' => 'We need a Laravel engineer with Vue and SQL experience.',
        'extracted_keywords' => ['laravel', 'vue', 'sql'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Analyzed Lead Co')
            ->where('matchedJobs.0.has_limited_analysis', false)
        );
});

it('shows ignored leads when explicitly requested in the saved job workspace', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Saved Lead',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Visible Ignored Lead',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['show_ignored' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.show_ignored', true)
            ->has('matchedJobs', 2)
        )
        ->assertSee('Visible Ignored Lead');
});

it('filters the saved job workspace by lead status', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Saved Lead Co',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Ignored Lead Co',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['lead_status' => JobLead::STATUS_IGNORED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.lead_status', JobLead::STATUS_IGNORED)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Ignored Lead Co')
        );
});

it('returns lead status counters scoped to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create();

    JobLead::factory()->for($user)->shortlisted()->create();

    JobLead::factory()->for($user)->applied()->create();

    JobLead::factory()->for($user)->ignored()->create();

    JobLead::factory()->for($otherUser)->ignored()->create();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('leadStatusCounts.active', 2)
            ->where('leadStatusCounts.ignored', 1)
            ->where('leadStatusCounts.applied', 1)
        );
});

it('filters the saved job workspace by analysis state', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Analyzed Lead Co',
        'description_text' => 'Full job description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Missing Analysis Co',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['analysis_state' => JobLead::ANALYSIS_STATE_ANALYZED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.analysis_state', JobLead::ANALYSIS_STATE_ANALYZED)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Analyzed Lead Co')
        );
});

it('filters the saved job workspace by work mode and keeps user scoping intact', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Remote Lead Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Hybrid Lead Co',
        'work_mode' => JobLead::WORK_MODE_HYBRID,
    ]);

    JobLead::factory()->for($otherUser)->create([
        'company_name' => 'Other User Remote Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['work_mode' => JobLead::WORK_MODE_REMOTE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.work_mode', JobLead::WORK_MODE_REMOTE)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Remote Lead Co')
        )
        ->assertDontSee('Other User Remote Co');
});

it('keeps matched only mode active when lead status filters are applied', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue experience.',
        'core_skills' => ['Laravel', 'Vue'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Matching Saved Lead',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Non Matching Saved Lead',
        'description_text' => 'Full description',
        'extracted_keywords' => ['python'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index', ['lead_status' => JobLead::STATUS_SAVED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.lead_status', JobLead::STATUS_SAVED)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Matching Saved Lead')
        )
        ->assertDontSee('Non Matching Saved Lead');
});
