<?php

use App\Models\JobLead;
use App\Models\User;

it('persists analysis fields when a job lead is created', function (): void {
    $user = User::factory()->create();

    $descriptionText = 'We need a Laravel engineer with Laravel API experience, strong testing discipline, and Vue ownership. '
        .'The role also values API design and automated testing across the product.';

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'company_name' => 'Northwind',
            'job_title' => 'Laravel Engineer',
            'source_url' => 'https://example.com/jobs/laravel-engineer',
            'lead_status' => 'saved',
            'description_text' => $descriptionText,
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->sole();

    expect($jobLead->description_text)->toBe($descriptionText);
    expect($jobLead->extracted_keywords)->toContain('laravel');
    expect($jobLead->ats_hints)->not->toBeEmpty();
});

it('creates analysis fields immediately during url first intake when description text is provided', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://example.com/jobs/laravel-engineer',
            'description_text' => 'We need a Laravel engineer with Laravel API experience, Vue ownership, and strong automated testing.',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->sole();

    expect($jobLead->extracted_keywords)->toContain('laravel')
        ->and($jobLead->extracted_keywords)->toContain('testing')
        ->and($jobLead->ats_hints)->not->toBeEmpty();
});

it('updates analysis fields when a job lead changes', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create([
        'description_text' => null,
        'extracted_keywords' => null,
        'ats_hints' => null,
    ]);

    $descriptionText = 'This product role values analytics, communication, and product strategy. '
        .'Communication with stakeholders and analytics fluency matter in this product organization.';

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => $descriptionText,
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead->refresh();

    expect($jobLead->description_text)->toBe($descriptionText);
    expect($jobLead->extracted_keywords)->toContain('communication');
    expect($jobLead->ats_hints)->not->toBeEmpty();
});

it('clears stale analysis fields when job description text is removed', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create([
        'description_text' => 'Laravel Vue role with testing ownership.',
        'extracted_keywords' => ['laravel', 'vue', 'testing'],
        'ats_hints' => ['Likely ATS terms to reflect in your resume: laravel, vue, testing.'],
    ]);

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => '',
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead->refresh();

    expect($jobLead->description_text)->toBeNull();
    expect($jobLead->extracted_keywords)->toBe([]);
    expect($jobLead->ats_hints)->toBe([
        'Paste the full job description to unlock ATS keyword analysis.',
    ]);
});

it('handles empty description text safely', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'company_name' => 'Northwind',
            'job_title' => 'Analyst',
            'source_url' => 'https://example.com/jobs/analyst',
            'lead_status' => 'saved',
            'description_text' => '',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->sole();

    expect($jobLead->description_text)->toBeNull();
    expect($jobLead->extracted_keywords)->toBe([]);
    expect($jobLead->ats_hints)->toBe([
        'Paste the full job description to unlock ATS keyword analysis.',
    ]);
});

it('keeps job lead analysis isolated to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Lead Co',
        'job_title' => 'Visible Role',
        'extracted_keywords' => ['laravel', 'testing'],
        'ats_hints' => ['Likely ATS terms to reflect in your resume: laravel, testing.'],
    ]);

    JobLead::factory()->for($otherUser)->create([
        'company_name' => 'Hidden Lead Co',
        'job_title' => 'Hidden Role',
        'extracted_keywords' => ['python', 'automation'],
        'ats_hints' => ['Likely ATS terms to reflect in your resume: python, automation.'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertSee('Visible Lead Co')
        ->assertSee('laravel')
        ->assertDontSee('Hidden Lead Co')
        ->assertDontSee('python');
});


it('surfaces missing keyword analysis counts on the matched jobs page', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Needs Text Co',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/platform',
        'description_text' => null,
        'extracted_keywords' => [],
        'ats_hints' => ['Paste the full job description to unlock ATS keyword analysis.'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('JobLeads/Index')
            ->where('leadsMissingAnalysisCount', 1)
        );
});
