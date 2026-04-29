<?php

use App\Services\TechnicalKeywordDisplay;

it('formats canonical technical keywords with user friendly labels', function (): void {
    $display = new TechnicalKeywordDisplay();

    expect($display->displayKeywords([
        'ci_cd',
        'nodejs',
        'rest_api',
        'graphql',
        'postgresql',
        'mysql',
        'machine_learning',
        'github_actions',
    ]))->toBe([
        'CI/CD',
        'Node.js',
        'REST API',
        'GraphQL',
        'PostgreSQL',
        'MySQL',
        'Machine Learning',
        'GitHub Actions',
    ]);
});

it('suppresses redundant generic technical labels when a more specific phrase is present', function (): void {
    $display = new TechnicalKeywordDisplay();

    expect($display->displayKeywords([
        'data_engineering',
        'data',
        'rest_api',
        'api',
        'sql',
    ]))->toBe([
        'Data Engineering',
        'REST API',
        'SQL',
    ]);
});
