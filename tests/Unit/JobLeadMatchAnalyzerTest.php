<?php

use App\Services\JobLeadKeywordExtractor;
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

it('keeps missing keywords focused on meaningful technical terms only', function (): void {
    $keywords = (new JobLeadKeywordExtractor())->extractKeywords(
        'We need a Laravel engineer with Vue, Docker, and good team experience.',
    );

    $analysis = (new JobLeadMatchAnalyzer())->analyze(
        $keywords,
        'Laravel engineer with Vue experience.',
        ['Laravel', 'Vue'],
    );

    expect($analysis['matched_keywords'])->toContain('laravel');
    expect($analysis['matched_keywords'])->toContain('vue');
    expect($analysis['missing_keywords'])->toContain('docker');
    expect($analysis['missing_keywords'])->not->toContain('good');
    expect($analysis['missing_keywords'])->not->toContain('team');
    expect($analysis['missing_keywords'])->not->toContain('experience');
});
