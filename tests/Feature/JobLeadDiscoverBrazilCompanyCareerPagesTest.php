<?php

use App\Models\JobLead;
use App\Models\User;

function useBrazilCompanyCareerFixtures(): void
{
    config()->set('job_discovery.use_fixture_responses', true);
    config()->set('job_discovery.supported_sources', ['company-career-pages']);
    config()->set('job_discovery.company_career_targets', config('job_discovery.fixture_company_career_targets'));
}

it('imports deterministic leads from the curated brazilian company career page fixtures', function (): void {
    useBrazilCompanyCareerFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'company-career-pages',
    ])
        ->expectsOutput('Fetched: 18')
        ->expectsOutput('Created: 18')
        ->expectsOutput('Duplicates skipped: 0')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    $countsByCompany = JobLead::query()
        ->where('user_id', $user->id)
        ->selectRaw('company_name, count(*) as aggregate_count')
        ->groupBy('company_name')
        ->pluck('aggregate_count', 'company_name')
        ->all();

    expect($countsByCompany)->toMatchArray([
        'Grupo OLX' => 1,
        'Hotmart' => 2,
        'Mercado Livre' => 1,
        'Nubank' => 3,
        'PagBank' => 3,
        'QuintoAndar' => 3,
        'Stone' => 3,
        'iFood' => 2,
    ]);

    expect(JobLead::query()->where('user_id', $user->id)->where('company_name', 'VTEX')->count())->toBe(0)
        ->and(JobLead::query()->where('user_id', $user->id)->where('company_name', 'Magazine Luiza')->count())->toBe(0);
});

it('deduplicates deterministic leads across repeated curated brazilian company career page runs', function (): void {
    useBrazilCompanyCareerFixtures();

    $user = User::factory()->create();

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'company-career-pages',
    ])->assertExitCode(0);

    $this->artisan('job-leads:discover', [
        'user_id' => $user->id,
        'source' => 'company-career-pages',
    ])
        ->expectsOutput('Fetched: 18')
        ->expectsOutput('Created: 0')
        ->expectsOutput('Duplicates skipped: 18')
        ->expectsOutput('Invalid skipped: 0')
        ->expectsOutput('Failed: 0')
        ->assertExitCode(0);

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(18);
});
