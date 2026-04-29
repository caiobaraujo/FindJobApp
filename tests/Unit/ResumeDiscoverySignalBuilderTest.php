<?php

use App\Services\ResumeDiscoverySignalBuilder;

it('derives deterministic discovery signals from a realistic full stack resume', function (): void {
    $resumeText = file_get_contents(__DIR__.'/../Fixtures/resume_discovery_signals_full_stack.txt');

    expect($resumeText)->toBeString();

    $signals = app(ResumeDiscoverySignalBuilder::class)->build($resumeText, [
        'PHP',
        'Laravel',
        'Python',
        'Django',
        'Vue.js',
        'Angular',
        'Docker',
        'SQL',
        'MySQL',
        'OpenAI',
        'NLP',
        'LLMs',
    ]);

    expect($signals['canonical_skills'])->toBe([
        'vue',
        'angular',
        'python',
        'django',
        'php',
        'laravel',
        'fullstack',
        'nodejs',
        'javascript',
        'sql',
        'mysql',
        'docker',
        'openai',
        'llm',
        'nlp',
        'chatbot',
        'frontend',
        'backend',
    ])
        ->and($signals['role_families'])->toBe([
            'frontend_vue',
            'frontend',
            'javascript',
            'frontend_angular',
            'backend_python',
            'backend',
            'backend_php',
            'fullstack',
            'database',
            'ai_applied',
        ])
        ->and($signals['aliases'])->toContain(
            'vuejs',
            'angularjs',
            'nodejs',
            'open ai',
            'large language model',
            'natural language processing',
            'chat bot',
        )
        ->and($signals['query_profiles'])->toHaveCount(8)
        ->and($signals['query_profiles'][0])->toMatchArray([
            'key' => 'frontend_vue',
            'signals' => ['frontend_vue', 'frontend', 'javascript', 'vue'],
            'query' => 'frontend_vue frontend javascript vue',
        ])
        ->and($signals['query_profiles'][2])->toMatchArray([
            'key' => 'backend_python',
            'signals' => ['backend_python', 'backend', 'python', 'django'],
            'query' => 'backend_python backend python django',
        ])
        ->and($signals['query_profiles'][7])->toMatchArray([
            'key' => 'ai_applied',
            'signals' => ['ai_applied', 'chatbot', 'nlp', 'llm', 'openai'],
            'query' => 'ai_applied chatbot nlp llm openai',
        ]);
});

it('keeps empty resume inputs from inventing discovery signals', function (): void {
    $signals = app(ResumeDiscoverySignalBuilder::class)->build(null, null);

    expect($signals)->toBe([
        'detected_skills' => [],
        'role_families' => [],
        'canonical_skills' => [],
        'aliases' => [],
        'query_profiles' => [],
    ]);
});

it('builds match signals from the same canonical resume taxonomy used for job comparison', function (): void {
    $resumeText = 'Full stack engineer with Node.js, Vue.js, Angular, Python, Django, PHP, Laravel, SQL, and MySQL experience. We go above and beyond for product quality.';

    $signals = app(ResumeDiscoverySignalBuilder::class)->matchSignals($resumeText, []);

    expect($signals)->toContain('nodejs')
        ->and($signals)->toContain('vue')
        ->and($signals)->toContain('angular')
        ->and($signals)->toContain('python')
        ->and($signals)->toContain('django')
        ->and($signals)->toContain('php')
        ->and($signals)->toContain('laravel')
        ->and($signals)->toContain('sql')
        ->and($signals)->toContain('mysql')
        ->and($signals)->toContain('javascript')
        ->and($signals)->toContain('frontend')
        ->and($signals)->toContain('backend')
        ->and($signals)->not->toContain('go');
});
