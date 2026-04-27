<?php

use App\Models\JobLead;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('discovers django community job leads for a selected user', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://www.djangoproject.com/community/jobs/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'django-community-jobs',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 2')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $this->assertDatabaseCount('job_leads', 2);

    $baserowLead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://builtwithdjango.com/jobs/2379/product-engineer')
        ->sole();

    expect($baserowLead->lead_status)->toBe(JobLead::STATUS_SAVED)
        ->and($baserowLead->job_title)->toBe('Product Engineer')
        ->and($baserowLead->company_name)->toBe('Baserow')
        ->and($baserowLead->location)->toBeNull()
        ->and($baserowLead->extracted_keywords)->not->toBe([])
        ->and($baserowLead->hasLimitedAnalysis())->toBeFalse();
});

it('skips discovered duplicates from django community jobs using normalized source urls', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'source_url' => 'https://builtwithdjango.com/jobs/2379/product-engineer',
        'normalized_source_url' => 'https://builtwithdjango.com/jobs/2379/product-engineer',
        'source_host' => 'builtwithdjango.com',
    ]);

    Http::fake([
        'https://www.djangoproject.com/community/jobs/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/django_community_jobs_listing.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'django-community-jobs',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 1')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $this->assertDatabaseCount('job_leads', 2);
});
