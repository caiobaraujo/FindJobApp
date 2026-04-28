<?php

use App\Models\JobLead;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('discovers python job board leads for a selected user and prints a useful summary', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'python-job-board',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 2')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $this->assertDatabaseCount('job_leads', 2);

    $readyLead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://acme.example.com/jobs/senior-laravel-engineer')
        ->sole();

    expect($readyLead->lead_status)->toBe(JobLead::STATUS_SAVED)
        ->and($readyLead->source_type)->toBe(JobLead::SOURCE_TYPE_JOB_BOARD)
        ->and($readyLead->job_title)->toBe('Senior Laravel Engineer')
        ->and($readyLead->company_name)->toBe('Acme Labs')
        ->and($readyLead->location)->toBe('Remote, United States')
        ->and($readyLead->extracted_keywords)->toContain('laravel')
        ->and($readyLead->hasLimitedAnalysis())->toBeFalse();

    $limitedLead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://beta.example.com/jobs/python-support-engineer')
        ->sole();

    expect($limitedLead->lead_status)->toBe(JobLead::STATUS_SAVED)
        ->and($limitedLead->job_title)->toBe('Python Support Engineer')
        ->and($limitedLead->company_name)->toBe('Beta Systems')
        ->and($limitedLead->location)->toBe('Lisbon, Portugal')
        ->and($limitedLead->extracted_keywords)->toBe([])
        ->and($limitedLead->hasLimitedAnalysis())->toBeTrue();
});

it('prints verbose parser diagnostics for the listing page', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'python-job-board',
        '--verbose' => true,
    ])
        ->expectsOutput('Listing HTTP status: 200')
        ->expectsOutput('Candidate links found: 3')
        ->expectsOutput('Parsed jobs after filtering: 2')
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 2')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);
});

it('warns clearly when the listing fetch succeeds but no valid jobs are parsed', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://www.python.org/jobs/' => Http::response('<html><body><h1>0 jobs on the Python Job Board</h1></body></html>', 200),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'python-job-board',
    ])
        ->expectsOutput('No valid jobs were parsed from the listing page.')
        ->expectsOutput('Fetched: 0')
        ->expectsOutput('Created: 0')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);
});

it('skips discovered duplicates using normalized source urls', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'source_url' => 'https://acme.example.com/jobs/senior-laravel-engineer',
        'normalized_source_url' => 'https://acme.example.com/jobs/senior-laravel-engineer',
        'source_host' => 'acme.example.com',
    ]);

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'python-job-board',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 1')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $this->assertDatabaseCount('job_leads', 2);
});

it('fails gracefully for an invalid user id', function (): void {
    $this->artisan('job-leads:discover', [
        'user_id' => 999999,
        'source' => 'python-job-board',
    ])
        ->expectsOutput('User not found.')
        ->assertExitCode(1);
});

it('reports listing fetch failures honestly', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://www.python.org/jobs/' => Http::response('Upstream error', 500),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'python-job-board',
    ])
        ->expectsOutput('Failed to fetch the Python Job Board listing page (HTTP 500).')
        ->assertExitCode(1);
});

it('supports an optional query filter for discovery imports', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://www.python.org/jobs/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html')), 200),
        'https://www.python.org/jobs/1001/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_ready.html')), 200),
        'https://www.python.org/jobs/1002/' => Http::response(file_get_contents(base_path('tests/Fixtures/python_job_board_detail_limited.html')), 200),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'python-job-board',
        '--query' => 'remoto vuejs',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Skipped not matching query: 1')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $this->assertDatabaseCount('job_leads', 1);
});
