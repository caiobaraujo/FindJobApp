<?php

use App\Models\Application;
use App\Models\User;

it('allows an authenticated user to manage applications', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('applications.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('applications.create'))
        ->assertOk();

    $payload = [
        'company_name' => 'Acme Inc',
        'job_title' => 'Backend Engineer',
        'source_url' => 'https://example.com/jobs/backend-engineer',
        'status' => 'applied',
        'applied_at' => '2026-04-20',
        'notes' => 'Strong fit for the backend role.',
    ];

    $this->actingAs($user)
        ->post(route('applications.store'), $payload)
        ->assertRedirect(route('applications.index'));

    $application = Application::query()->firstOrFail();

    expect($application->user_id)->toBe($user->id);

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'company_name' => 'Acme Inc',
        'job_title' => 'Backend Engineer',
        'status' => 'applied',
    ]);

    $this->actingAs($user)
        ->get(route('applications.edit', $application))
        ->assertOk();

    $this->actingAs($user)
        ->patch(route('applications.update', $application), [
            'company_name' => 'Acme Labs',
            'job_title' => 'Senior Backend Engineer',
            'source_url' => 'https://example.com/jobs/senior-backend-engineer',
            'status' => 'interview',
            'applied_at' => '2026-04-21',
            'notes' => 'Recruiter screen scheduled.',
        ])
        ->assertRedirect(route('applications.index'));

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'company_name' => 'Acme Labs',
        'job_title' => 'Senior Backend Engineer',
        'status' => 'interview',
    ]);

    $this->actingAs($user)
        ->delete(route('applications.destroy', $application))
        ->assertRedirect(route('applications.index'));

    $this->assertDatabaseMissing('applications', [
        'id' => $application->id,
    ]);
});

it('filters applications by status and search term', function (): void {
    $user = User::factory()->create();

    Application::factory()->for($user)->create([
        'company_name' => 'Acme Inc',
        'job_title' => 'Backend Engineer',
        'status' => 'applied',
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Product Designer',
        'status' => 'wishlist',
    ]);

    $this->actingAs($user)
        ->get(route('applications.index', [
            'status' => 'applied',
            'search' => 'Acme',
        ]))
        ->assertOk()
        ->assertSee('Acme Inc')
        ->assertDontSee('Northwind');
});

it('shows only the authenticated users applications on the dashboard', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Application::factory()->for($user)->create([
        'company_name' => 'Visible Co',
        'job_title' => 'Engineer',
    ]);

    Application::factory()->for($otherUser)->create([
        'company_name' => 'Hidden Co',
        'job_title' => 'Designer',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Visible Co')
        ->assertDontSee('Hidden Co');
});
