<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('does not serialize preference fit when the user has no saved preferences', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer',
        'core_skills' => ['Laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'No Preferences Co',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.preference_fit', null)
        );
});

it('serializes a work mode preference match', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'preferred_work_modes' => [JobLead::WORK_MODE_REMOTE],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Remote Fit Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.preference_fit.status', 'match')
            ->where('matchedJobs.0.preference_fit.matched', ['work_mode'])
            ->where('matchedJobs.0.preference_fit.mismatched', [])
            ->where('matchedJobs.0.why_this_job.preference_summary', 'match')
        );
});

it('serializes a work mode preference mismatch', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'preferred_work_modes' => [JobLead::WORK_MODE_REMOTE],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Onsite Mismatch Co',
        'work_mode' => JobLead::WORK_MODE_ONSITE,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.preference_fit.status', 'mismatch')
            ->where('matchedJobs.0.preference_fit.matched', [])
            ->where('matchedJobs.0.preference_fit.mismatched', ['work_mode'])
            ->where('matchedJobs.0.why_this_job', null)
        );
});

it('serializes a target role preference match from the job title', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'target_roles' => ['Product Engineer'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Role Fit Co',
        'job_title' => 'Senior Product Engineer',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.preference_fit.status', 'match')
            ->where('matchedJobs.0.preference_fit.matched', ['target_role'])
        );
});

it('serializes a location preference match from the lead location', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'preferred_locations' => ['Lisbon, Portugal'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Location Fit Co',
        'location' => 'Lisbon, Portugal',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.preference_fit.status', 'match')
            ->where('matchedJobs.0.preference_fit.matched', ['location'])
            ->where('matchedJobs.0.why_this_job.preference_summary', 'match')
        );
});

it('does not invent preference fit data for url only leads with app generated titles', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'target_roles' => ['Product Engineer'],
        'preferred_locations' => ['Remote'],
        'preferred_work_modes' => [JobLead::WORK_MODE_REMOTE],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Imported URL Only Co',
        'job_title' => 'Imported job',
        'source_url' => 'https://example.com/jobs/url-only-fit',
        'location' => null,
        'work_mode' => null,
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.preference_fit', null)
            ->where('matchedJobs.0.has_limited_analysis', true)
        );
});

it('scopes preference fit to the authenticated users profile only', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'preferred_work_modes' => [JobLead::WORK_MODE_REMOTE],
    ]);

    UserProfile::query()->create([
        'user_id' => $otherUser->id,
        'preferred_work_modes' => [JobLead::WORK_MODE_ONSITE],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Scoped Preference Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    JobLead::factory()->for($otherUser)->saved()->create([
        'company_name' => 'Other User Co',
        'work_mode' => JobLead::WORK_MODE_ONSITE,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Scoped Preference Co')
            ->where('matchedJobs.0.preference_fit.status', 'match')
            ->where('matchedJobs.0.preference_fit.matched', ['work_mode'])
        )
        ->assertDontSee('Other User Co');
});
