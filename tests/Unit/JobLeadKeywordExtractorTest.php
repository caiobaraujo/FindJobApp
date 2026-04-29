<?php

use App\Services\JobLeadKeywordExtractor;

it('extracts meaningful keywords from a job description', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'We are hiring a Laravel engineer to build Laravel APIs, improve API performance, and own Vue dashboards. '
        .'The Laravel engineer will collaborate on testing and API design.',
    );

    expect($keywords)->toContain('laravel');
    expect($keywords)->toContain('api');
    expect($keywords)->toContain('vue');
    expect($keywords)->toContain('testing');
});

it('filters english and portuguese stopwords from extracted keywords', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'The team and the role are focused on the product and the platform with the best outcomes for the team, '
        .'com muito apoio para diversos contextos.',
    );

    expect($keywords)->not->toContain('the');
    expect($keywords)->not->toContain('and');
    expect($keywords)->not->toContain('com');
    expect($keywords)->not->toContain('muito');
    expect($keywords)->not->toContain('para');
    expect($keywords)->not->toContain('diversos');
});

it('drops page and script noise while keeping technical terms', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'https://example.com/jobs backend role primaryImage breadcrumb dataLayer gtag schema.org png '
        .'const return window document but also PHP, Laravel, Python, Django, Vue.js, MySQL, SQL, Docker, API, OpenAI, LLM, NLP.',
    );

    expect($keywords)->toContain('php');
    expect($keywords)->toContain('laravel');
    expect($keywords)->toContain('python');
    expect($keywords)->toContain('django');
    expect($keywords)->toContain('vue');
    expect($keywords)->toContain('mysql');
    expect($keywords)->toContain('sql');
    expect($keywords)->toContain('docker');
    expect($keywords)->toContain('api');
    expect($keywords)->toContain('openai');
    expect($keywords)->not->toContain('primaryimage');
    expect($keywords)->not->toContain('breadcrumb');
    expect($keywords)->not->toContain('datalayer');
    expect($keywords)->not->toContain('gtag');
    expect($keywords)->not->toContain('png');
});

it('normalizes common technical aliases to canonical keywords', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'We need a VueJS and NodeJS engineer with Open AI, AngularJS, React.js, Postgres, and fullstack experience.',
    );

    expect($keywords)->toContain('vue');
    expect($keywords)->toContain('nodejs');
    expect($keywords)->toContain('openai');
    expect($keywords)->toContain('angular');
    expect($keywords)->toContain('react');
    expect($keywords)->toContain('postgresql');
    expect($keywords)->toContain('fullstack');
    expect($keywords)->not->toContain('vuejs');
    expect($keywords)->not->toContain('angularjs');
});

it('extracts clean technical keywords from a nodejs nestjs aws backend description', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        <<<'TEXT'
We are hiring a Senior Backend Engineer to build Node.js and NestJS services on AWS.
You will design REST APIs, work with Docker and Kubernetes, and maintain PostgreSQL data flows.
The page also contains breadcrumb primaryImage contentUrl analytics gtag and raw https://tracker.example.com pixels.
TEXT,
    );

    expect($keywords)->toContain('backend');
    expect($keywords)->toContain('nodejs');
    expect($keywords)->toContain('nestjs');
    expect($keywords)->toContain('aws');
    expect($keywords)->toContain('rest_api');
    expect($keywords)->toContain('docker');
    expect($keywords)->toContain('kubernetes');
    expect($keywords)->toContain('postgresql');
    expect($keywords)->not->toContain('primaryimage');
    expect($keywords)->not->toContain('breadcrumb');
});

it('extracts useful technical terms from a golang backend description without treating ordinary go as golang', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        <<<'TEXT'
We need a backend engineer with Golang, gRPC, Kubernetes, CI/CD and microservices experience.
You will go beyond implementation details and collaborate with platform teams on cloud-native delivery.
TEXT,
    );

    expect($keywords)->toContain('go');
    expect($keywords)->toContain('microservices');
    expect($keywords)->toContain('kubernetes');
    expect($keywords)->toContain('ci_cd');
    expect($keywords)->not->toContain('go beyond');
});

it('extracts useful technical terms from php laravel and python django descriptions', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $phpKeywords = $extractor->extractKeywords(
        'Backend role with PHP, Laravel, MySQL, REST API design, Docker, and Domain-Driven Design.',
    );

    $pythonKeywords = $extractor->extractKeywords(
        'Python Django backend role building APIs with PostgreSQL, pytest, and AWS infrastructure.',
    );

    expect($phpKeywords)->toContain('php')
        ->and($phpKeywords)->toContain('laravel')
        ->and($phpKeywords)->toContain('mysql')
        ->and($phpKeywords)->toContain('rest_api')
        ->and($phpKeywords)->toContain('docker')
        ->and($phpKeywords)->toContain('ddd');

    expect($pythonKeywords)->toContain('python')
        ->and($pythonKeywords)->toContain('django')
        ->and($pythonKeywords)->toContain('backend')
        ->and($pythonKeywords)->toContain('api')
        ->and($pythonKeywords)->toContain('postgresql')
        ->and($pythonKeywords)->toContain('pytest');
});

it('extracts useful technical terms from frontend and data descriptions', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $frontendKeywords = $extractor->extractKeywords(
        'Frontend product team using React.js, TypeScript, GraphQL, Jest, Cypress and Tailwind CSS.',
    );

    $dataKeywords = $extractor->extractKeywords(
        'Data and BI role focused on SQL, ETL, Airflow, analytics dashboards, and business intelligence reporting.',
    );

    expect($frontendKeywords)->toContain('frontend')
        ->and($frontendKeywords)->toContain('react')
        ->and($frontendKeywords)->toContain('typescript')
        ->and($frontendKeywords)->toContain('graphql')
        ->and($frontendKeywords)->toContain('jest')
        ->and($frontendKeywords)->toContain('cypress')
        ->and($frontendKeywords)->toContain('tailwind');

    expect($dataKeywords)->toContain('data')
        ->and($dataKeywords)->toContain('bi')
        ->and($dataKeywords)->toContain('sql')
        ->and($dataKeywords)->toContain('etl')
        ->and($dataKeywords)->toContain('airflow');
});

it('marks generic api and data only descriptions as limited analysis', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $analysis = $extractor->analyze(
        'Data-focused role supporting API integrations, cloud collaboration, and backend automation for internal workflows.',
    );

    expect($analysis['extracted_keywords'])->toContain('data')
        ->and($analysis['extracted_keywords'])->toContain('api')
        ->and($analysis['ats_hints'][0])->toBe('Only broad technical context was found. Add the full job posting to surface stronger stack-specific signals.')
        ->and(implode(' ', $analysis['ats_hints']))->not->toContain('Likely ATS terms to reflect in your resume:');
});

it('keeps strong backend keywords while allowing specific rest api evidence to suppress generic api', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractAllKeywords(
        'Backend role with Python, Django, PostgreSQL, REST API design, and cloud integrations.',
    );

    expect($keywords)->toContain('python')
        ->and($keywords)->toContain('django')
        ->and($keywords)->toContain('postgresql')
        ->and($keywords)->toContain('rest_api')
        ->and($keywords)->toContain('api');
});

it('treats short listing style generic text as limited analysis', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $analysis = $extractor->analyze('API data role for cloud product.');

    expect($analysis['extracted_keywords'])->toContain('api')
        ->and($analysis['extracted_keywords'])->toContain('data')
        ->and($analysis['ats_hints'])->toContain('Only broad technical context was found. Add the full job posting to surface stronger stack-specific signals.')
        ->and($analysis['ats_hints'])->toContain('The description looks short. Add the full posting before tailoring your resume.');
});

it('extracts qa python automation keywords from a realistic brazilian job description', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);
    $description = file_get_contents(__DIR__.'/../Fixtures/brazilian_tech_job_boards_programathor_qa_python_detail.html');

    expect($description)->toBeString();

    $keywords = $extractor->extractAllKeywords($description);

    expect($keywords)->toContain('python')
        ->and($keywords)->toContain('qa')
        ->and($keywords)->toContain('testing')
        ->and($keywords)->toContain('pytest')
        ->and($keywords)->toContain('robot_framework')
        ->and($keywords)->toContain('selenium')
        ->and($keywords)->toContain('playwright')
        ->and($keywords)->toContain('rest_api')
        ->and($keywords)->toContain('postman')
        ->and($keywords)->toContain('git')
        ->and($keywords)->toContain('ci_cd')
        ->and($keywords)->toContain('github_actions')
        ->and($keywords)->toContain('aws')
        ->and($keywords)->toContain('microservices')
        ->and($keywords)->toContain('owasp')
        ->and($keywords)->toContain('rpa')
        ->and($keywords)->toContain('ai_ml')
        ->and($keywords)->not->toContain('javascript');
});

it('keeps rest and go context-aware instead of matching ordinary prose', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'We go above and beyond to support the team and let services rest overnight after incidents.',
    );

    expect($keywords)->not->toContain('go')
        ->and($keywords)->not->toContain('rest_api');
});

it('does not treat ordinary go as golang when nearby resume prose also mentions engineer', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'Full stack engineer with Node.js, Vue.js, Angular, Python, Django, PHP, Laravel, SQL, and MySQL experience. We go above and beyond to support the team.',
    );

    expect($keywords)->not->toContain('go')
        ->and($keywords)->toContain('nodejs')
        ->and($keywords)->toContain('vue');
});

it('keeps output deterministic unique and capped', function (): void {
    $extractor = app(JobLeadKeywordExtractor::class);

    $keywords = $extractor->extractKeywords(
        'React Vue Angular Laravel Django FastAPI NestJS PostgreSQL MySQL SQL Docker Kubernetes AWS GCP Azure GraphQL QA CI/CD.',
    );

    expect($keywords)->toBe([
        'react',
        'vue',
        'angular',
        'laravel',
        'django',
        'fastapi',
        'nestjs',
        'postgresql',
        'mysql',
        'sql',
        'docker',
        'kubernetes',
    ]);
    expect(count($keywords))->toBeLessThanOrEqual(12);
    expect($keywords)->toBe(array_values(array_unique($keywords)));
});
