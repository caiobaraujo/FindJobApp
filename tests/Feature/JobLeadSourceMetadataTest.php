<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('refreshes canonical source metadata when a job lead source url changes', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://www.example.com/jobs/platform-engineer?ref=feed',
        'normalized_source_url' => 'https://example.com/jobs/platform-engineer',
        'source_host' => 'example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => 'Northwind',
            'job_title' => 'Platform Engineer',
            'source_name' => 'Company Site',
            'source_url' => 'https://careers.example.org/roles/platform-engineer/?utm_source=newsletter#apply',
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => $jobLead->description_text,
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead->refresh();

    expect($jobLead->normalized_source_url)->toBe('https://careers.example.org/roles/platform-engineer')
        ->and($jobLead->source_host)->toBe('careers.example.org');
});

it('includes source metadata in matched job cards', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue experience.',
        'core_skills' => ['Laravel', 'Vue'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Northwind',
        'job_title' => 'Senior Laravel Engineer',
        'source_name' => 'Company Site',
        'source_type' => JobLead::SOURCE_TYPE_POST,
        'source_platform' => 'linkedin',
        'source_post_url' => 'https://www.linkedin.com/posts/example-hiring-post',
        'source_author' => 'Northwind Recruiter',
        'source_url' => 'https://careers.example.com/jobs/laravel-engineer?ref=feed',
        'normalized_source_url' => 'https://careers.example.com/jobs/laravel-engineer',
        'source_host' => 'careers.example.com',
        'extracted_keywords' => ['laravel', 'vue'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertSee('Company Site')
        ->assertSee('careers.example.com')
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.source_name', 'Company Site')
            ->where('matchedJobs.0.source_type', JobLead::SOURCE_TYPE_POST)
            ->where('matchedJobs.0.source_platform', 'linkedin')
            ->where('matchedJobs.0.source_post_url', 'https://www.linkedin.com/posts/example-hiring-post')
            ->where('matchedJobs.0.source_author', 'Northwind Recruiter')
            ->where('matchedJobs.0.source_host', 'careers.example.com')
            ->where('matchedJobs.0.source_url', 'https://careers.example.com/jobs/laravel-engineer?ref=feed')
        );
});
