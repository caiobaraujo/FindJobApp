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
        ->assertSessionHas('success', __('app.job_discovery.summary', [
            'fetched' => 6,
            'created' => 4,
            'duplicates' => 0,
            'skipped_not_matching_query' => 0,
            'invalid' => 2,
            'failed' => 0,
        ]));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(4)
        ->and(JobLead::query()->where('user_id', $otherUser->id)->count())->toBe(0);

    $userProfile->refresh();
    $otherUserProfile->refresh();

    expect($userProfile->last_discovered_at)->not->toBeNull()
        ->and($userProfile->last_discovered_new_count)->toBe(4)
        ->and($otherUserProfile->last_discovered_at)->toBeNull()
        ->and($otherUserProfile->last_discovered_new_count)->toBeNull();
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

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'))
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.summary', [
            'fetched' => 6,
            'created' => 0,
            'duplicates' => 4,
            'skipped_not_matching_query' => 0,
            'invalid' => 2,
            'failed' => 0,
        ]));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->last_discovered_at)->not->toBeNull()
        ->and($userProfile->last_discovered_new_count)->toBe(0);
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
        ->assertSessionHas('success', __('app.job_discovery.summary', [
            'fetched' => 3,
            'created' => 2,
            'duplicates' => 0,
            'skipped_not_matching_query' => 0,
            'invalid' => 1,
            'failed' => 1,
        ]))
        ->assertSessionHas('discovery', [
            [
                'source' => 'python-job-board',
                'fetched' => 0,
                'created' => 0,
                'duplicates' => 0,
                'skipped_not_matching_query' => 0,
                'invalid' => 0,
                'failed' => 1,
                'query_used' => false,
            ],
            [
                'source' => 'django-community-jobs',
                'fetched' => 3,
                'created' => 2,
                'duplicates' => 0,
                'skipped_not_matching_query' => 0,
                'invalid' => 1,
                'failed' => 0,
                'query_used' => false,
            ],
        ]);

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
        ->assertSessionHas('success', __('app.job_discovery.summary', [
            'fetched' => 6,
            'created' => 1,
            'duplicates' => 0,
            'skipped_not_matching_query' => 3,
            'invalid' => 2,
            'failed' => 0,
        ]))
        ->assertSessionHas('discovery', [
            [
                'source' => 'python-job-board',
                'fetched' => 3,
                'created' => 1,
                'duplicates' => 0,
                'skipped_not_matching_query' => 1,
                'invalid' => 1,
                'failed' => 0,
                'query_used' => true,
            ],
            [
                'source' => 'django-community-jobs',
                'fetched' => 3,
                'created' => 0,
                'duplicates' => 0,
                'skipped_not_matching_query' => 2,
                'invalid' => 1,
                'failed' => 0,
                'query_used' => true,
            ],
        ]);

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
        ->assertSessionHas('success', __('app.job_discovery.summary', [
            'fetched' => 6,
            'created' => 0,
            'duplicates' => 1,
            'skipped_not_matching_query' => 3,
            'invalid' => 2,
            'failed' => 0,
        ]));

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
