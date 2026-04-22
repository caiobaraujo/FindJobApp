<?php

use App\Models\User;

it('resolves the authenticated navigation destinations', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk();
});

it('renders the main authenticated pages', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk();
});
