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
