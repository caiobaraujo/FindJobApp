<?php

use App\Models\JobLead;
use App\Models\User;

it('creates a post based job lead from source post text', function (): void {
    $user = User::factory()->create();

    $this->artisan('job-leads:import-post', [
        'user_id' => $user->id,
        '--platform' => 'linkedin',
        '--post-url' => 'https://www.linkedin.com/posts/hiring-post-123',
        '--text' => 'We are hiring a Laravel and Vue engineer for a remote team. Apply through our careers page soon.',
    ])
        ->expectsOutputToContain('Created JobLead #')
        ->expectsOutput('Source post: https://www.linkedin.com/posts/hiring-post-123')
        ->expectsOutput('Job URL: ')
        ->assertExitCode(0);

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->source_type)->toBe(JobLead::SOURCE_TYPE_POST)
        ->and($jobLead->source_platform)->toBe('linkedin')
        ->and($jobLead->source_post_url)->toBe('https://www.linkedin.com/posts/hiring-post-123')
        ->and($jobLead->source_url)->toBeNull()
        ->and($jobLead->source_context_text)->toContain('Laravel and Vue engineer')
        ->and($jobLead->description_text)->toContain('Laravel and Vue engineer')
        ->and($jobLead->extracted_keywords)->toContain('laravel')
        ->and($jobLead->extracted_keywords)->toContain('vue');
});

it('does not invent a job url company or title for post based leads', function (): void {
    $user = User::factory()->create();

    $this->artisan('job-leads:import-post', [
        'user_id' => $user->id,
        '--platform' => 'linkedin',
        '--post-url' => 'https://www.linkedin.com/posts/honest-post-456',
        '--text' => 'Hiring backend engineers. Python, APIs, and remote collaboration.',
    ])->assertExitCode(0);

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->source_url)->toBeNull()
        ->and($jobLead->company_name)->toBeNull()
        ->and($jobLead->job_title)->toBeNull();
});

it('dedupes post leads by source post url when no job url is present', function (): void {
    $user = User::factory()->create();

    $this->artisan('job-leads:import-post', [
        'user_id' => $user->id,
        '--platform' => 'linkedin',
        '--post-url' => 'https://www.linkedin.com/posts/duplicate-post-789',
        '--text' => 'Hiring Laravel developers.',
    ])->assertExitCode(0);

    $this->artisan('job-leads:import-post', [
        'user_id' => $user->id,
        '--platform' => 'linkedin',
        '--post-url' => 'https://www.linkedin.com/posts/duplicate-post-789?tracking=feed',
        '--text' => 'Hiring Laravel developers again.',
    ])
        ->expectsOutput('Duplicate skipped.')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('accepts both a source post url and a direct job url when plainly available', function (): void {
    $user = User::factory()->create();

    $this->artisan('job-leads:import-post', [
        'user_id' => $user->id,
        '--platform' => 'linkedin',
        '--post-url' => 'https://www.linkedin.com/posts/direct-job-post',
        '--job-url' => 'https://careers.example.com/jobs/platform-engineer?ref=social',
        '--company' => 'Example Corp',
        '--title' => 'Platform Engineer',
        '--text' => 'Platform engineer role using Laravel, Vue, and AWS.',
    ])->assertExitCode(0);

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->source_post_url)->toBe('https://www.linkedin.com/posts/direct-job-post')
        ->and($jobLead->source_url)->toBe('https://careers.example.com/jobs/platform-engineer?ref=social')
        ->and($jobLead->normalized_source_url)->toBe('https://careers.example.com/jobs/platform-engineer')
        ->and($jobLead->company_name)->toBe('Example Corp')
        ->and($jobLead->job_title)->toBe('Platform Engineer');
});
