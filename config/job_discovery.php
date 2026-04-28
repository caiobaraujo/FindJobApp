<?php

$useFixtureResponses = (bool) env('JOB_DISCOVERY_USE_FIXTURES', false);

$fixtureCompanyCareerTargets = [
    [
        'name' => 'Example BH Tech',
        'website_url' => 'https://example.com',
        'region' => 'Belo Horizonte',
        'career_urls' => [
            'https://example.com/carreiras',
        ],
    ],
    [
        'name' => 'Example Gupy Product',
        'website_url' => 'https://example-gupy.com',
        'region' => 'Brazil',
        'career_urls' => [
            'https://example-gupy.com/careers',
        ],
    ],
    [
        'name' => 'Example Brazil SaaS',
        'website_url' => 'https://example-saas.com',
        'region' => 'Brazil',
        'career_urls' => [
            'https://example-saas.com/careers',
        ],
    ],
];

return [
    'supported_sources' => $useFixtureResponses
        ? ['larajobs', 'company-career-pages']
        : [
            'python-job-board',
            'django-community-jobs',
            'we-work-remotely',
            'larajobs',
            'company-career-pages',
        ],
    'use_fixture_responses' => $useFixtureResponses,
    'fixture_responses' => [
        'larajobs' => base_path('tests/Fixtures/larajobs_listing.html'),
        'company_career_pages' => [
            'https://example.com/carreiras' => base_path('tests/Fixtures/company_career_page_software.html'),
            'https://example-gupy.com/careers' => base_path('tests/Fixtures/company_career_page_gupy_multi.html'),
            'https://example-saas.com/careers' => base_path('tests/Fixtures/company_career_page_gupy_multi_two.html'),
        ],
    ],
    'company_career_targets' => $useFixtureResponses ? $fixtureCompanyCareerTargets : [
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
        [
            'name' => 'Contabilizei',
            'website_url' => 'https://www.contabilizei.com.br',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://contabilizei.gupy.io/',
            ],
        ],
        [
            'name' => 'Omie',
            'website_url' => 'https://www.omie.com.br',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://carreirasomie.gupy.io/',
            ],
        ],
        [
            'name' => 'Asaas',
            'website_url' => 'https://www.asaas.com',
            'region' => 'Brazil',
            'career_urls' => [
                'https://asaas.gupy.io/',
            ],
        ],
        [
            'name' => 'Asaas Tech',
            'website_url' => 'https://www.asaas.com',
            'region' => 'Brazil',
            'career_urls' => [
                'https://asaastech.gupy.io/',
            ],
        ],
        [
            'name' => 'Gupy Tech',
            'website_url' => 'https://www.gupy.io',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://tech-career.gupy.io/',
            ],
        ],
        [
            'name' => 'Stone Tecnologia',
            'website_url' => 'https://www.stone.com.br',
            'region' => 'Brazil',
            'career_urls' => [
                'https://stech.gupy.io/',
            ],
        ],
        [
            'name' => 'Stone Banking',
            'website_url' => 'https://www.stone.com.br',
            'region' => 'Brazil',
            'career_urls' => [
                'https://stonebanking.gupy.io/',
            ],
        ],
        [
            'name' => 'Stone',
            'website_url' => 'https://www.stone.com.br',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://jornadastone.gupy.io/',
            ],
        ],
        [
            'name' => 'Trinks',
            'website_url' => 'https://www.trinks.com',
            'region' => 'Rio de Janeiro',
            'career_urls' => [
                'https://trinks.gupy.io/',
            ],
        ],
        [
            'name' => 'Leapfone',
            'website_url' => 'https://www.leapfone.com.br',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://leapfone.gupy.io/',
            ],
        ],
        [
            'name' => 'Efí Bank',
            'website_url' => 'https://sejaefi.com.br',
            'region' => 'Brazil',
            'career_urls' => [
                'https://sejaefi.gupy.io/',
            ],
        ],
        [
            'name' => 'Conta Simples',
            'website_url' => 'https://contasimples.com',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://contasimples.gupy.io/',
            ],
        ],
        [
            'name' => 'Mercos',
            'website_url' => 'https://mercos.com',
            'region' => 'Brazil',
            'career_urls' => [
                'https://mercos.gupy.io/',
            ],
        ],
        [
            'name' => 'Zenvia',
            'website_url' => 'https://www.zenvia.com',
            'region' => 'Brazil',
            'career_urls' => [
                'https://zenvia.gupy.io/',
            ],
        ],
        [
            'name' => 'Cadastra',
            'website_url' => 'https://www.cadastra.com',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://cadastra.gupy.io/',
            ],
        ],
        [
            'name' => 'MindMiners',
            'website_url' => 'https://mindminers.com',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://mindminers.gupy.io/',
            ],
        ],
        [
            'name' => 'Intelia',
            'website_url' => 'https://intelia.com.br',
            'region' => 'Brazil',
            'career_urls' => [
                'https://intelia.gupy.io/',
            ],
        ],
        [
            'name' => 'Sankhya',
            'website_url' => 'https://www.sankhya.com.br',
            'region' => 'Brazil',
            'career_urls' => [
                'https://sankhya.gupy.io/',
            ],
        ],
        [
            'name' => 'QuintoAndar',
            'website_url' => 'https://www.quintoandar.com.br',
            'region' => 'São Paulo',
            'career_urls' => [
                'https://carreiras.quintoandar.com.br/home-2/?lang=en',
            ],
        ],
    ],
];
