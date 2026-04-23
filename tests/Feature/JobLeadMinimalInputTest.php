<?php

use App\Models\JobLead;
use App\Models\User;

it('allows an authenticated user to create a job lead with only source url', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://jobs.example-company.com/openings/senior-product-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->assertDatabaseHas('job_leads', [
        'user_id' => $user->id,
        'source_url' => 'https://jobs.example-company.com/openings/senior-product-engineer',
        'company_name' => 'Example Company',
        'job_title' => 'Imported job',
        'lead_status' => 'saved',
    ]);
});

it('does not create fake keywords from url only intake', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://jobs.example-company.com/openings/senior-product-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->description_text)->toBeNull()
        ->and($jobLead->extracted_keywords)->toBe([])
        ->and($jobLead->ats_hints)->toBe([
            'Paste the full job description to unlock ATS keyword analysis.',
        ]);
});

it('stores a cleaner fallback company name from the url host', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://acme-labs.example.com/jobs/backend-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->company_name)->toBe('Acme Labs')
        ->and($jobLead->job_title)->toBe('Imported job');
});

it('rejects an invalid url in the reduced friction intake flow', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors(['source_url']);
});

it('still runs ats analysis when optional description text is provided', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://company.example.com/jobs/platform-engineer',
            'description_text' => 'We need a Laravel engineer with Vue, SQL, and AWS experience.',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->extracted_keywords)->toContain('laravel')
        ->and($jobLead->extracted_keywords)->toContain('vue')
        ->and($jobLead->ats_hints)->not->toBeEmpty();
});


it('shows the create page for the explicit url first intake flow', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('job-leads.create'))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('JobLeads/Create')
            ->where('leadStatuses.0', 'saved')
        );
});
