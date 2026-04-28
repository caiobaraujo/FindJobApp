<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('hides ignored leads by default in the saved job workspace', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Saved Lead',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Hidden Ignored Lead',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.show_ignored', false)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Visible Saved Lead')
        )
        ->assertDontSee('Hidden Ignored Lead');
});

it('keeps url only leads visible in the saved job workspace and marks them as limited analysis', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'URL Only Lead Co',
        'job_title' => 'Imported job',
        'source_url' => 'https://example.com/jobs/url-only',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'URL Only Lead Co')
            ->where('matchedJobs.0.source_url', 'https://example.com/jobs/url-only')
            ->where('matchedJobs.0.has_limited_analysis', true)
            ->where('matchedJobs.0.why_this_job', null)
        );
});

it('does not mark analyzed leads as limited analysis', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Analyzed Lead Co',
        'job_title' => 'Laravel Engineer',
        'description_text' => 'We need a Laravel engineer with Vue and SQL experience.',
        'extracted_keywords' => ['laravel', 'vue', 'sql'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Analyzed Lead Co')
            ->where('matchedJobs.0.has_limited_analysis', false)
        );
});

it('orders ready to evaluate leads ahead of limited analysis leads by default', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Needs Description Co',
        'description_text' => null,
        'extracted_keywords' => [],
        'relevance_score' => 99,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Ready Lead Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Ready Lead Co')
            ->where('matchedJobs.1.company_name', 'Needs Description Co')
        );
});

it('orders higher relevance leads ahead of lower relevance leads when analysis quality is tied', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Lower Score Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 40,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Higher Score Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 90,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Higher Score Co')
            ->where('matchedJobs.1.company_name', 'Lower Score Co')
        );
});

it('orders active leads ahead of applied leads in the default workspace', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->applied()->create([
        'company_name' => 'Applied Lead Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 95,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Active Lead Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Active Lead Co')
            ->where('matchedJobs.1.company_name', 'Applied Lead Co')
        );
});

it('uses newest leads as the fallback order when usefulness signals are tied', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Older Lead Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 70,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Newer Lead Co',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'relevance_score' => 70,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Newer Lead Co')
            ->where('matchedJobs.1.company_name', 'Older Lead Co')
        );
});

it('prioritizes jobs that overlap with the users main technical stack over unrelated jobs', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'PHP, Laravel, Python, Django, Vue, MySQL, SQL, Docker, OpenAI, and full stack backend development.',
        'core_skills' => ['PHP', 'Laravel', 'Python', 'Django', 'Vue', 'MySQL', 'SQL', 'Docker', 'OpenAI'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Relevant Stack Co',
        'description_text' => 'Looking for a PHP Laravel Vue backend engineer with MySQL, Docker, and Python experience.',
        'extracted_keywords' => ['php', 'laravel', 'vue', 'mysql', 'docker', 'python'],
        'relevance_score' => 10,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Unrelated Lead Co',
        'description_text' => 'Seeking a sales operations leader with customer success and revenue planning experience.',
        'extracted_keywords' => ['sales', 'operations', 'customer success', 'revenue'],
        'relevance_score' => 100,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Relevant Stack Co')
            ->where('matchedJobs.1.company_name', 'Unrelated Lead Co')
        );
});

it('shows ignored leads when explicitly requested in the saved job workspace', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Saved Lead',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Visible Ignored Lead',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['show_ignored' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.show_ignored', true)
            ->has('matchedJobs', 2)
        )
        ->assertSee('Visible Ignored Lead');
});

it('filters the saved job workspace by lead status', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Saved Lead Co',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Ignored Lead Co',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['lead_status' => JobLead::STATUS_IGNORED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.lead_status', JobLead::STATUS_IGNORED)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Ignored Lead Co')
        );
});

it('returns lead status counters scoped to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create();

    JobLead::factory()->for($user)->shortlisted()->create();

    JobLead::factory()->for($user)->applied()->create();

    JobLead::factory()->for($user)->ignored()->create();

    JobLead::factory()->for($otherUser)->ignored()->create();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('leadStatusCounts.active', 2)
            ->where('leadStatusCounts.ignored', 1)
            ->where('leadStatusCounts.applied', 1)
        );
});

it('filters the saved job workspace by analysis state', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Analyzed Lead Co',
        'description_text' => 'Full job description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Missing Analysis Co',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['analysis_state' => JobLead::ANALYSIS_STATE_ANALYZED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.analysis_state', JobLead::ANALYSIS_STATE_ANALYZED)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Analyzed Lead Co')
        );
});

it('filters the saved job workspace by analysis readiness needs description', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Needs Description Co',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Ready Lead Co',
        'description_text' => 'Full job description',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['analysis_readiness' => JobLead::ANALYSIS_READINESS_NEEDS_DESCRIPTION]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.analysis_readiness', JobLead::ANALYSIS_READINESS_NEEDS_DESCRIPTION)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Needs Description Co')
            ->where('matchedJobs.0.has_limited_analysis', true)
        );
});

it('filters the saved job workspace by analysis readiness ready to evaluate', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Needs Description Co',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Ready Lead Co',
        'description_text' => 'Full job description',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['analysis_readiness' => JobLead::ANALYSIS_READINESS_READY]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.analysis_readiness', JobLead::ANALYSIS_READINESS_READY)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Ready Lead Co')
            ->where('matchedJobs.0.has_limited_analysis', false)
        );
});

it('returns both readiness states when analysis readiness is all', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Needs Description Co',
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Ready Lead Co',
        'description_text' => 'Full job description',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['analysis_readiness' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.analysis_readiness', '')
            ->has('matchedJobs', 2)
        );
});

it('filters the saved job workspace by work mode and keeps user scoping intact', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Remote Lead Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Hybrid Lead Co',
        'work_mode' => JobLead::WORK_MODE_HYBRID,
    ]);

    JobLead::factory()->for($otherUser)->create([
        'company_name' => 'Other User Remote Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', ['work_mode' => JobLead::WORK_MODE_REMOTE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.work_mode', JobLead::WORK_MODE_REMOTE)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Remote Lead Co')
        )
        ->assertDontSee('Other User Remote Co');
});

it('composes analysis readiness with work mode filters and keeps user scoping intact', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Remote Needs Description Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Remote Ready Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full job description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($otherUser)->saved()->create([
        'company_name' => 'Other User Remote Needs Description Co',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => null,
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'analysis_readiness' => JobLead::ANALYSIS_READINESS_NEEDS_DESCRIPTION,
            'work_mode' => JobLead::WORK_MODE_REMOTE,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.analysis_readiness', JobLead::ANALYSIS_READINESS_NEEDS_DESCRIPTION)
            ->where('filters.work_mode', JobLead::WORK_MODE_REMOTE)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Remote Needs Description Co')
        )
        ->assertDontSee('Remote Ready Co')
        ->assertDontSee('Other User Remote Needs Description Co');
});

it('keeps matched only mode active when lead status filters are applied', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue experience.',
        'core_skills' => ['Laravel', 'Vue'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Matching Saved Lead',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Non Matching Saved Lead',
        'description_text' => 'Full description',
        'extracted_keywords' => ['python'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index', ['lead_status' => JobLead::STATUS_SAVED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.lead_status', JobLead::STATUS_SAVED)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Matching Saved Lead')
        )
        ->assertDontSee('Non Matching Saved Lead');
});
