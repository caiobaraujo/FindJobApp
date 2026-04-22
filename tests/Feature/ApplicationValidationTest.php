<?php

use App\Models\Application;
use App\Models\User;

it('validates required application fields on create', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('applications.store'), [
            'company_name' => '',
            'job_title' => '',
            'status' => '',
        ])
        ->assertSessionHasErrors([
            'company_name',
            'job_title',
            'status',
        ]);
});

it('validates the status value on create', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('applications.store'), [
            'company_name' => 'Acme Inc',
            'job_title' => 'Backend Engineer',
            'status' => 'invalid-status',
        ])
        ->assertSessionHasErrors(['status']);
});

it('validates the status value on update', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('applications.update', $application), [
            'company_name' => $application->company_name,
            'job_title' => $application->job_title,
            'source_url' => $application->source_url,
            'status' => 'invalid-status',
            'applied_at' => optional($application->applied_at)->toDateString(),
            'notes' => $application->notes,
        ])
        ->assertSessionHasErrors(['status']);
});
