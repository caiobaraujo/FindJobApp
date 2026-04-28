<?php

use App\Services\JobDiscovery\WeWorkRemotelyDiscoverySource;

it('extracts software jobs from the we work remotely feed fixture', function (): void {
    $xml = file_get_contents(__DIR__.'/../Fixtures/we_work_remotely_programming_feed.xml');

    expect($xml)->not->toBeFalse();

    $parsed = app(WeWorkRemotelyDiscoverySource::class)->parseFeedXmlWithDiagnostics($xml);
    $entries = $parsed['entries'];

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(2)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]['detail_url'])->toBe('https://weworkremotely.com/remote-jobs/acme-remote-senior-laravel-engineer')
        ->and($entries[0]['job_title'])->toBe('Senior Laravel Engineer')
        ->and($entries[0]['company_name'])->toBe('Acme Remote')
        ->and($entries[0]['location'])->toBe('Remote - Americas')
        ->and($entries[0]['work_mode'])->toBe('remote');
});
