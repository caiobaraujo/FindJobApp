<?php

use App\Services\JobDiscovery\PythonJobBoardDiscoverySource;

it('extracts job urls from a python job board listing fixture', function (): void {
    $html = file_get_contents(base_path('tests/Fixtures/python_job_board_listing.html'));

    expect($html)->not->toBeFalse();

    $parsed = app(PythonJobBoardDiscoverySource::class)->parseListingHtmlWithDiagnostics($html);
    $entries = $parsed['entries'];

    expect($entries)->toHaveCount(2)
        ->and($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(1)
        ->and($entries[0]['detail_url'])->toBe('https://www.python.org/jobs/1001/')
        ->and($entries[1]['detail_url'])->toBe('https://www.python.org/jobs/1002/')
        ->and($entries[0]['company_name'])->toBe('Acme Labs')
        ->and($entries[1]['location'])->toBe('Lisbon, Portugal');
});
