<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('job_discovery.supported_sources', [
        'python-job-board',
        'django-community-jobs',
    ]);
});

it('does not allow guests to trigger job discovery', function (): void {
    $this->post(route('job-leads.discover'))
        ->assertRedirect(route('login'));
});

it('allows an authenticated user to trigger discovery for their own workspace', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userProfile = UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    $otherUserProfile = UserProfile::query()->create([
        'user_id' => $otherUser->id,
        'base_resume_text' => 'Other resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'))
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.new_jobs_found_multiple', [
            'count' => 4,
        ]));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(4)
        ->and(JobLead::query()->where('user_id', $otherUser->id)->count())->toBe(0);

    $userProfile->refresh();
    $otherUserProfile->refresh();
    $batchIds = JobLead::query()
        ->where('user_id', $user->id)
        ->pluck('discovery_batch_id')
        ->unique()
        ->values()
        ->all();

    expect($userProfile->last_discovered_at)->not->toBeNull()
        ->and($userProfile->last_discovered_new_count)->toBe(4)
        ->and($userProfile->last_discovery_batch_id)->not->toBeNull()
        ->and($batchIds)->toHaveCount(1)
        ->and($batchIds[0])->toBe($userProfile->last_discovery_batch_id)
        ->and($otherUserProfile->last_discovered_at)->toBeNull()
        ->and($otherUserProfile->last_discovered_new_count)->toBeNull()
        ->and($otherUserProfile->last_discovery_batch_id)->toBeNull();
});

it('shares source level discovery results through flash after redirect', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('job-leads.discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('discoveryStatus.last_discovered_new_count', 4)
            ->has('flash.discovery', 2)
            ->where('flash.discovery.0.source', 'python-job-board')
            ->where('flash.discovery.0.fetched', 3)
            ->where('flash.discovery.0.created', 2)
            ->where('flash.discovery.0.duplicates', 0)
            ->where('flash.discovery.0.skipped_not_matching_query', 0)
            ->where('flash.discovery.0.invalid', 1)
            ->where('flash.discovery.0.failed', 0)
            ->where('flash.discovery.0.query_used', false)
            ->where('flash.discovery.1.source', 'django-community-jobs')
            ->where('flash.discovery.1.fetched', 3)
            ->where('flash.discovery.1.created', 2)
            ->where('flash.discovery.1.duplicates', 0)
            ->where('flash.discovery.1.skipped_not_matching_query', 0)
            ->where('flash.discovery.1.invalid', 1)
            ->where('flash.discovery.1.failed', 0)
            ->where('flash.discovery.1.query_used', false)
        );
});

it('skips duplicates when the user triggers discovery again', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)->post(route('job-leads.discover'))->assertRedirect(route('job-leads.index'));
    $firstDiscoveryBatchId = UserProfile::query()->where('user_id', $user->id)->sole()->last_discovery_batch_id;

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'))
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.no_new_jobs_found'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();
    $existingLeadBatchIds = JobLead::query()
        ->where('user_id', $user->id)
        ->pluck('discovery_batch_id')
        ->unique()
        ->values()
        ->all();

    expect($userProfile->last_discovered_at)->not->toBeNull()
        ->and($userProfile->last_discovered_new_count)->toBe(0)
        ->and($userProfile->last_discovery_batch_id)->not->toBe($firstDiscoveryBatchId)
        ->and($existingLeadBatchIds)->toHaveCount(1)
        ->and($existingLeadBatchIds[0])->toBe($firstDiscoveryBatchId);
});

it('returns a summary even when one discovery source fails', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response('Upstream error', 500),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'))
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.new_jobs_found_multiple', [
            'count' => 2,
        ]));

    $discovery = session('discovery');

    expect($discovery)->toHaveCount(2)
        ->and($discovery[0]['source'])->toBe('python-job-board')
        ->and($discovery[0]['failed'])->toBe(1)
        ->and($discovery[0]['discovery_batch_id'])->not->toBeNull()
        ->and($discovery[1]['source'])->toBe('django-community-jobs')
        ->and($discovery[1]['created'])->toBe(2)
        ->and($discovery[1]['discovery_batch_id'])->toBe($discovery[0]['discovery_batch_id']);

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and($userProfile->last_discovered_at)->not->toBeNull()
        ->and($userProfile->last_discovered_new_count)->toBe(2);
});

it('imports only matching discovered jobs when a search query is provided', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'), [
            'search_query' => 'Laravel remote',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.new_jobs_found_single'))
        ->assertSessionHas('discovery_search_query', 'Laravel remote');

    $discovery = session('discovery');

    expect($discovery)->toHaveCount(2)
        ->and($discovery[0]['source'])->toBe('python-job-board')
        ->and($discovery[0]['created'])->toBe(1)
        ->and($discovery[0]['skipped_not_matching_query'])->toBe(1)
        ->and($discovery[0]['discovery_batch_id'])->not->toBeNull()
        ->and($discovery[1]['source'])->toBe('django-community-jobs')
        ->and($discovery[1]['created'])->toBe(0)
        ->and($discovery[1]['skipped_not_matching_query'])->toBe(2)
        ->and($discovery[1]['discovery_batch_id'])->toBe($discovery[0]['discovery_batch_id']);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(JobLead::query()->where('user_id', $user->id)->sole()->job_title)->toBe('Senior Laravel Engineer');
});

it('keeps duplicate handling working when discovery uses a search query', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'source_url' => 'https://acme.example.com/jobs/senior-laravel-engineer',
        'normalized_source_url' => 'https://acme.example.com/jobs/senior-laravel-engineer',
        'source_host' => 'acme.example.com',
    ]);

    JobLead::factory()->for($otherUser)->saved()->create([
        'source_url' => 'https://acme.example.com/jobs/senior-laravel-engineer',
        'normalized_source_url' => 'https://acme.example.com/jobs/senior-laravel-engineer',
        'source_host' => 'acme.example.com',
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'), [
            'search_query' => 'Laravel remote',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.no_new_jobs_found'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(JobLead::query()->where('user_id', $otherUser->id)->count())->toBe(1);
});

it('supports alias-based discovery queries for imported jobs', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'), [
            'search_query' => 'vuejs remoto',
        ])
        ->assertRedirect(route('job-leads.index'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(JobLead::query()->where('user_id', $user->id)->sole()->job_title)->toBe('Senior Laravel Engineer');
});

it('keeps the discovery search query in flash after redirect', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('job-leads.discover'), [
            'search_query' => 'Laravel remote',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.discovery_search_query', 'Laravel remote')
        );
});

it('shows newly created jobs in latest discovery view even when conflicting filters are requested', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    config()->set('job_discovery.supported_sources', [
        'larajobs',
    ]);

    Http::fake([
        'https://larajobs.com/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/larajobs_listing.html')),
            200,
        ),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'), [
            'search_query' => 'javascript',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.new_jobs_found_single'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->last_discovery_batch_id)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertDontSee('Bright Studio');

    $this->actingAs($user)
        ->get(route('job-leads.index', [
            'discovery_batch' => 'latest',
            'location_scope' => JobLead::LOCATION_SCOPE_BRAZIL,
            'search' => 'No matching term',
            'lead_status' => JobLead::STATUS_IGNORED,
            'analysis_readiness' => JobLead::ANALYSIS_READINESS_NEEDS_DESCRIPTION,
            'analysis_state' => JobLead::ANALYSIS_STATE_MISSING,
            'show_ignored' => 0,
            'work_mode' => JobLead::WORK_MODE_ONSITE,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('isLatestDiscoveryView', true)
            ->where('filters.discovery_batch', 'latest')
            ->where('filters.location_scope', JobLead::LOCATION_SCOPE_ALL)
            ->where('filters.search', '')
            ->where('filters.lead_status', '')
            ->where('filters.analysis_readiness', '')
            ->where('filters.analysis_state', '')
            ->where('filters.work_mode', '')
            ->where('filters.show_ignored', true)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Bright Studio')
            ->where('matchedJobs.0.location_classification', JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL)
        )
        ->assertSee('Bright Studio');
});
