<?php

$useFixtureResponses = (bool) env('JOB_DISCOVERY_USE_FIXTURES', false);
$enableBrazilianTechJobBoards = (bool) env('JOB_DISCOVERY_ENABLE_BRAZILIAN_TECH_JOB_BOARDS', env('APP_ENV', 'production') === 'local');

$fixtureCompanyCareerTargets = [
    [
        'name' => 'Nubank',
        'website_url' => 'https://nubank.com.br',
        'region' => 'São Paulo',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.nubank.com.br/careers',
        ],
    ],
    [
        'name' => 'iFood',
        'website_url' => 'https://ifood.com.br',
        'region' => 'Brazil',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.ifood.com.br/carreiras',
        ],
    ],
    [
        'name' => 'Mercado Livre',
        'website_url' => 'https://mercadolivre.com.br',
        'region' => 'Brazil',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.mercadolivre.com.br/careers',
        ],
    ],
    [
        'name' => 'VTEX',
        'website_url' => 'https://vtex.com',
        'region' => 'São Paulo',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.vtex.com/careers',
        ],
    ],
    [
        'name' => 'Stone',
        'website_url' => 'https://stone.com.br',
        'region' => 'Brazil',
        'parser_strategy' => 'ats_board',
        'career_urls' => [
            'https://fixtures.stone.com.br/careers',
        ],
    ],
    [
        'name' => 'PagBank',
        'website_url' => 'https://pagbank.com.br',
        'region' => 'São Paulo',
        'parser_strategy' => 'ats_board',
        'career_urls' => [
            'https://fixtures.pagbank.com.br/careers',
        ],
    ],
    [
        'name' => 'Hotmart',
        'website_url' => 'https://hotmart.com',
        'region' => 'Belo Horizonte',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.hotmart.com/jobs',
        ],
    ],
    [
        'name' => 'QuintoAndar',
        'website_url' => 'https://quintoandar.com.br',
        'region' => 'São Paulo',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.quintoandar.com.br/carreiras',
        ],
    ],
    [
        'name' => 'Grupo OLX',
        'website_url' => 'https://olxbrasil.com.br',
        'region' => 'Brazil',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.olx.com.br/vagas',
        ],
    ],
    [
        'name' => 'Magazine Luiza',
        'website_url' => 'https://magazineluiza.com.br',
        'region' => 'Brazil',
        'parser_strategy' => 'structured_lists',
        'career_urls' => [
            'https://fixtures.magalu.com.br/carreiras',
        ],
    ],
];

$fixtureBrazilianTechJobBoardTargets = [
    [
        'platform' => 'programathor',
        'name' => 'ProgramaThor',
        'parser_strategy' => 'programathor_cards',
        'listing_urls' => [
            'https://fixtures.programathor.com.br/jobs',
        ],
    ],
    [
        'platform' => 'remotar',
        'name' => 'Remotar',
        'parser_strategy' => 'remotar_cards',
        'listing_urls' => [
            'https://fixtures.remotar.com.br/vagas-tecnologia-remoto',
        ],
    ],
];

return [
    'fixture_supported_sources' => ['larajobs', 'company-career-pages'],
    'supported_sources' => $useFixtureResponses
        ? ['larajobs', 'company-career-pages']
        : array_values(array_filter([
            'python-job-board',
            'django-community-jobs',
            'we-work-remotely',
            'larajobs',
            'company-career-pages',
            $enableBrazilianTechJobBoards ? 'brazilian-tech-job-boards' : null,
        ])),
    'use_fixture_responses' => $useFixtureResponses,
    'fixture_company_career_targets' => $fixtureCompanyCareerTargets,
    'fixture_brazilian_tech_job_board_targets' => $fixtureBrazilianTechJobBoardTargets,
    'fixture_responses' => [
        'larajobs' => base_path('tests/Fixtures/larajobs_listing.html'),
        'company_career_pages' => [
            'https://fixtures.nubank.com.br/careers' => base_path('tests/Fixtures/company_career_page_brazil_strong.html'),
            'https://fixtures.ifood.com.br/carreiras' => base_path('tests/Fixtures/company_career_page_brazil_promising.html'),
            'https://fixtures.mercadolivre.com.br/careers' => base_path('tests/Fixtures/company_career_page_brazil_weak.html'),
            'https://fixtures.vtex.com/careers' => base_path('tests/Fixtures/company_career_page_generic.html'),
            'https://fixtures.stone.com.br/careers' => base_path('tests/Fixtures/company_career_page_gupy_multi.html'),
            'https://fixtures.pagbank.com.br/careers' => base_path('tests/Fixtures/company_career_page_gupy_multi_two.html'),
            'https://fixtures.hotmart.com/jobs' => base_path('tests/Fixtures/company_career_page_brazil_promising.html'),
            'https://fixtures.quintoandar.com.br/carreiras' => base_path('tests/Fixtures/company_career_page_brazil_strong.html'),
            'https://fixtures.olx.com.br/vagas' => base_path('tests/Fixtures/company_career_page_brazil_weak.html'),
            'https://fixtures.magalu.com.br/carreiras' => base_path('tests/Fixtures/company_career_page_generic.html'),
        ],
        'brazilian_tech_job_boards' => [
            'https://fixtures.programathor.com.br/jobs' => base_path('tests/Fixtures/brazilian_tech_job_boards_programathor.html'),
            'https://fixtures.remotar.com.br/vagas-tecnologia-remoto' => base_path('tests/Fixtures/brazilian_tech_job_boards_remotar.html'),
        ],
    ],
    'company_career_targets' => $useFixtureResponses ? $fixtureCompanyCareerTargets : [
        [
            'name' => 'Nubank',
            'website_url' => 'https://nubank.com.br',
            'region' => 'São Paulo',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://international.nubank.com.br/careers/',
            ],
        ],
        [
            'name' => 'iFood',
            'website_url' => 'https://ifood.com.br',
            'region' => 'Brazil',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://institucional.ifood.com.br/comunidade/carreiras/',
            ],
        ],
        [
            'name' => 'Mercado Livre',
            'website_url' => 'https://mercadolivre.com.br',
            'region' => 'Brazil',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://careers-meli.mercadolibre.com/en',
            ],
        ],
        [
            'name' => 'VTEX',
            'website_url' => 'https://vtex.com',
            'region' => 'São Paulo',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://vtex.com/us-en/about-us/',
            ],
        ],
        [
            'name' => 'Stone',
            'website_url' => 'https://stone.com.br',
            'region' => 'Brazil',
            'parser_strategy' => 'ats_board',
            'career_urls' => [
                'https://jornadastone.gupy.io/',
            ],
        ],
        [
            'name' => 'PagBank',
            'website_url' => 'https://pagbank.com.br',
            'region' => 'São Paulo',
            'parser_strategy' => 'ats_board',
            'career_urls' => [
                'https://pagseguro.gupy.io/',
            ],
        ],
        [
            'name' => 'Hotmart',
            'website_url' => 'https://hotmart.com',
            'region' => 'Belo Horizonte',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://hotmart.com/en/jobs',
            ],
        ],
        [
            'name' => 'QuintoAndar',
            'website_url' => 'https://quintoandar.com.br',
            'region' => 'São Paulo',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://carreiras.quintoandar.com.br/home/trabalho/',
            ],
        ],
        [
            'name' => 'Grupo OLX',
            'website_url' => 'https://olxbrasil.com.br',
            'region' => 'Brazil',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://olxbrasil.com.br/vagas/',
            ],
        ],
        [
            'name' => 'Magazine Luiza',
            'website_url' => 'https://magazineluiza.com.br',
            'region' => 'Brazil',
            'parser_strategy' => 'structured_lists',
            'career_urls' => [
                'https://carreiras.magazineluiza.com.br/vagas/',
            ],
        ],
    ],
    'brazilian_tech_job_board_targets' => $useFixtureResponses ? $fixtureBrazilianTechJobBoardTargets : [
        [
            'platform' => 'programathor',
            'name' => 'ProgramaThor',
            'parser_strategy' => 'programathor_cards',
            'listing_urls' => [
                'https://programathor.com.br/jobs',
            ],
        ],
        [
            'platform' => 'remotar',
            'name' => 'Remotar',
            'parser_strategy' => 'remotar_cards',
            'listing_urls' => [
                'https://blog.remotar.com.br/tag/vagas-tecnologia/',
            ],
        ],
    ],
];
