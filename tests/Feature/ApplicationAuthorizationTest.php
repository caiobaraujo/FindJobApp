<?php

use App\Models\Application;
use App\Models\User;

it('prevents a user from editing another users application', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = Application::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('applications.edit', $application))
        ->assertForbidden();
});

it('prevents a user from updating another users application', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = Application::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->patch(route('applications.update', $application), [
            'company_name' => 'Hacked Company',
            'job_title' => 'Hacked Role',
            'source_url' => 'https://example.com/hacked',
            'status' => 'offer',
            'applied_at' => '2026-04-22',
            'notes' => 'Should not be updated.',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('applications', [
        'id' => $application->id,
        'company_name' => 'Hacked Company',
    ]);
});

it('prevents a user from deleting another users application', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = Application::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('applications.destroy', $application))
        ->assertForbidden();

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
    ]);
});
