<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Http;

it('discovers we work remotely leads for a selected user', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://weworkremotely.com/categories/remote-programming-jobs.rss' => Http::response(
            file_get_contents(base_path('tests/Fixtures/we_work_remotely_programming_feed.xml')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'we-work-remotely',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 2')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $lead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://weworkremotely.com/remote-jobs/acme-remote-senior-laravel-engineer')
        ->sole();

    expect($lead->source_type)->toBe(JobLead::SOURCE_TYPE_JOB_BOARD)
        ->and($lead->job_title)->toBe('Senior Laravel Engineer')
        ->and($lead->company_name)->toBe('Acme Remote')
        ->and($lead->work_mode)->toBe(JobLead::WORK_MODE_REMOTE);
});

it('discovers remotive leads and skips duplicate urls', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'source_url' => 'https://remotive.com/remote-jobs/software-dev/staff-python-engineer-1001',
        'normalized_source_url' => 'https://remotive.com/remote-jobs/software-dev/staff-python-engineer-1001',
        'source_host' => 'remotive.com',
    ]);

    Http::fake([
        'https://remotive.com/feed' => Http::response(
            file_get_contents(base_path('tests/Fixtures/remotive_feed.xml')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'remotive',
    ])
        ->expectsOutput('Fetched: 3')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 1')
        ->expectsOutput('Invalid skipped: 1')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('discovers larajobs leads for laravel and javascript coverage', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://larajobs.com/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/larajobs_listing.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'larajobs',
    ])
        ->expectsOutput('Fetched: 4')
        ->expectsOutput('Created: 2')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 2')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $leads = JobLead::query()
        ->where('user_id', $user->id)
        ->orderBy('source_url')
        ->get();

    expect($leads)->toHaveCount(2)
        ->and($leads[0]->source_type)->toBe(JobLead::SOURCE_TYPE_JOB_BOARD)
        ->and($leads[0]->source_name)->toBe('LaraJobs')
        ->and($leads[1]->source_type)->toBe(JobLead::SOURCE_TYPE_JOB_BOARD)
        ->and($leads[1]->source_name)->toBe('LaraJobs');
});

it('imports only matching laravel jobs from larajobs when a laravel query is used', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://larajobs.com/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/larajobs_listing.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'larajobs',
        '--query' => 'laravel',
    ])
        ->expectsOutput('Fetched: 4')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Skipped not matching query: 1')
        ->expectsOutput('Invalid skipped: 2')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $lead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($lead->job_title)->toBe('Senior Laravel Engineer')
        ->and($lead->location)->toBe('Remote / Brazil');
});

it('imports only matching javascript jobs from larajobs when a javascript query is used', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://larajobs.com/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/larajobs_listing.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'larajobs',
        '--query' => 'javascript',
    ])
        ->expectsOutput('Fetched: 4')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Skipped not matching query: 1')
        ->expectsOutput('Invalid skipped: 2')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $lead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($lead->job_title)->toBe('Frontend JavaScript Engineer')
        ->and($lead->location)->toBe('Remote / LATAM');
});

it('imports software opportunities from curated company career pages', function (): void {
    $user = User::factory()->create();

    config()->set('job_discovery.company_career_targets', [
        [
            'name' => 'Example BH Tech',
            'website_url' => 'https://example.com',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://example.com/carreiras',
            ],
        ],
    ]);

    Http::fake([
        'https://example.com/carreiras' => Http::response(
            file_get_contents(base_path('tests/Fixtures/company_career_page_software.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'company-career-pages',
    ])
        ->expectsOutput('Fetched: 1')
        ->expectsOutput('Created: 1')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $lead = JobLead::query()
        ->where('user_id', $user->id)
        ->where('source_url', 'https://example.com/carreiras/backend-laravel')
        ->sole();

    expect($lead->company_name)->toBe('Example BH Tech')
        ->and($lead->location)->toBe('Belo Horizonte')
        ->and($lead->job_title)->toBe('Desenvolvedor Backend Laravel')
        ->and($lead->work_mode)->toBe(JobLead::WORK_MODE_HYBRID);
});

it('does not import generic company career pages without software role signals', function (): void {
    $user = User::factory()->create();

    config()->set('job_discovery.company_career_targets', [
        [
            'name' => 'Example BH Tech',
            'website_url' => 'https://example.com',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://example.com/trabalhe-conosco',
            ],
        ],
    ]);

    Http::fake([
        'https://example.com/trabalhe-conosco' => Http::response(
            file_get_contents(base_path('tests/Fixtures/company_career_page_generic.html')),
            200,
        ),
    ]);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'company-career-pages',
    ])
        ->expectsOutput('No valid jobs were parsed from the listing page.')
        ->expectsOutput('Fetched: 2')
        ->expectsOutput('Created: 0')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('uses the new configured sources when discovery runs with a search query', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Resume text',
        'auto_discover_jobs' => false,
    ]);

    config()->set('job_discovery.supported_sources', [
        'we-work-remotely',
        'company-career-pages',
    ]);
    config()->set('job_discovery.company_career_targets', [
        [
            'name' => 'Example BH Tech',
            'website_url' => 'https://example.com',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://example.com/carreiras',
            ],
        ],
    ]);

    Http::fake([
        'https://weworkremotely.com/categories/remote-programming-jobs.rss' => Http::response(
            file_get_contents(base_path('tests/Fixtures/we_work_remotely_programming_feed.xml')),
            200,
        ),
        'https://example.com/carreiras' => Http::response(
            file_get_contents(base_path('tests/Fixtures/company_career_page_software.html')),
            200,
        ),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'), [
            'search_query' => 'Laravel híbrido BH',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.new_jobs_found_single'))
        ->assertSessionHas('discovery_search_query', 'Laravel híbrido BH');

    $lead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($lead->source_url)->toBe('https://example.com/carreiras/backend-laravel')
        ->and($lead->location)->toBe('Belo Horizonte');
});
