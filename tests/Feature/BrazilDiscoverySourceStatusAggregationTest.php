<?php

use App\Services\JobDiscovery\BrazilianTechJobBoardsDiscoverySource;
use App\Services\JobDiscovery\CompanyCareerPagesDiscoverySource;
use App\Services\JobDiscovery\GupyPublicJobsDiscoverySource;
use Illuminate\Support\Facades\Http;

it('keeps a successful company career source status when one curated target returns 404', function (): void {
    config()->set('job_discovery.use_fixture_responses', false);
    config()->set('job_discovery.company_career_targets', [
        [
            'name' => 'Broken',
            'career_urls' => ['https://broken.example/careers'],
        ],
        [
            'name' => 'Healthy',
            'region' => 'Brazil',
            'career_urls' => ['https://healthy.example/careers'],
        ],
    ]);

    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_vtex_technology.html');

    expect($html)->not->toBeFalse();

    Http::fake([
        'https://broken.example/careers' => Http::response('missing', 404),
        'https://healthy.example/careers' => Http::response($html, 200),
    ]);

    $listing = app(CompanyCareerPagesDiscoverySource::class)->discoverEntriesWithDiagnostics();

    expect($listing['status_code'])->toBe(200)
        ->and($listing['parsed_jobs'])->toBe(2);
});

it('keeps a successful brazilian tech boards source status when one curated target returns 404', function (): void {
    config()->set('job_discovery.use_fixture_responses', false);
    config()->set('job_discovery.brazilian_tech_job_board_targets', [
        [
            'platform' => 'programathor',
            'name' => 'ProgramaThor',
            'parser_strategy' => 'programathor_cards',
            'listing_urls' => ['https://broken.example/jobs'],
        ],
        [
            'platform' => 'remotar',
            'name' => 'Remotar',
            'parser_strategy' => 'remotar_cards',
            'listing_urls' => ['https://remotar.com.br/vagas-tecnologia-remoto'],
        ],
    ]);

    $html = file_get_contents(__DIR__.'/../Fixtures/brazilian_tech_job_boards_remotar.html');

    expect($html)->not->toBeFalse();

    Http::fake([
        'https://broken.example/jobs' => Http::response('missing', 404),
        'https://remotar.com.br/vagas-tecnologia-remoto' => Http::response($html, 200),
    ]);

    $listing = app(BrazilianTechJobBoardsDiscoverySource::class)->discoverEntriesWithDiagnostics();

    expect($listing['status_code'])->toBe(200)
        ->and($listing['parsed_jobs'])->toBe(2)
        ->and(collect($listing['targets'])->sum('failed'))->toBe(1);
});

it('keeps a successful gupy source status when one curated target returns 404', function (): void {
    config()->set('job_discovery.use_fixture_responses', false);
    config()->set('job_discovery.gupy_public_job_targets', [
        [
            'name' => 'Broken',
            'listing_url' => 'https://broken.gupy.io/',
        ],
        [
            'name' => 'Healthy',
            'listing_url' => 'https://healthy.gupy.io/',
        ],
    ]);

    $html = file_get_contents(__DIR__.'/../Fixtures/gupy_public_jobs_positivo_listing.html');

    expect($html)->not->toBeFalse();

    Http::fake([
        'https://broken.gupy.io/' => Http::response('missing', 404),
        'https://healthy.gupy.io/' => Http::response($html, 200),
    ]);

    $listing = app(GupyPublicJobsDiscoverySource::class)->discoverEntriesWithDiagnostics();

    expect($listing['status_code'])->toBe(200)
        ->and($listing['parsed_jobs'])->toBe(1)
        ->and(collect($listing['targets'])->sum('failed'))->toBe(1);
});
