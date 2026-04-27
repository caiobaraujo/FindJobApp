<?php

use App\Services\JobDiscovery\DjangoCommunityJobsDiscoverySource;

it('extracts job urls from a django community jobs listing fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/django_community_jobs_listing.html');

    expect($html)->not->toBeFalse();

    $parsed = app(DjangoCommunityJobsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html);
    $entries = $parsed['entries'];

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(1)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]['detail_url'])->toBe('https://djangojobboard.com/1344/junior-software-developer-apprentice-ucs-assist/')
        ->and($entries[0]['job_title'])->toBe('Junior Software Developer (Apprentice)')
        ->and($entries[0]['company_name'])->toBeNull()
        ->and($entries[0]['description_text'])->toContain('Project Atlas')
        ->and($entries[1]['detail_url'])->toBe('https://builtwithdjango.com/jobs/2379/product-engineer')
        ->and($entries[1]['job_title'])->toBe('Product Engineer')
        ->and($entries[1]['company_name'])->toBe('Baserow');
});
