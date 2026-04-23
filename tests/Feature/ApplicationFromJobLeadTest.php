<?php

use App\Models\Application;
use App\Models\JobLead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('prefills the application create form from an owned job lead', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/platform-engineer',
    ]);

    $this->actingAs($user)
        ->get(route('applications.create', ['job_lead' => $jobLead->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Create')
            ->where('prefill.job_lead_id', $jobLead->id)
            ->where('prefill.company_name', 'Northwind')
            ->where('prefill.job_title', 'Platform Engineer')
            ->where('prefill.source_url', 'https://example.com/jobs/platform-engineer')
            ->where('prefill.status', 'wishlist')
        );
});

it('creates an application from an owned job lead and keeps the job lead intact', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/platform-engineer',
    ]);

    $this->actingAs($user)
        ->post(route('applications.store'), [
            'job_lead_id' => $jobLead->id,
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_url' => $jobLead->source_url,
            'status' => 'wishlist',
            'applied_at' => null,
            'notes' => 'Created from the saved lead.',
        ])
        ->assertRedirect(route('applications.index'));

    $application = Application::query()->sole();

    expect($application->user_id)->toBe($user->id)
        ->and($application->company_name)->toBe('Northwind')
        ->and($application->job_title)->toBe('Platform Engineer')
        ->and($application->source_url)->toBe('https://example.com/jobs/platform-engineer')
        ->and($application->status)->toBe('wishlist');

    $this->assertDatabaseHas('job_leads', [
        'id' => $jobLead->id,
        'user_id' => $user->id,
        'company_name' => 'Northwind',
    ]);
});

it('prevents a user from opening another users job lead conversion flow', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $jobLead = JobLead::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('applications.create', ['job_lead' => $jobLead->id]))
        ->assertForbidden();
});

it('prevents a user from storing an application from another users job lead', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $jobLead = JobLead::factory()->for($owner)->create([
        'company_name' => 'Private Co',
        'job_title' => 'Private Role',
        'source_url' => 'https://example.com/jobs/private-role',
    ]);

    $this->actingAs($intruder)
        ->post(route('applications.store'), [
            'job_lead_id' => $jobLead->id,
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_url' => $jobLead->source_url,
            'status' => 'wishlist',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('applications', 0);
});

it('redirects to the existing application instead of creating a duplicate conversion', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/platform-engineer',
    ]);

    $application = Application::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/platform-engineer',
        'status' => 'wishlist',
    ]);

    $this->actingAs($user)
        ->get(route('applications.create', ['job_lead' => $jobLead->id]))
        ->assertRedirect(route('applications.edit', $application))
        ->assertSessionHas('success', 'Application already exists for this job lead.');

    $this->actingAs($user)
        ->post(route('applications.store'), [
            'job_lead_id' => $jobLead->id,
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_url' => $jobLead->source_url,
            'status' => 'wishlist',
        ])
        ->assertRedirect(route('applications.edit', $application))
        ->assertSessionHas('success', 'Application already exists for this job lead.');

    $this->assertDatabaseCount('applications', 1);
});
