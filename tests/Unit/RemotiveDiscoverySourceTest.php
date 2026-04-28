<?php

use App\Services\JobDiscovery\RemotiveDiscoverySource;

it('extracts software jobs from the remotive feed fixture', function (): void {
    $xml = file_get_contents(__DIR__.'/../Fixtures/remotive_feed.xml');

    expect($xml)->not->toBeFalse();

    $parsed = app(RemotiveDiscoverySource::class)->parseFeedXmlWithDiagnostics($xml);
    $entries = $parsed['entries'];

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(1)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]['detail_url'])->toBe('https://remotive.com/remote-jobs/software-dev/staff-python-engineer-1001')
        ->and($entries[0]['job_title'])->toBe('Staff Python Engineer')
        ->and($entries[0]['company_name'])->toBe('Orbit Labs')
        ->and($entries[0]['location'])->toBe('Remote - Worldwide')
        ->and($entries[1]['detail_url'])->toBe('https://remotive.com/remote-jobs/software-dev/frontend-engineer-1003')
        ->and($entries[1]['work_mode'])->toBe('remote');
});
