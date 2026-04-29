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
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->where('filters.show_ignored', false)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Visible Saved Lead')
        )
        ->assertDontSee('Hidden Ignored Lead');
});

it('excludes clearly international jobs by default in the saved job workspace', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Lead Co',
        'location' => 'Remote Brazil',
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Lead Co',
        'location' => 'Remote, United States',
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Unknown Lead Co',
        'location' => null,
        'relevance_score' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->has('matchedJobs', 2)
        )
        ->assertSee('Brazil Lead Co')
        ->assertSee('Unknown Lead Co')
        ->assertDontSee('International Lead Co');

    $locationClassifications = collect($response->inertiaProps('matchedJobs'))
        ->pluck('location_classification', 'company_name')
        ->sortKeys()
        ->all();

    expect($locationClassifications)->toBe([
        'Brazil Lead Co' => JobLead::LOCATION_CLASSIFICATION_BRAZIL,
        'Unknown Lead Co' => JobLead::LOCATION_CLASSIFICATION_UNKNOWN,
    ]);
});

it('excludes known international source leads by default when location is missing', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Unknown Manual Lead',
        'source_name' => 'Manual',
        'source_type' => JobLead::SOURCE_TYPE_MANUAL,
        'location' => null,
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Remotive JavaScript Lead',
        'source_name' => 'Remotive',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'job_title' => 'JavaScript Engineer',
        'location' => null,
        'relevance_score' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Unknown Manual Lead')
            ->where('matchedJobs.0.location_classification', JobLead::LOCATION_CLASSIFICATION_UNKNOWN)
        )
        ->assertSee('Unknown Manual Lead')
        ->assertDontSee('Remotive JavaScript Lead');

    expect(collect($response->inertiaProps('matchedJobs'))->pluck('company_name')->all())
        ->toBe(['Unknown Manual Lead']);
});

it('includes international jobs when location scope is all', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Lead Co',
        'location' => 'Remote, United States',
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Lead Co',
        'location' => 'Belo Horizonte, Brazil',
        'relevance_score' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('job-leads.index', [
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_ALL)
            ->has('matchedJobs', 2)
        )
        ->assertSee('International Lead Co')
        ->assertSee('Brazil Lead Co');

    $locationClassifications = collect($response->inertiaProps('matchedJobs'))
        ->pluck('location_classification', 'company_name')
        ->sortKeys()
        ->all();

    expect($locationClassifications)->toBe([
        'Brazil Lead Co' => JobLead::LOCATION_CLASSIFICATION_BRAZIL,
        'International Lead Co' => JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL,
    ]);
});

it('includes known international source leads when location scope is all', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'We Work Remotely Lead',
        'source_name' => 'We Work Remotely',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Company Career Lead',
        'source_name' => 'Company Career Pages',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'source_url' => 'https://hotmart.com/en/jobs/product-engineer',
        'location' => null,
        'relevance_score' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('job-leads.index', [
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_ALL)
            ->has('matchedJobs', 2)
        )
        ->assertSee('We Work Remotely Lead')
        ->assertSee('Company Career Lead');

    $locationClassifications = collect($response->inertiaProps('matchedJobs'))
        ->pluck('location_classification', 'company_name')
        ->sortKeys()
        ->all();

    expect($locationClassifications)->toBe([
        'Company Career Lead' => JobLead::LOCATION_CLASSIFICATION_BRAZIL,
        'We Work Remotely Lead' => JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL,
    ]);
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

it('keeps unknown manual leads visible by default', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Unknown Manual Lead',
        'source_type' => JobLead::SOURCE_TYPE_MANUAL,
        'source_name' => 'Manual',
        'location' => null,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Unknown Manual Lead')
            ->where('matchedJobs.0.location_classification', JobLead::LOCATION_CLASSIFICATION_UNKNOWN)
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

it('shows only leads from the latest discovery batch when requested', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel and Vue',
        'last_discovery_batch_id' => 'batch-latest',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Older Batch Lead',
        'job_title' => 'Laravel Engineer',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'discovery_batch_id' => 'batch-older',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Latest Batch Lead',
        'job_title' => 'Vue Engineer',
        'description_text' => 'Vue and Laravel role.',
        'extracted_keywords' => ['vue', 'laravel'],
        'discovery_batch_id' => 'batch-latest',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'discovery_batch' => 'latest',
            'location_scope' => JobLead::LOCATION_SCOPE_BRAZIL,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('isLatestDiscoveryView', true)
            ->where('filters.discovery_batch', 'latest')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_ALL)
            ->where('filters.show_ignored', true)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Latest Batch Lead')
            ->where('latestDiscoveryBatchId', 'batch-latest')
        )
        ->assertDontSee('Older Batch Lead');
});

it('keeps the default workspace list when no discovery batch filter is applied', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel and Vue',
        'last_discovery_batch_id' => 'batch-latest',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Older Batch Lead',
        'job_title' => 'Laravel Engineer',
        'description_text' => 'Laravel and Vue role.',
        'extracted_keywords' => ['laravel', 'vue'],
        'discovery_batch_id' => 'batch-older',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Latest Batch Lead',
        'job_title' => 'Vue Engineer',
        'description_text' => 'Vue and Laravel role.',
        'extracted_keywords' => ['vue', 'laravel'],
        'discovery_batch_id' => 'batch-latest',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.discovery_batch', '')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->has('matchedJobs', 2)
        );
});

it('shows all jobs from the latest discovery batch regardless of conflicting workspace filters', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'last_discovery_batch_id' => 'batch-latest',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Older International Lead',
        'lead_status' => JobLead::STATUS_SAVED,
        'location' => 'Worldwide',
        'discovery_batch_id' => 'batch-older',
    ]);

    JobLead::factory()->for($user)->shortlisted()->create([
        'company_name' => 'Latest International Lead',
        'location' => 'Portugal',
        'discovery_batch_id' => 'batch-latest',
    ]);

    JobLead::factory()->for($user)->applied()->create([
        'company_name' => 'Latest Brazil Lead',
        'location' => 'Belo Horizonte, Brazil',
        'description_text' => null,
        'extracted_keywords' => [],
        'discovery_batch_id' => 'batch-latest',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Latest Ignored Lead',
        'location' => 'Remote, Brazil',
        'job_title' => 'Frontend Engineer',
        'description_text' => 'Frontend and JavaScript role.',
        'extracted_keywords' => ['javascript', 'frontend'],
        'discovery_batch_id' => 'batch-latest',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'discovery_batch' => 'latest',
            'lead_status' => JobLead::STATUS_SHORTLISTED,
            'location_scope' => JobLead::LOCATION_SCOPE_BRAZIL,
            'search' => 'No matching lead title',
            'analysis_readiness' => JobLead::ANALYSIS_READINESS_READY,
            'analysis_state' => JobLead::ANALYSIS_STATE_ANALYZED,
            'show_ignored' => 0,
            'work_mode' => JobLead::WORK_MODE_ONSITE,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('isLatestDiscoveryView', true)
            ->where('filters.discovery_batch', 'latest')
            ->where('filters.lead_status', '')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_ALL)
            ->where('filters.search', '')
            ->where('filters.analysis_readiness', '')
            ->where('filters.analysis_state', '')
            ->where('filters.work_mode', '')
            ->where('filters.show_ignored', true)
            ->has('matchedJobs', 3)
        )
        ->assertDontSee('Older International Lead')
        ->assertSee('Latest International Lead')
        ->assertSee('Latest Brazil Lead')
        ->assertSee('Latest Ignored Lead');
});

it('does not let search bypass the default brazil location scope', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil JavaScript Lead',
        'job_title' => 'JavaScript Engineer',
        'location' => 'Sao Paulo, Brazil',
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International JavaScript Lead',
        'job_title' => 'JavaScript Engineer',
        'source_name' => 'Remotive',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
        'relevance_score' => null,
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'search' => 'javascript',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.search', 'javascript')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Brazil JavaScript Lead')
        )
        ->assertDontSee('International JavaScript Lead');
});

it('keeps location filtering scoped to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'User Brazil Lead',
        'location' => 'Contagem, MG',
    ]);

    JobLead::factory()->for($otherUser)->saved()->create([
        'company_name' => 'Other User International Lead',
        'location' => 'Canada',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_ALL)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'User Brazil Lead')
        )
        ->assertDontSee('Other User International Lead');
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

it('shows matched jobs visibility summary counts for default hidden ignored and international leads', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Brazil Match',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Ignored Brazil Match',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible International Match',
        'location' => 'Remote, United States',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Ignored International Match',
        'location' => 'Remote, Germany',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Visible Unmatched Brazil Lead',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['python'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Visible Brazil Match')
            ->where('matchedJobsVisibilitySummary.visible_count', 1)
            ->where('matchedJobsVisibilitySummary.hidden_ignored_count', 2)
            ->where('matchedJobsVisibilitySummary.hidden_international_count', 2)
            ->where('matchedJobsVisibilitySummary.total_count', 4)
        );
});

it('shows zero hidden matched job summary counts when ignored and international filters are already enabled', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Match',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Ignored Match',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Match',
        'location' => 'Remote, Canada',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index', [
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
            'show_ignored' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 3)
            ->where('matchedJobsVisibilitySummary.visible_count', 3)
            ->where('matchedJobsVisibilitySummary.hidden_ignored_count', 0)
            ->where('matchedJobsVisibilitySummary.hidden_international_count', 0)
            ->where('matchedJobsVisibilitySummary.total_count', 3)
        );
});

it('separates matched and broader discovered leads in the all discovered workspace', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
        'last_discovery_batch_id' => 'latest-batch-split-1',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-split-1',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Broader Brazil Lead',
        'job_title' => 'Python Engineer',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['python'],
        'discovery_batch_id' => 'latest-batch-split-1',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote, Canada',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-split-1',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('workspaceView', 'all')
            ->where('filters.lead_group', 'all')
            ->has('matchedJobs', 2)
            ->where('matchedJobs.0.company_name', 'Brazil Match')
            ->where('matchedJobs.1.company_name', 'Broader Brazil Lead')
            ->where('latestDiscoveryWorkspaceSplit.latest_batch_total_count', 3)
            ->where('latestDiscoveryWorkspaceSplit.matched_leads_count', 2)
            ->where('latestDiscoveryWorkspaceSplit.unmatched_leads_count', 1)
            ->where('latestDiscoveryWorkspaceSplit.visible_matched_count', 1)
            ->where('latestDiscoveryWorkspaceSplit.visible_unmatched_count', 1)
            ->where('latestDiscoveryWorkspaceSplit.hidden_international_count', 1)
        )
        ->assertSee('Brazil Match')
        ->assertSee('Broader Brazil Lead')
        ->assertDontSee('International Match');
});

it('shows unmatched technology leads in the broader workspace segment', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
        'last_discovery_batch_id' => 'latest-batch-split-2',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Matched Lead',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-split-2',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Unmatched Technology Lead',
        'job_title' => 'Python Engineer',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['python'],
        'discovery_batch_id' => 'latest-batch-split-2',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'lead_group' => 'unmatched',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('workspaceView', 'unmatched')
            ->where('filters.lead_group', 'unmatched')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Unmatched Technology Lead')
        )
        ->assertSee('Unmatched Technology Lead')
        ->assertDontSee('Matched Lead');
});

it('updates broader workspace international visibility counts when international leads are enabled', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
        'last_discovery_batch_id' => 'latest-batch-split-3',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-split-3',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote, Germany',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-split-3',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('latestDiscoveryWorkspaceSplit.visible_matched_count', 1)
            ->where('latestDiscoveryWorkspaceSplit.visible_unmatched_count', 0)
            ->where('latestDiscoveryWorkspaceSplit.hidden_international_count', 1)
        )
        ->assertDontSee('International Match');

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 2)
            ->where('latestDiscoveryWorkspaceSplit.visible_matched_count', 2)
            ->where('latestDiscoveryWorkspaceSplit.visible_unmatched_count', 0)
            ->where('latestDiscoveryWorkspaceSplit.hidden_international_count', 0)
        )
        ->assertSee('International Match');
});

it('filters existing job leads with deterministic search intent terms', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Python backend engineer.',
        'core_skills' => ['Python'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Python Remote',
        'job_title' => 'Python Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Python backend role.',
        'extracted_keywords' => ['python', 'backend'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Brazil Python Hybrid',
        'job_title' => 'Python Engineer',
        'location' => 'Sao Paulo, Brazil',
        'work_mode' => JobLead::WORK_MODE_HYBRID,
        'description_text' => 'Python hybrid role.',
        'extracted_keywords' => ['python'],
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'International Python Remote',
        'job_title' => 'Python Engineer',
        'location' => 'Remote, Portugal',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Python remote role.',
        'extracted_keywords' => ['python'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'lead_group' => 'all',
            'search' => 'python remote brazil',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('filters.lead_group', 'all')
            ->where('filters.search', 'python remote brazil')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_BRAZIL)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Brazil Python Remote')
        )
        ->assertSee('Brazil Python Remote')
        ->assertDontSee('Brazil Python Hybrid')
        ->assertDontSee('International Python Remote');
});

it('shows latest discovery funnel counts for ignored status work mode and search filters', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
        'last_discovery_batch_id' => 'latest-batch-1',
    ]);

    JobLead::factory()->for($user)->shortlisted()->create([
        'company_name' => 'Scoped Visible Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-1',
    ]);

    JobLead::factory()->for($user)->ignored()->create([
        'company_name' => 'Scoped Ignored Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-1',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Scoped Saved Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-1',
    ]);

    JobLead::factory()->for($user)->shortlisted()->create([
        'company_name' => 'Scoped Hybrid Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_HYBRID,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-1',
    ]);

    JobLead::factory()->for($user)->shortlisted()->create([
        'company_name' => 'Other Search Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-1',
    ]);

    JobLead::factory()->for($user)->shortlisted()->create([
        'company_name' => 'Scoped Unmatched Lead',
        'job_title' => 'Python Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['python'],
        'discovery_batch_id' => 'latest-batch-1',
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index', [
            'lead_status' => JobLead::STATUS_SHORTLISTED,
            'work_mode' => JobLead::WORK_MODE_REMOTE,
            'search' => 'Scoped',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Scoped Visible Match')
            ->where('latestDiscoveryMatchFunnel.latest_batch_total_count', 6)
            ->where('latestDiscoveryMatchFunnel.matched_before_default_hiding_count', 5)
            ->where('latestDiscoveryMatchFunnel.visible_matched_count', 1)
            ->where('latestDiscoveryMatchFunnel.hidden_ignored_count', 1)
            ->where('latestDiscoveryMatchFunnel.hidden_international_count', 0)
            ->where('latestDiscoveryMatchFunnel.hidden_status_filter_count', 1)
            ->where('latestDiscoveryMatchFunnel.hidden_analysis_readiness_filter_count', 0)
            ->where('latestDiscoveryMatchFunnel.hidden_analysis_state_filter_count', 0)
            ->where('latestDiscoveryMatchFunnel.hidden_work_mode_filter_count', 1)
            ->where('latestDiscoveryMatchFunnel.hidden_search_text_filter_count', 1)
            ->where('latestDiscoveryMatchFunnel.imported_not_matched_count', 1)
        );
});

it('counts limited analysis discovery leads as imported but not considered matched', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
        'last_discovery_batch_id' => 'latest-batch-2',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Ready Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-2',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Needs Description Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => null,
        'extracted_keywords' => [],
        'discovery_batch_id' => 'latest-batch-2',
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index', [
            'analysis_readiness' => JobLead::ANALYSIS_READINESS_READY,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Ready Match')
            ->where('latestDiscoveryMatchFunnel.latest_batch_total_count', 2)
            ->where('latestDiscoveryMatchFunnel.matched_before_default_hiding_count', 1)
            ->where('latestDiscoveryMatchFunnel.visible_matched_count', 1)
            ->where('latestDiscoveryMatchFunnel.hidden_analysis_readiness_filter_count', 0)
            ->where('latestDiscoveryMatchFunnel.imported_not_matched_count', 1)
        );
});

it('keeps analysis state funnel counts explicit even when missing-analysis leads are not considered matched', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
        'last_discovery_batch_id' => 'latest-batch-3',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Analyzed Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel'],
        'discovery_batch_id' => 'latest-batch-3',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Missing Analysis Match',
        'job_title' => 'Laravel Engineer',
        'location' => 'Remote Brazil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => null,
        'extracted_keywords' => [],
        'discovery_batch_id' => 'latest-batch-3',
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index', [
            'analysis_state' => JobLead::ANALYSIS_STATE_ANALYZED,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Analyzed Match')
            ->where('latestDiscoveryMatchFunnel.latest_batch_total_count', 2)
            ->where('latestDiscoveryMatchFunnel.matched_before_default_hiding_count', 1)
            ->where('latestDiscoveryMatchFunnel.visible_matched_count', 1)
            ->where('latestDiscoveryMatchFunnel.hidden_analysis_state_filter_count', 0)
            ->where('latestDiscoveryMatchFunnel.imported_not_matched_count', 1)
        );
});
