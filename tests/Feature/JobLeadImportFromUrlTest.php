<?php

use App\Models\JobLead;
use App\Models\User;
use Carbon\Carbon;

it('allows an authenticated user to import a job lead from a valid url', function (): void {
    Carbon::setTestNow('2026-04-22');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.import'), [
            'source_url' => 'https://example.com/jobs/staff-engineer',
            'source_name' => 'LinkedIn',
            'company_name' => 'Northwind',
            'job_title' => 'Staff Engineer',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.matched_jobs.import_success'));

    $jobLead = JobLead::query()->sole();

    expect($jobLead->user_id)->toBe($user->id);
    expect($jobLead->source_url)->toBe('https://example.com/jobs/staff-engineer');
    expect($jobLead->normalized_source_url)->toBe('https://example.com/jobs/staff-engineer');
    expect($jobLead->source_host)->toBe('example.com');
    expect($jobLead->source_type)->toBe(JobLead::SOURCE_TYPE_MANUAL);
    expect($jobLead->source_name)->toBe('LinkedIn');
    expect($jobLead->company_name)->toBe('Northwind');
    expect($jobLead->job_title)->toBe('Staff Engineer');
    expect($jobLead->lead_status)->toBe('saved');
    expect($jobLead->discovered_at?->toDateString())->toBe('2026-04-22');
    expect($jobLead->description_text)->toBeNull();
    expect($jobLead->extracted_keywords)->toBe([]);
    expect($jobLead->ats_hints)->toBe([
        'Paste the full job description to unlock ATS keyword analysis.',
    ]);

    Carbon::setTestNow();
});

it('rejects an invalid import url', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('job-leads.index'))
        ->post(route('job-leads.import'), [
            'source_url' => 'not-a-valid-url',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHasErrors(['source_url']);
});

it('sets default import values for new job leads', function (): void {
    Carbon::setTestNow('2026-04-22');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.import'), [
            'source_url' => 'https://example.com/jobs/design-lead',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.matched_jobs.import_success'));

    $jobLead = JobLead::query()->sole();

    expect($jobLead->lead_status)->toBe('saved');
    expect($jobLead->discovered_at?->toDateString())->toBe('2026-04-22');
    expect($jobLead->source_name)->toBeNull();
    expect($jobLead->source_type)->toBe(JobLead::SOURCE_TYPE_MANUAL);
    expect($jobLead->normalized_source_url)->toBe('https://example.com/jobs/design-lead');
    expect($jobLead->source_host)->toBe('example.com');
    expect($jobLead->company_name)->toBe('example.com');
    expect($jobLead->job_title)->toBe('Imported job lead');

    Carbon::setTestNow();
});

it('does not allow guests to import job leads', function (): void {
    $this->post(route('job-leads.import'), [
        'source_url' => 'https://example.com/jobs/guest-blocked',
    ])
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('job_leads', 0);
});
