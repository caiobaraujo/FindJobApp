<?php

use App\Services\JobLeadKeywordExtractor;

it('extracts meaningful keywords from a job description', function (): void {
    $extractor = new JobLeadKeywordExtractor();

    $keywords = $extractor->extractKeywords(
        'We are hiring a Laravel engineer to build Laravel APIs, improve API performance, and own Vue dashboards. '
        .'The Laravel engineer will collaborate on testing and API design.',
    );

    expect($keywords)->toContain('laravel');
    expect($keywords)->toContain('api');
    expect($keywords)->toContain('laravel engineer');
});

it('filters stopwords from extracted keywords', function (): void {
    $extractor = new JobLeadKeywordExtractor();

    $keywords = $extractor->extractKeywords(
        'The team and the role are focused on the product and the platform with the best outcomes for the team.',
    );

    expect($keywords)->not->toContain('the');
    expect($keywords)->not->toContain('and');
});
