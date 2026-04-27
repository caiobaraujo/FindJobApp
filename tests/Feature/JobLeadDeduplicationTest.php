<?php

use App\Models\JobLead;
use App\Models\User;

it('redirects to the existing job lead when the same user creates an obvious duplicate', function (): void {
    $user = User::factory()->create();

    $existingJobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/platform-engineer',
        'normalized_source_url' => 'https://example.com/jobs/platform-engineer',
        'source_host' => 'example.com',
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://example.com/jobs/platform-engineer/?utm_source=feed#apply',
            'company_name' => 'Northwind Updated',
            'job_title' => 'Platform Engineer Updated',
        ])
        ->assertRedirect(route('job-leads.edit', $existingJobLead))
        ->assertSessionHas('error', __('app.job_lead_create.duplicate_error'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1);

    $existingJobLead->refresh();

    expect($existingJobLead->company_name)->toBe('Northwind')
        ->and($existingJobLead->job_title)->toBe('Platform Engineer');
});

it('redirects to the existing job lead when import finds an obvious duplicate', function (): void {
    $user = User::factory()->create();

    $existingJobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Staff Engineer',
        'source_url' => 'https://example.com/jobs/staff-engineer',
        'normalized_source_url' => 'https://example.com/jobs/staff-engineer',
        'source_host' => 'example.com',
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.import'), [
            'source_url' => 'https://example.com/jobs/staff-engineer/?ref=feed',
            'company_name' => 'Different Import Name',
            'job_title' => 'Different Import Title',
            'source_name' => 'Indeed',
        ])
        ->assertRedirect(route('job-leads.edit', $existingJobLead))
        ->assertSessionHas('error', __('app.job_lead_create.duplicate_error'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('allows different users to save the same source url independently', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://example.com/jobs/platform-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($secondUser)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://example.com/jobs/platform-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    expect(JobLead::query()->count())->toBe(2);
});

it('allows non duplicate job leads from the same source host', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://example.com/jobs/platform-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'source_url' => 'https://example.com/jobs/staff-engineer',
        ])
        ->assertRedirect(route('job-leads.index'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(2);
});
