<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('shows job lead match analysis when prerequisites exist', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'core_skills' => ['Laravel', 'Vue'],
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel', 'vue', 'aws'],
        'ats_hints' => ['Hint'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertSee('laravel')
        ->assertSee('vue')
        ->assertSee('aws')
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Edit')
            ->where('matchAnalysis.state', 'ready')
            ->where('matchAnalysis.match_summary', 'Matched 2 keywords and missing 1.')
            ->where('matchAnalysis.matched_keywords.0', 'laravel')
            ->where('matchAnalysis.matched_keywords.1', 'vue')
            ->where('matchAnalysis.missing_keywords.0', 'aws')
        );
});

it('shows an empty state when no resume profile exists', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create([
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Edit')
            ->where('matchAnalysis.state', 'missing_profile')
            ->where('matchAnalysis.match_summary', 'Create your resume profile to compare your background against this job lead.')
        );
});

it('shows an empty state when job analysis prerequisites are missing', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'core_skills' => ['Laravel'],
        'base_resume_text' => 'Laravel engineer',
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Edit')
            ->where('matchAnalysis.state', 'missing_job_analysis')
            ->where('matchAnalysis.match_summary', 'Add a full job description to generate keywords before matching against your resume profile.')
        );
});

it('recomputes job lead match analysis from the latest persisted profile data', function (): void {
    $user = User::factory()->create();

    $profile = UserProfile::query()->create([
        'user_id' => $user->id,
        'core_skills' => ['Laravel'],
        'base_resume_text' => 'Laravel engineer',
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'description_text' => 'Full description',
        'extracted_keywords' => ['python', 'sql'],
        'ats_hints' => ['Hint'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Edit')
            ->where('matchAnalysis.matched_keywords', [])
            ->where('matchAnalysis.missing_keywords.0', 'python')
            ->where('matchAnalysis.missing_keywords.1', 'sql')
        );

    $profile->update([
        'base_resume_text' => 'Python engineer with SQL experience.',
        'core_skills' => ['Python', 'SQL'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Edit')
            ->where('matchAnalysis.matched_keywords.0', 'python')
            ->where('matchAnalysis.matched_keywords.1', 'sql')
            ->where('matchAnalysis.missing_keywords', [])
        );
});
