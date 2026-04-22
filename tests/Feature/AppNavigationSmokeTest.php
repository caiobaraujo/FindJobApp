<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->actingAs($user)
        ->get(route('job-leads.import.entry'))
        ->assertRedirect(route('job-leads.create'));
});

it('renders the main authenticated pages with shared translation props', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('translations.nav')
            ->where('translations.shell.account', 'Account')
        );

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('translations.matched_jobs')
        );

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->has('translations.resume')
        );
});
