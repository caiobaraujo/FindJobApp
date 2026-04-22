<?php

use App\Models\Application;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only the authenticated users applications in the pipeline view', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Application::factory()->for($user)->create([
        'company_name' => 'Wishlist Co',
        'job_title' => 'Platform Engineer',
        'status' => 'wishlist',
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Interview Co',
        'job_title' => 'Staff Engineer',
        'status' => 'interview',
    ]);

    Application::factory()->for($otherUser)->create([
        'company_name' => 'Hidden Co',
        'job_title' => 'Hidden Role',
        'status' => 'offer',
    ]);

    $this->actingAs($user)
        ->get(route('applications.index', ['view' => 'pipeline']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Index')
            ->where('filters.view', 'pipeline')
            ->where('pipelineColumns.0.key', 'wishlist')
            ->where('pipelineColumns.0.count', 1)
            ->where('pipelineColumns.0.applications.0.company_name', 'Wishlist Co')
            ->where('pipelineColumns.2.key', 'interview')
            ->where('pipelineColumns.2.count', 1)
            ->where('pipelineColumns.2.applications.0.company_name', 'Interview Co')
            ->where('pipelineColumns.3.key', 'offer')
            ->where('pipelineColumns.3.count', 0)
        )
        ->assertDontSee('Hidden Co');
});

it('groups applications by status with correct counts in the pipeline view', function (): void {
    $user = User::factory()->create();

    Application::factory()->count(2)->for($user)->create([
        'status' => 'applied',
    ]);

    Application::factory()->count(3)->for($user)->create([
        'status' => 'offer',
    ]);

    Application::factory()->for($user)->create([
        'status' => 'rejected',
    ]);

    $this->actingAs($user)
        ->get(route('applications.index', ['view' => 'pipeline']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Index')
            ->where('pipelineColumns.0.count', 0)
            ->where('pipelineColumns.1.key', 'applied')
            ->where('pipelineColumns.1.count', 2)
            ->where('pipelineColumns.2.count', 0)
            ->where('pipelineColumns.3.key', 'offer')
            ->where('pipelineColumns.3.count', 3)
            ->where('pipelineColumns.4.key', 'rejected')
            ->where('pipelineColumns.4.count', 1)
        );
});

it('renders the pipeline view successfully with no applications', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('applications.index', ['view' => 'pipeline']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Index')
            ->where('filters.view', 'pipeline')
            ->where('pipelineColumns.0.count', 0)
            ->where('pipelineColumns.1.count', 0)
            ->where('pipelineColumns.2.count', 0)
            ->where('pipelineColumns.3.count', 0)
            ->where('pipelineColumns.4.count', 0)
        );
});
