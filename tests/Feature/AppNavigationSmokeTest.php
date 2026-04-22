<?php

use App\Models\User;

it('resolves the authenticated navigation destinations', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('job-leads.import.entry'))
        ->assertRedirect(route('job-leads.index', ['focus' => 'import']));

    $this->actingAs($user)
        ->get(route('applications.index'))
        ->assertOk();
});

it('renders the main authenticated pages', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('job-leads.create'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('applications.index'))
        ->assertOk();
});
