<?php

$useFixtureResponses = (bool) env('JOB_DISCOVERY_USE_FIXTURES', false);

return [
    'supported_sources' => $useFixtureResponses
        ? ['larajobs']
        : [
            'python-job-board',
            'django-community-jobs',
            'we-work-remotely',
            'remotive',
            'larajobs',
            'company-career-pages',
        ],
    'use_fixture_responses' => $useFixtureResponses,
    'fixture_responses' => [
        'larajobs' => base_path('tests/Fixtures/larajobs_listing.html'),
    ],
    'company_career_targets' => [
        [
            'name' => 'Meliuz',
            'website_url' => 'https://www.meliuz.com.br',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://meliuz.gupy.io/',
            ],
        ],
        [
            'name' => 'Hotmart',
            'website_url' => 'https://hotmart.com',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://hotmart.com/en/jobs/',
            ],
        ],
        [
            'name' => 'Sympla',
            'website_url' => 'https://www.sympla.com.br',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://www.sympla.com.br/index.php/trabalhe-conosco',
            ],
        ],
    ],
];
