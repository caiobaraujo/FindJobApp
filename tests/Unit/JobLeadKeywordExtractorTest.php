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
