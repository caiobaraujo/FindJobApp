<?php

use App\Models\JobLead;
use App\Models\Application;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('renders dashboard metrics for the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Application::factory()->for($user)->create([
        'company_name' => 'Wishlist Co',
        'job_title' => 'Role 1',
        'status' => 'wishlist',
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Applied Co',
        'job_title' => 'Role 2',
        'status' => 'applied',
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Interview Co',
        'job_title' => 'Role 3',
        'status' => 'interview',
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Offer Co',
        'job_title' => 'Role 4',
        'status' => 'offer',
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Rejected Co',
        'job_title' => 'Role 5',
        'status' => 'rejected',
    ]);

    Application::factory()->for($otherUser)->create([
        'company_name' => 'Other User Co',
        'job_title' => 'Other Role',
        'status' => 'offer',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('totalApplications', 5)
            ->where('statusCounts.wishlist', 1)
            ->where('statusCounts.applied', 1)
            ->where('statusCounts.interview', 1)
            ->where('statusCounts.offer', 1)
            ->where('statusCounts.rejected', 1)
            ->has('applications', 5)
        );
});

it('shows only the current users latest five applications in recent applications', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Application::factory()->for($user)->create([
        'company_name' => 'Oldest Co',
        'job_title' => 'Oldest Role',
        'status' => 'wishlist',
        'created_at' => now()->subDays(6),
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Recent One',
        'job_title' => 'Role 1',
        'status' => 'applied',
        'created_at' => now()->subDays(5),
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Recent Two',
        'job_title' => 'Role 2',
        'status' => 'interview',
        'created_at' => now()->subDays(4),
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Recent Three',
        'job_title' => 'Role 3',
        'status' => 'offer',
        'created_at' => now()->subDays(3),
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Recent Four',
        'job_title' => 'Role 4',
        'status' => 'rejected',
        'created_at' => now()->subDays(2),
    ]);

    Application::factory()->for($user)->create([
        'company_name' => 'Recent Five',
        'job_title' => 'Role 5',
        'status' => 'applied',
        'created_at' => now()->subDay(),
    ]);

    Application::factory()->for($otherUser)->create([
        'company_name' => 'Other Users Latest',
        'job_title' => 'Should Not Appear',
        'status' => 'offer',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('applications', 5)
            ->where('applications.0.company_name', 'Recent Five')
            ->where('applications.1.company_name', 'Recent Four')
            ->where('applications.2.company_name', 'Recent Three')
            ->where('applications.3.company_name', 'Recent Two')
            ->where('applications.4.company_name', 'Recent One')
        )
        ->assertDontSee('Other Users Latest')
        ->assertDontSee('Oldest Co');
});

it('counts only default visible matched jobs on the dashboard', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Match Co',
        'location' => 'Remote Brazil',
        'extracted_keywords' => ['laravel', 'vue'],
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Ignored Match Co',
        'location' => 'Remote Brazil',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Match Co',
        'location' => 'Remote, United States',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Unmatched Visible Co',
        'location' => 'Remote Brazil',
        'extracted_keywords' => ['python'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Missing Analysis Co',
        'location' => 'Remote Brazil',
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('matchedJobsCount', 1)
        );

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->where('filters.show_ignored', false)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Visible Match Co')
        )
        ->assertDontSee('Ignored Match Co')
        ->assertDontSee('International Match Co');
});
