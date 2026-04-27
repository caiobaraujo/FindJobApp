<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('filters the saved job workspace by lead status', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Saved Lead Co',
        'lead_status' => JobLead::STATUS_SAVED,
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Ignored Lead Co',
        'lead_status' => JobLead::STATUS_IGNORED,
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

    JobLead::factory()->for($user)->create([
        'company_name' => 'Matching Saved Lead',
        'lead_status' => JobLead::STATUS_SAVED,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Non Matching Saved Lead',
        'lead_status' => JobLead::STATUS_SAVED,
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
