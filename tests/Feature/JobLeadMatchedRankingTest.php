<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('ranks jobs with more matched keywords above weaker matches', function (): void {
    $user = User::factory()->create();
    createRankingProfile($user);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Weak Match Co',
        'job_title' => 'Backend Engineer',
        'extracted_keywords' => ['laravel', 'python'],
        'relevance_score' => 99,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Strong Match Co',
        'job_title' => 'Full Stack Engineer',
        'extracted_keywords' => ['laravel', 'vue', 'sql', 'python'],
        'relevance_score' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Strong Match Co')
            ->where('matchedJobs.1.company_name', 'Weak Match Co')
        );
});

it('ranks jobs with fewer missing keywords above broader weaker fits', function (): void {
    $user = User::factory()->create();
    createRankingProfile($user);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Broad Gap Co',
        'job_title' => 'Platform Engineer',
        'extracted_keywords' => ['laravel', 'vue', 'python', 'go', 'rust'],
        'relevance_score' => 95,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Tight Fit Co',
        'job_title' => 'Laravel Engineer',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 25,
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Tight Fit Co')
            ->where('matchedJobs.0.missing_keywords', [])
            ->where('matchedJobs.1.company_name', 'Broad Gap Co')
        );
});

it('does not rank leads missing keyword analysis above analyzed leads', function (): void {
    $user = User::factory()->create();
    createRankingProfile($user);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Missing Analysis Co',
        'job_title' => 'Saved Lead',
        'extracted_keywords' => [],
        'relevance_score' => 100,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Analyzed Lead Co',
        'job_title' => 'Laravel Engineer',
        'extracted_keywords' => ['laravel'],
        'relevance_score' => null,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Analyzed Lead Co')
            ->where('matchedJobs.1.company_name', 'Missing Analysis Co')
        );
});

it('uses relevance score as a tie breaker after match quality', function (): void {
    $user = User::factory()->create();
    createRankingProfile($user);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Lower Relevance Co',
        'job_title' => 'Laravel Engineer',
        'extracted_keywords' => ['laravel', 'vue', 'python'],
        'relevance_score' => 40,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Higher Relevance Co',
        'job_title' => 'Product Engineer',
        'extracted_keywords' => ['laravel', 'vue', 'python'],
        'relevance_score' => 90,
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Higher Relevance Co')
            ->where('matchedJobs.1.company_name', 'Lower Relevance Co')
        );
});

function createRankingProfile(User $user): void
{
    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
    ]);
}
