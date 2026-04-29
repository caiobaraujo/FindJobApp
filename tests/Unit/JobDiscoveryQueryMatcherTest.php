<?php

use App\Services\JobDiscovery\JobDiscoveryQueryMatcher;

it('matches aliased technology and work mode tokens against discovered job text', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $matches = $matcher->matches('Vuejs remoto', [
        'job_title' => 'Senior Laravel Engineer',
        'company_name' => 'Acme Labs',
        'location' => 'Remote, United States',
        'work_mode' => null,
        'description_text' => 'We need a Laravel engineer with Vue and SQL experience.',
        'extracted_keywords' => ['laravel', 'vue', 'sql'],
    ]);

    expect($matches)->toBeTrue();
});

it('matches the BH alias against belo horizonte location text', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $matches = $matcher->matches('PHP Laravel híbrido BH', [
        'job_title' => 'PHP Laravel Developer',
        'company_name' => 'Minas Tech',
        'location' => 'Hybrid - Belo Horizonte, Brazil',
        'work_mode' => null,
        'description_text' => 'Hybrid role for a PHP and Laravel engineer.',
        'extracted_keywords' => ['php', 'laravel'],
    ]);

    expect($matches)->toBeTrue();
});

it('rejects jobs that only match the work mode but not the main query terms', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $matches = $matcher->matches('Laravel remote', [
        'job_title' => 'Python Support Engineer',
        'company_name' => 'Beta Systems',
        'location' => 'Remote, Portugal',
        'work_mode' => null,
        'description_text' => 'Support role focused on Python support and customer troubleshooting.',
        'extracted_keywords' => ['python'],
    ]);

    expect($matches)->toBeFalse();
});

it('matches php queries against laravel jobs and normalizes brasil to brazil', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $matches = $matcher->matches('php laravel remoto brasil', [
        'job_title' => 'Senior Laravel Engineer',
        'company_name' => 'Acme Labs',
        'location' => 'Remote / Brazil',
        'work_mode' => null,
        'description_text' => 'Laravel backend role for distributed teams in Brazil.',
        'extracted_keywords' => ['laravel', 'mysql'],
    ]);

    expect($matches)->toBeTrue();
});

it('uses the vue frontend query profile to match vue jobs that do not say frontend', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $explanation = $matcher->explainWithProfiles('frontend', [
        'job_title' => 'Vue.js Product Engineer',
        'company_name' => 'Acme Labs',
        'location' => 'Remote / Brazil',
        'work_mode' => null,
        'description_text' => 'Build Vue.js interfaces and JavaScript product experiences.',
        'extracted_keywords' => ['vue', 'javascript'],
    ], [[
        'key' => 'frontend_vue',
        'label' => 'Vue frontend',
        'signals' => ['frontend_vue', 'frontend', 'javascript', 'vue'],
        'aliases' => ['vue', 'vue js', 'vuejs'],
        'query' => 'frontend_vue frontend javascript vue',
    ]]);

    expect($explanation['matches'])->toBeTrue()
        ->and($explanation['matched_by_query'])->toBeFalse()
        ->and($explanation['matched_query_profile_keys'])->toBe(['frontend_vue']);
});

it('uses the angular frontend query profile to match angular jobs that do not say frontend', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $explanation = $matcher->explainWithProfiles('frontend', [
        'job_title' => 'Angular Product Engineer',
        'company_name' => 'Northwind',
        'location' => 'Remote / Brazil',
        'work_mode' => null,
        'description_text' => 'Angular and JavaScript product engineering role.',
        'extracted_keywords' => ['angular', 'javascript'],
    ], [[
        'key' => 'frontend_angular',
        'label' => 'Angular frontend',
        'signals' => ['frontend_angular', 'frontend', 'javascript', 'angular'],
        'aliases' => ['angular', 'angular js', 'angularjs'],
        'query' => 'frontend_angular frontend javascript angular',
    ]]);

    expect($explanation['matches'])->toBeTrue()
        ->and($explanation['matched_by_query'])->toBeFalse()
        ->and($explanation['matched_query_profile_keys'])->toBe(['frontend_angular']);
});

it('uses the python backend query profile to match python django jobs that do not say backend', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $explanation = $matcher->explainWithProfiles('backend', [
        'job_title' => 'Python Django Platform Engineer',
        'company_name' => 'DataForge',
        'location' => 'Hybrid - Belo Horizonte, Brazil',
        'work_mode' => null,
        'description_text' => 'Python, Django and API platform role.',
        'extracted_keywords' => ['python', 'django', 'api'],
    ], [[
        'key' => 'backend_python',
        'label' => 'Python backend',
        'signals' => ['backend_python', 'backend', 'python', 'django'],
        'aliases' => ['python', 'django'],
        'query' => 'backend_python backend python django',
    ]]);

    expect($explanation['matches'])->toBeTrue()
        ->and($explanation['matched_by_query'])->toBeFalse()
        ->and($explanation['matched_query_profile_keys'])->toBe(['backend_python']);
});

it('uses the php backend query profile to match php laravel jobs that do not say backend', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $explanation = $matcher->explainWithProfiles('backend', [
        'job_title' => 'Laravel Product Engineer',
        'company_name' => 'Platform Works',
        'location' => 'Remote / Brazil',
        'work_mode' => null,
        'description_text' => 'Laravel, PHP and MySQL product engineering role.',
        'extracted_keywords' => ['laravel', 'php', 'mysql'],
    ], [[
        'key' => 'backend_php',
        'label' => 'PHP backend',
        'signals' => ['backend_php', 'backend', 'php', 'laravel'],
        'aliases' => ['php', 'laravel'],
        'query' => 'backend_php backend php laravel',
    ]]);

    expect($explanation['matches'])->toBeTrue()
        ->and($explanation['matched_by_query'])->toBeFalse()
        ->and($explanation['matched_query_profile_keys'])->toBe(['backend_php']);
});

it('uses the fullstack query profile to match full stack jobs during backend discovery', function (): void {
    $matcher = app(JobDiscoveryQueryMatcher::class);

    $explanation = $matcher->explainWithProfiles('backend', [
        'job_title' => 'Full Stack Engineer',
        'company_name' => 'Stack Studio',
        'location' => 'Remote / Brazil',
        'work_mode' => null,
        'description_text' => 'Full stack product engineering across PHP and JavaScript.',
        'extracted_keywords' => ['full stack', 'php', 'javascript'],
    ], [[
        'key' => 'fullstack',
        'label' => 'Full stack',
        'signals' => ['fullstack', 'backend', 'frontend'],
        'aliases' => ['full stack', 'fullstack'],
        'query' => 'fullstack backend frontend',
    ]]);

    expect($explanation['matches'])->toBeTrue()
        ->and($explanation['matched_by_query'])->toBeFalse()
        ->and($explanation['matched_query_profile_keys'])->toBe(['fullstack']);
});
