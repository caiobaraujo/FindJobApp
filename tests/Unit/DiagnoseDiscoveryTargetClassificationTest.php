<?php

use App\Console\Commands\DiagnoseDiscovery;

it('classifies strong calibration targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 5,
        'imported' => 4,
        'skipped_by_query' => 5,
        'hidden_by_default' => 0,
        'failed' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'strong',
        'action' => 'keep',
    ]);
});

it('classifies promising calibration targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 2,
        'imported' => 2,
        'skipped_by_query' => 8,
        'hidden_by_default' => 1,
        'failed' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'promising',
        'action' => 'review',
    ]);
});

it('classifies weak calibration targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 1,
        'imported' => 0,
        'skipped_by_query' => 9,
        'hidden_by_default' => 0,
        'failed' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'weak',
        'action' => 'deprioritize',
    ]);
});

it('classifies no signal calibration targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 0,
        'imported' => 0,
        'skipped_by_query' => 10,
        'hidden_by_default' => 0,
        'failed' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'no-signal',
        'action' => 'investigate',
    ]);
});

it('keeps failed targets out of keep or review buckets', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 5,
        'imported' => 4,
        'skipped_by_query' => 5,
        'hidden_by_default' => 0,
        'failed' => 1,
    ]);

    expect($classification)->toBe([
        'bucket' => 'weak',
        'action' => 'investigate',
    ]);
});
