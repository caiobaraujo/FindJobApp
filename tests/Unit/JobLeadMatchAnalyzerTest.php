<?php

use App\Services\JobLeadMatchAnalyzer;

it('returns matched and missing keywords correctly', function (): void {
    $analysis = (new JobLeadMatchAnalyzer())->analyze(
        ['laravel', 'vue', 'aws', 'sql'],
        'Laravel engineer with Vue and SQL delivery experience.',
        ['Product thinking', 'Communication'],
    );

    expect($analysis['matched_keywords'])->toBe(['laravel', 'vue', 'sql']);
    expect($analysis['missing_keywords'])->toBe(['aws']);
    expect($analysis['match_summary'])->toBe('Matched 3 keywords and missing 1.');
});
