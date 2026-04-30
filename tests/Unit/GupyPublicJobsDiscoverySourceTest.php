<?php

use App\Models\JobLead;
use App\Services\JobDiscovery\GupyPublicJobsDiscoverySource;

it('extracts valid gupy jobs from a curated listing fixture and skips expired cards', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/gupy_public_jobs_afya_listing.html');

    expect($html)->not->toBeFalse();

    $parsed = app(GupyPublicJobsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html, [
        'listing_url' => 'https://afya.gupy.io/',
        'target_name' => 'Afya',
        'company_name' => 'Afya',
        'platform' => 'gupy',
        'parser_strategy' => 'gupy_listing',
    ]);

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['skipped_expired'])->toBe(1)
        ->and($parsed['skipped_missing_company'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(2)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://afya.gupy.io/jobs/71001')
        ->and($parsed['entries'][0]['company_name'])->toBe('Afya')
        ->and($parsed['entries'][0]['job_title'])->toBe('Pessoa Engenheira Python Django')
        ->and($parsed['entries'][0]['work_mode'])->toBe(JobLead::WORK_MODE_REMOTE)
        ->and($parsed['entries'][1]['job_title'])->toBe('Frontend React Engineer');
});

it('skips gupy listing entries when the curated target does not provide a safe company name', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/gupy_public_jobs_mystery_listing.html');

    expect($html)->not->toBeFalse();

    $parsed = app(GupyPublicJobsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html, [
        'listing_url' => 'https://mystery.gupy.io/',
        'target_name' => null,
        'company_name' => null,
        'platform' => 'gupy',
        'parser_strategy' => 'gupy_listing',
    ]);

    expect($parsed['candidate_links'])->toBe(1)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['skipped_expired'])->toBe(0)
        ->and($parsed['skipped_missing_company'])->toBe(1)
        ->and($parsed['entries'])->toBe([]);
});

it('enriches a gupy backend detail page with rich job specific text', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/gupy_public_jobs_backend_python_detail.html');

    expect($html)->not->toBeFalse();

    $source = new GupyPublicJobsDiscoverySource();
    $method = new ReflectionMethod($source, 'detailEntry');
    $method->setAccessible(true);

    $enriched = $method->invoke($source, $html, [
        'detail_url' => 'https://afya.gupy.io/jobs/71001',
        'job_title' => 'Pessoa Engenheira Python Django',
        'company_name' => 'Afya',
        'location' => 'Remoto, Brasil',
        'work_mode' => JobLead::WORK_MODE_REMOTE,
        'description_text' => 'Backend platform role with Python and Django.',
        'target_identifier' => 'Afya',
        'target_name' => 'Afya',
    ]);

    expect($enriched)->not->toBeNull()
        ->and($enriched['job_title'])->toBe('Pessoa Engenheira Python Django')
        ->and($enriched['company_name'])->toBe('Afya')
        ->and($enriched['location'])->toBe('Remoto, Brasil')
        ->and($enriched['work_mode'])->toBe(JobLead::WORK_MODE_REMOTE)
        ->and($enriched['description_text'])->toContain('Python')
        ->and($enriched['description_text'])->toContain('Django')
        ->and($enriched['description_text'])->toContain('PostgreSQL')
        ->and($enriched['description_text'])->toContain('REST');
});

it('extracts valid gupy jobs from additional curated fixture targets', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/gupy_public_jobs_positivo_listing.html');

    expect($html)->not->toBeFalse();

    $parsed = app(GupyPublicJobsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html, [
        'listing_url' => 'https://positivotecnologia.gupy.io/',
        'target_name' => 'Positivo Tecnologia',
        'company_name' => 'Positivo Tecnologia',
        'platform' => 'gupy',
        'parser_strategy' => 'gupy_listing',
    ]);

    expect($parsed['candidate_links'])->toBe(1)
        ->and($parsed['skipped_expired'])->toBe(0)
        ->and($parsed['skipped_missing_company'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(1)
        ->and($parsed['entries'][0]['job_title'])->toBe('Data Platform Engineer')
        ->and($parsed['entries'][0]['company_name'])->toBe('Positivo Tecnologia');
});
