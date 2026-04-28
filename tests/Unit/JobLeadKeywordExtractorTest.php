<?php

use App\Services\JobLeadKeywordExtractor;

it('extracts meaningful keywords from a job description', function (): void {
    $extractor = new JobLeadKeywordExtractor();

    $keywords = $extractor->extractKeywords(
        'We are hiring a Laravel engineer to build Laravel APIs, improve API performance, and own Vue dashboards. '
        .'The Laravel engineer will collaborate on testing and API design.',
    );

    expect($keywords)->toContain('laravel');
    expect($keywords)->toContain('api');
    expect($keywords)->toContain('laravel engineer');
});

it('filters english and portuguese stopwords from extracted keywords', function (): void {
    $extractor = new JobLeadKeywordExtractor();

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

it('drops generic job posting noise words while keeping technical terms', function (): void {
    $extractor = new JobLeadKeywordExtractor();

    $keywords = $extractor->extractKeywords(
        'Like work just own time per good experience team and team, but also PHP, Laravel, Python, Django, Vue, MySQL, SQL, Docker, API, OpenAI, LLM, NLP.',
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
    expect($keywords)->toContain('llm');
    expect($keywords)->toContain('nlp');
    expect($keywords)->not->toContain('like');
    expect($keywords)->not->toContain('work');
    expect($keywords)->not->toContain('just');
    expect($keywords)->not->toContain('own');
    expect($keywords)->not->toContain('time');
    expect($keywords)->not->toContain('per');
    expect($keywords)->not->toContain('good');
    expect($keywords)->not->toContain('experience');
    expect($keywords)->not->toContain('team');
});

it('normalizes common technical aliases to canonical keywords', function (): void {
    $extractor = new JobLeadKeywordExtractor();

    $keywords = $extractor->extractKeywords(
        'We need a VueJS and NodeJS engineer with Open AI, AngularJS, and fullstack experience.',
    );

    expect($keywords)->toContain('vue');
    expect($keywords)->toContain('node');
    expect($keywords)->toContain('openai');
    expect($keywords)->toContain('angular');
    expect($keywords)->toContain('full stack');
    expect($keywords)->not->toContain('vuejs');
    expect($keywords)->not->toContain('nodejs');
    expect($keywords)->not->toContain('angularjs');
});

it('extracts clean technical keywords from a realistic mixed language description', function (): void {
    $extractor = new JobLeadKeywordExtractor();

    $keywords = $extractor->extractKeywords(
        <<<'TEXT'
Buscamos uma pessoa desenvolvedora full stack para atuar com React, Python, SQL e AWS em um produto SaaS.
Voce vai trabalhar com integracoes de API, automacao de processos, testes e analise de dados.
The role requires strong communication, experience with React components, Python services, SQL queries,
AWS infrastructure, and collaboration with product and design teams. O convenio e outros beneficios fazem parte
do pacote, mas o foco principal e engenharia de software, arquitetura de API e qualidade.
TEXT,
    );

    expect($keywords)->toContain('react');
    expect($keywords)->toContain('python');
    expect($keywords)->toContain('sql');
    expect($keywords)->toContain('aws');
    expect($keywords)->toContain('api');
    expect($keywords)->toContain('react python');
    expect($keywords)->not->toContain('para');
    expect($keywords)->not->toContain('com');
    expect($keywords)->not->toContain('muito');
    expect($keywords)->not->toContain('diversos');
    expect($keywords)->not->toContain('conv');
    expect($keywords)->not->toContain('nio');
    expect($keywords)->not->toContain('conv nio');
    expect(count($keywords))->toBeLessThanOrEqual(12);
    expect($keywords)->toBe(array_values(array_unique($keywords)));
});
