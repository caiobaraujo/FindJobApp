<?php

use App\Services\JobDiscovery\LaraJobsDiscoverySource;

it('extracts laravel and javascript jobs from the larajobs fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/larajobs_listing.html');

    expect($html)->not->toBeFalse();

    $parsed = app(LaraJobsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html);
    $entries = $parsed['entries'];

    expect($parsed['candidate_links'])->toBe(2)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]['detail_url'])->toBe('https://larajobs.com/jobs/acme-senior-laravel-engineer')
        ->and($entries[0]['job_title'])->toBe('Senior Laravel Engineer')
        ->and($entries[0]['company_name'])->toBe('Acme Labs')
        ->and($entries[0]['location'])->toBe('Remote / Brazil')
        ->and($entries[0]['work_mode'])->toBe('remote')
        ->and($entries[1]['detail_url'])->toBe('https://larajobs.com/jobs/bright-frontend-javascript-engineer')
        ->and($entries[1]['job_title'])->toBe('Frontend JavaScript Engineer')
        ->and($entries[1]['company_name'])->toBe('Bright Studio')
        ->and($entries[1]['location'])->toBe('Remote / LATAM');
});

it('ignores navigation and non-software links instead of counting them as invalid candidates', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/larajobs_listing_with_noise.html');

    expect($html)->not->toBeFalse();

    $parsed = app(LaraJobsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html);

    expect($parsed['candidate_links'])->toBe(2)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(2);
});
