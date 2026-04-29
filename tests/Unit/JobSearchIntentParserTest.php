<?php

use App\Services\JobSearchIntentParser;

it('parses python remote brazil into deterministic discovery intent', function (): void {
    $parser = app(JobSearchIntentParser::class);

    $intent = $parser->parse('python remote brazil');

    expect($intent)->toBe([
        'raw_query' => 'python remote brazil',
        'query' => 'python remote brazil',
        'keywords' => ['python'],
        'operator' => 'any',
        'location' => 'brazil',
        'work_mode' => 'remote',
    ]);
});

it('parses javascript or laravel into a basic or intent', function (): void {
    $parser = app(JobSearchIntentParser::class);

    $intent = $parser->parse('javascript or laravel');

    expect($intent)->toBe([
        'raw_query' => 'javascript or laravel',
        'query' => 'javascript laravel',
        'keywords' => ['javascript', 'laravel'],
        'operator' => 'or',
        'location' => null,
        'work_mode' => null,
    ]);
});
