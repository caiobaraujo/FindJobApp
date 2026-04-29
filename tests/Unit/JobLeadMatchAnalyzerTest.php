<?php

use App\Services\JobLeadKeywordExtractor;
use App\Services\JobLeadMatchAnalyzer;

it('returns matched and missing keywords correctly', function (): void {
    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['laravel', 'vue', 'aws', 'sql'],
        'Laravel engineer with Vue and SQL delivery experience.',
        ['Product thinking', 'Communication'],
    );

    expect($analysis['matched_keywords'])->toBe(['laravel', 'vue', 'sql']);
    expect($analysis['missing_keywords'])->toBe(['aws']);
    expect($analysis['match_summary'])->toBe('Matched 3 keywords and missing 1.');
});

it('keeps missing keywords focused on meaningful technical terms only', function (): void {
    $keywords = app(JobLeadKeywordExtractor::class)->extractKeywords(
        'We need a Laravel engineer with Vue, Docker, and good team experience.',
    );

    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
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

it('matches against canonical resume signals so javascript and nodejs are not missing when derived from node vue and angular', function (): void {
    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['javascript', 'nodejs', 'vue', 'angular', 'go'],
        'Full stack engineer with Node.js services, Vue.js dashboards, Angular admin flows, Python APIs, Django, PHP, Laravel, SQL, and MySQL.',
        [],
    );

    expect($analysis['matched_keywords'])->toBe(['javascript', 'nodejs', 'vue', 'angular'])
        ->and($analysis['missing_keywords'])->toBe(['go']);
});

it('does not match go from ordinary english resume text without explicit golang evidence', function (): void {
    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['go', 'python'],
        'We go above and beyond to support Python services and Django APIs.',
        ['Python', 'Django'],
    );

    expect($analysis['matched_keywords'])->toBe(['python'])
        ->and($analysis['missing_keywords'])->toBe(['go']);
});

it('matches go only when the resume explicitly contains go or golang in technical context', function (): void {
    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['go', 'kubernetes', 'ci_cd'],
        'Backend engineer with Golang microservices, Kubernetes, and CI/CD delivery experience.',
        [],
    );

    expect($analysis['matched_keywords'])->toBe(['go', 'kubernetes', 'ci_cd'])
        ->and($analysis['missing_keywords'])->toBe([]);
});

it('keeps rich backend and qa descriptions focused on strong technical signals', function (): void {
    $backendAnalysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['python', 'django', 'postgresql', 'rest_api', 'api'],
        'Python Django engineer with PostgreSQL and REST API experience.',
        [],
    );

    $qaAnalysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['python', 'qa', 'pytest', 'selenium', 'postman', 'rest_api', 'api'],
        'QA engineer with Python, Pytest, Selenium, REST API validation, and Postman.',
        [],
    );

    expect($backendAnalysis['matched_keywords'])->toBe(['python', 'django', 'postgresql', 'rest_api'])
        ->and($backendAnalysis['missing_keywords'])->toBe([])
        ->and($qaAnalysis['matched_keywords'])->toBe(['python', 'qa', 'pytest', 'selenium', 'postman', 'rest_api'])
        ->and($qaAnalysis['missing_keywords'])->toBe([]);
});

it('prefers specific data terms over generic parent terms in match explanations', function (): void {
    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['data_engineering', 'data', 'sql'],
        'Data engineering specialist with SQL pipeline experience.',
        [],
    );

    expect($analysis['matched_keywords'])->toBe(['data_engineering', 'sql'])
        ->and($analysis['missing_keywords'])->toBe([]);
});

it('does not promote api and data only descriptions into misleading match explanations', function (): void {
    $analysis = app(JobLeadMatchAnalyzer::class)->analyze(
        ['api', 'data', 'cloud', 'backend'],
        'Python engineer with API and data pipeline experience.',
        [],
    );

    expect($analysis['matched_keywords'])->toBe([])
        ->and($analysis['missing_keywords'])->toBe([])
        ->and($analysis['match_summary'])->toBe('No strong technical job keywords are available yet for matching.');
});
