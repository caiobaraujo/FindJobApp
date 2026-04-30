<?php

$useFixtureResponses = (bool) env('JOB_DISCOVERY_USE_FIXTURES', false);
$enableBrazilianTechJobBoards = (bool) env('JOB_DISCOVERY_ENABLE_BRAZILIAN_TECH_JOB_BOARDS', env('APP_ENV', 'production') === 'local');
$enableGupyPublicJobs = (bool) env('JOB_DISCOVERY_ENABLE_GUPY_PUBLIC_JOBS', false);

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

$fixtureGupyPublicJobTargets = [
    [
        'name' => 'Afya',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://afya.gupy.io/',
    ],
    [
        'name' => 'Omie',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://carreirasomie.gupy.io/',
    ],
    [
        'name' => 'FCamara',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://fcamara.gupy.io/',
    ],
    [
        'name' => 'Gran',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://vemsergran.gupy.io/',
    ],
    [
        'name' => 'CIGAM',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://vempracigam.gupy.io/',
    ],
    [
        'name' => 'JBS',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://grupojbs.gupy.io/',
    ],
    [
        'name' => 'Minsait',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://minsait.gupy.io/',
    ],
    [
        'name' => 'Positivo Tecnologia',
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://positivotecnologia.gupy.io/',
    ],
    [
        'name' => null,
        'parser_strategy' => 'gupy_listing',
        'listing_url' => 'https://mystery.gupy.io/',
    ],
];

return [
    'fixture_supported_sources' => ['larajobs', 'company-career-pages', 'brazilian-tech-job-boards', 'gupy-public-jobs'],
    'supported_sources' => $useFixtureResponses
        ? ['larajobs', 'company-career-pages', 'brazilian-tech-job-boards', 'gupy-public-jobs']
        : array_values(array_filter([
            'python-job-board',
            'django-community-jobs',
            'we-work-remotely',
            'larajobs',
            'company-career-pages',
            $enableBrazilianTechJobBoards ? 'brazilian-tech-job-boards' : null,
            $enableGupyPublicJobs ? 'gupy-public-jobs' : null,
        ])),
    'use_fixture_responses' => $useFixtureResponses,
    'fixture_company_career_targets' => $fixtureCompanyCareerTargets,
    'fixture_brazilian_tech_job_board_targets' => $fixtureBrazilianTechJobBoardTargets,
    'fixture_gupy_public_job_targets' => $fixtureGupyPublicJobTargets,
    'fixture_responses' => [
        'larajobs' => base_path('tests/Fixtures/larajobs_listing.html'),
        'company_career_pages' => [
            'https://fixtures.nubank.com.br/careers' => base_path('tests/Fixtures/company_career_page_brazil_strong.html'),
            'https://fixtures.ifood.com.br/carreiras' => base_path('tests/Fixtures/company_career_page_brazil_promising.html'),
            'https://fixtures.mercadolivre.com.br/careers' => base_path('tests/Fixtures/company_career_page_brazil_weak.html'),
            'https://fixtures.vtex.com/careers' => base_path('tests/Fixtures/company_career_page_vtex_technology.html'),
            'https://fixtures.stone.com.br/careers' => base_path('tests/Fixtures/company_career_page_gupy_multi.html'),
            'https://fixtures.pagbank.com.br/careers' => base_path('tests/Fixtures/company_career_page_gupy_multi_two.html'),
            'https://fixtures.hotmart.com/jobs' => base_path('tests/Fixtures/company_career_page_brazil_promising.html'),
            'https://fixtures.quintoandar.com.br/carreiras' => base_path('tests/Fixtures/company_career_page_brazil_strong.html'),
            'https://fixtures.olx.com.br/vagas' => base_path('tests/Fixtures/company_career_page_brazil_weak.html'),
            'https://fixtures.magalu.com.br/carreiras' => base_path('tests/Fixtures/company_career_page_magalu_technology.html'),
        ],
        'brazilian_tech_job_boards' => [
            'https://fixtures.programathor.com.br/jobs' => base_path('tests/Fixtures/brazilian_tech_job_boards_programathor.html'),
            'https://fixtures.remotar.com.br/vagas-tecnologia-remoto' => base_path('tests/Fixtures/brazilian_tech_job_boards_remotar.html'),
            'https://fixtures.programathor.com.br/jobs/qa-python' => base_path('tests/Fixtures/brazilian_tech_job_boards_programathor_qa_python_listing.html'),
            'https://programathor.com.br/jobs/9001-chronos-cap-qa-python' => base_path('tests/Fixtures/brazilian_tech_job_boards_programathor_qa_python_detail.html'),
        ],
        'gupy_public_jobs' => [
            'https://afya.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_afya_listing.html'),
            'https://afya.gupy.io/jobs/71001' => base_path('tests/Fixtures/gupy_public_jobs_backend_python_detail.html'),
            'https://afya.gupy.io/jobs/71002' => base_path('tests/Fixtures/gupy_public_jobs_frontend_javascript_detail.html'),
            'https://carreirasomie.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_omie_listing.html'),
            'https://carreirasomie.gupy.io/jobs/72001' => base_path('tests/Fixtures/gupy_public_jobs_backend_php_detail.html'),
            'https://fcamara.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_fcamara_listing.html'),
            'https://fcamara.gupy.io/jobs/73001' => base_path('tests/Fixtures/gupy_public_jobs_devops_detail.html'),
            'https://vemsergran.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_gran_listing.html'),
            'https://vemsergran.gupy.io/jobs/74001' => base_path('tests/Fixtures/gupy_public_jobs_qa_data_detail.html'),
            'https://vempracigam.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_cigam_listing.html'),
            'https://vempracigam.gupy.io/jobs/78001' => base_path('tests/Fixtures/gupy_public_jobs_cigam_infra_detail.html'),
            'https://grupojbs.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_jbs_listing.html'),
            'https://grupojbs.gupy.io/jobs/79001' => base_path('tests/Fixtures/gupy_public_jobs_jbs_security_detail.html'),
            'https://minsait.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_minsait_listing.html'),
            'https://minsait.gupy.io/jobs/76001' => base_path('tests/Fixtures/gupy_public_jobs_backend_java_detail.html'),
            'https://positivotecnologia.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_positivo_listing.html'),
            'https://positivotecnologia.gupy.io/jobs/77001' => base_path('tests/Fixtures/gupy_public_jobs_data_platform_detail.html'),
            'https://mystery.gupy.io/' => base_path('tests/Fixtures/gupy_public_jobs_mystery_listing.html'),
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
    'gupy_public_job_targets' => $useFixtureResponses ? $fixtureGupyPublicJobTargets : [
        [
            'name' => 'Afya',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://afya.gupy.io/',
        ],
        [
            'name' => 'Omie',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://carreirasomie.gupy.io/',
        ],
        [
            'name' => 'FCamara',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://fcamara.gupy.io/',
        ],
        [
            'name' => 'Minsait',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://minsait.gupy.io/',
        ],
        [
            'name' => 'Global Hitss',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://globalhitss.gupy.io/',
        ],
        [
            'name' => 'Gaudium',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://gaudium.gupy.io/',
        ],
        [
            'name' => 'Montreal',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://montreal.gupy.io/',
        ],
        [
            'name' => 'CIGAM',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://vempracigam.gupy.io/',
        ],
        [
            'name' => 'Positivo Tecnologia',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://positivotecnologia.gupy.io/',
        ],
        [
            'name' => 'Gran',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://vemsergran.gupy.io/',
        ],
        [
            'name' => 'JBS',
            'parser_strategy' => 'gupy_listing',
            'listing_url' => 'https://grupojbs.gupy.io/',
        ],
    ],
];
