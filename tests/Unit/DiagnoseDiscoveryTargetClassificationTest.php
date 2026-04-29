<?php

use App\Console\Commands\DiagnoseDiscovery;

it('classifies strong company career page targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 5,
        'imported' => 4,
        'skipped_by_query' => 5,
        'hidden_by_default' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'strong',
        'action' => 'keep strong targets',
    ]);
});

it('classifies promising company career page targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 2,
        'imported' => 2,
        'skipped_by_query' => 8,
        'hidden_by_default' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'promising',
        'action' => 'review promising targets',
    ]);
});

it('classifies weak company career page targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 1,
        'imported' => 1,
        'skipped_by_query' => 9,
        'hidden_by_default' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'weak',
        'action' => 'deprioritize weak targets',
    ]);
});

it('classifies no signal company career page targets deterministically', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 0,
        'imported' => 0,
        'skipped_by_query' => 10,
        'hidden_by_default' => 0,
    ]);

    expect($classification)->toBe([
        'bucket' => 'no-signal',
        'action' => 'investigate no-signal targets',
    ]);
});

it('does not classify hidden high volume targets as strong', function (): void {
    $classification = app(DiagnoseDiscovery::class)->classifyCompanyCareerTarget([
        'fetched_candidates' => 10,
        'matched_candidates' => 5,
        'imported' => 4,
        'skipped_by_query' => 5,
        'hidden_by_default' => 1,
    ]);

    expect($classification)->toBe([
        'bucket' => 'promising',
        'action' => 'review promising targets',
    ]);
});
