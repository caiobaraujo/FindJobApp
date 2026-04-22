<?php

use App\Models\Application;
use App\Models\User;

it('allows an authenticated owner to update application status', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->for($user)->create([
        'status' => 'wishlist',
    ]);

    $this->actingAs($user)
        ->from(route('applications.index', ['view' => 'pipeline']))
        ->patch(route('applications.status.update', $application), [
            'status' => 'interview',
        ])
        ->assertRedirect(route('applications.index', ['view' => 'pipeline']));

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'status' => 'interview',
    ]);
});

it('rejects an invalid application status update', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->for($user)->create([
        'status' => 'wishlist',
    ]);

    $this->actingAs($user)
        ->from(route('applications.index', ['view' => 'pipeline']))
        ->patch(route('applications.status.update', $application), [
            'status' => 'invalid-status',
        ])
        ->assertSessionHasErrors(['status']);

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'status' => 'wishlist',
    ]);
});

it('prevents a user from updating another users application status', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = Application::factory()->for($owner)->create([
        'status' => 'applied',
    ]);

    $this->actingAs($intruder)
        ->patch(route('applications.status.update', $application), [
            'status' => 'offer',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'status' => 'applied',
    ]);
});

it('returns a redirect suitable for the pipeline ui flow', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->for($user)->create([
        'status' => 'applied',
    ]);

    $response = $this->actingAs($user)
        ->from(route('applications.index', [
            'view' => 'pipeline',
            'search' => 'Acme',
        ]))
        ->patch(route('applications.status.update', $application), [
            'status' => 'offer',
        ]);

    $response
        ->assertRedirect(route('applications.index', [
            'view' => 'pipeline',
            'search' => 'Acme',
        ]))
        ->assertSessionHas('success', 'Application status updated successfully.');
});
