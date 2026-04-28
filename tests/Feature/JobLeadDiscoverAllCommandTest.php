<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('job_discovery.supported_sources', [
        'python-job-board',
        'django-community-jobs',
    ]);
});

it('runs discover-all for multiple users with profiles', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $ignoredUser = User::factory()->create();
    $disabledUser = User::factory()->create();

    $firstUserProfile = UserProfile::query()->create([
        'user_id' => $firstUser->id,
        'base_resume_text' => 'First user resume',
        'auto_discover_jobs' => true,
    ]);

    $secondUserProfile = UserProfile::query()->create([
        'user_id' => $secondUser->id,
        'base_resume_text' => 'Second user resume',
        'auto_discover_jobs' => true,
    ]);

    $disabledUserProfile = UserProfile::query()->create([
        'user_id' => $disabledUser->id,
        'base_resume_text' => 'Disabled user resume',
        'auto_discover_jobs' => false,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200)
            ->push(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200)
            ->push(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->artisan('job-leads:discover-all')
        ->expectsOutput(sprintf('Processing user %d', $firstUser->id))
        ->expectsOutput(sprintf('User %d source python-job-board summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $firstUser->id))
        ->expectsOutput(sprintf('User %d source django-community-jobs summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $firstUser->id))
        ->expectsOutput(sprintf('Processing user %d', $secondUser->id))
        ->expectsOutput(sprintf('User %d source python-job-board summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $secondUser->id))
        ->expectsOutput(sprintf('User %d source django-community-jobs summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $secondUser->id))
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $firstUser->id)->count())->toBe(4)
        ->and(JobLead::query()->where('user_id', $secondUser->id)->count())->toBe(4)
        ->and(JobLead::query()->where('user_id', $disabledUser->id)->count())->toBe(0)
        ->and(JobLead::query()->where('user_id', $ignoredUser->id)->count())->toBe(0);

    $firstUserProfile->refresh();
    $secondUserProfile->refresh();
    $disabledUserProfile->refresh();
    $firstUserBatchIds = JobLead::query()->where('user_id', $firstUser->id)->pluck('discovery_batch_id')->unique()->values()->all();
    $secondUserBatchIds = JobLead::query()->where('user_id', $secondUser->id)->pluck('discovery_batch_id')->unique()->values()->all();

    expect($firstUserProfile->last_discovered_at)->not->toBeNull()
        ->and($firstUserProfile->last_discovered_new_count)->toBe(4)
        ->and($firstUserProfile->last_discovery_batch_id)->not->toBeNull()
        ->and($firstUserBatchIds)->toHaveCount(1)
        ->and($firstUserBatchIds[0])->toBe($firstUserProfile->last_discovery_batch_id)
        ->and($secondUserProfile->last_discovered_at)->not->toBeNull()
        ->and($secondUserProfile->last_discovered_new_count)->toBe(4)
        ->and($secondUserProfile->last_discovery_batch_id)->not->toBeNull()
        ->and($secondUserBatchIds)->toHaveCount(1)
        ->and($secondUserBatchIds[0])->toBe($secondUserProfile->last_discovery_batch_id)
        ->and($disabledUserProfile->last_discovered_at)->toBeNull()
        ->and($disabledUserProfile->last_discovered_new_count)->toBeNull()
        ->and($disabledUserProfile->last_discovery_batch_id)->toBeNull();
});

it('keeps dedupe working when discover-all runs repeatedly', function (): void {
    $user = User::factory()->create();

    $userProfile = UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => true,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200)
            ->push(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200)
            ->push(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->artisan('job-leads:discover-all')
        ->expectsOutput(sprintf('Processing user %d', $user->id))
        ->expectsOutput(sprintf('User %d source python-job-board summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $user->id))
        ->expectsOutput(sprintf('User %d source django-community-jobs summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $user->id))
        ->assertExitCode(0);

    $this->artisan('job-leads:discover-all')
        ->expectsOutput(sprintf('Processing user %d', $user->id))
        ->expectsOutput(sprintf('User %d source python-job-board summary: fetched=3 created=0 duplicates=2 skipped=0 invalid=1 failed=0', $user->id))
        ->expectsOutput(sprintf('User %d source django-community-jobs summary: fetched=3 created=0 duplicates=2 skipped=0 invalid=1 failed=0', $user->id))
        ->assertExitCode(0);

    $userProfile->refresh();

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(4)
        ->and($userProfile->last_discovered_at)->not->toBeNull()
        ->and($userProfile->last_discovered_new_count)->toBe(0);
});

it('continues processing the next user if one discovery run fails', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstUserProfile = UserProfile::query()->create([
        'user_id' => $firstUser->id,
        'base_resume_text' => 'First user resume',
        'auto_discover_jobs' => true,
    ]);

    $secondUserProfile = UserProfile::query()->create([
        'user_id' => $secondUser->id,
        'base_resume_text' => 'Second user resume',
        'auto_discover_jobs' => true,
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::sequence()
            ->push('Upstream error', 500)
            ->push(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
        'https://www.djangoproject.com/community/jobs/' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200)
            ->push(file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')), 200),
    ]);

    $this->artisan('job-leads:discover-all')
        ->expectsOutput(sprintf('Processing user %d', $firstUser->id))
        ->expectsOutput(sprintf('User %d source python-job-board failed: Failed to fetch the Python Job Board listing page (HTTP 500).', $firstUser->id))
        ->expectsOutput(sprintf('User %d source django-community-jobs summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $firstUser->id))
        ->expectsOutput(sprintf('Processing user %d', $secondUser->id))
        ->expectsOutput(sprintf('User %d source python-job-board summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $secondUser->id))
        ->expectsOutput(sprintf('User %d source django-community-jobs summary: fetched=3 created=2 duplicates=0 skipped=0 invalid=1 failed=0', $secondUser->id))
        ->assertExitCode(0);

    $firstUserProfile->refresh();
    $secondUserProfile->refresh();

    expect(JobLead::query()->where('user_id', $firstUser->id)->count())->toBe(2)
        ->and(JobLead::query()->where('user_id', $secondUser->id)->count())->toBe(4)
        ->and($firstUserProfile->last_discovered_at)->not->toBeNull()
        ->and($firstUserProfile->last_discovered_new_count)->toBe(2)
        ->and($secondUserProfile->last_discovered_at)->not->toBeNull()
        ->and($secondUserProfile->last_discovered_new_count)->toBe(4);
});
