<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a resume first cta on the dashboard when no resume exists', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hasResumeProfile', false)
            ->where('resumeReady', false)
            ->where('matchedJobsCount', 0)
        );
});

it('renders matched jobs with matched and missing keywords when resume data exists', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Senior Laravel Engineer',
        'source_url' => 'https://example.com/jobs/northwind',
        'extracted_keywords' => ['laravel', 'vue', 'aws'],
        'ats_hints' => ['Mirror the strongest stack terms in your resume bullets.'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Hidden Co',
        'job_title' => 'Python Role',
        'source_url' => '',
        'extracted_keywords' => ['python'],
        'ats_hints' => ['This should not match the resume.'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('hasResumeProfile', true)
            ->where('resumeReady', true)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Northwind')
            ->where('matchedJobs.0.matched_keywords.0', 'laravel')
            ->where('matchedJobs.0.matched_keywords.1', 'vue')
            ->where('matchedJobs.0.missing_keywords.0', 'aws')
            ->where('matchedJobs.0.source_url', 'https://example.com/jobs/northwind')
        )
        ->assertDontSee('Hidden Co');
});

it('keeps jobs without a source url from exposing a go to job button payload', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Python developer with API experience.',
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'No Link Co',
        'job_title' => 'Python Developer',
        'source_url' => '',
        'extracted_keywords' => ['python', 'api'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.source_url', '')
        );
});
