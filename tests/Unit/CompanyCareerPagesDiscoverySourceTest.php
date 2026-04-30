<?php

use App\Models\JobLead;
use App\Services\JobDiscovery\CompanyCareerPagesDiscoverySource;

it('extracts multiple software jobs from a structured curated brazilian career page fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_brazil_strong.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://fixtures.nubank.com.br/careers',
        'company_name' => 'Nubank',
        'region' => 'São Paulo',
        'website_url' => 'https://nubank.com.br',
        'parser_strategy' => 'structured_lists',
    ]);

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(3)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://fixtures.nubank.com.br/vagas/backend-php-laravel')
        ->and($parsed['entries'][0]['job_title'])->toBe('Engenheiro(a) Backend PHP Laravel')
        ->and($parsed['entries'][0]['company_name'])->toBe('Nubank')
        ->and($parsed['entries'][0]['target_identifier'])->toBe('Nubank')
        ->and($parsed['entries'][0]['target_name'])->toBe('Nubank')
        ->and($parsed['entries'][0]['location'])->toBe('São Paulo')
        ->and($parsed['entries'][0]['work_mode'])->toBe(JobLead::WORK_MODE_HYBRID);
});

it('does not import a generic curated company page without software job signals', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_generic.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://fixtures.vtex.com/careers',
        'company_name' => 'VTEX',
        'region' => 'São Paulo',
        'website_url' => 'https://vtex.com',
        'parser_strategy' => 'structured_lists',
    ]);

    expect($parsed['entries'])->toBe([]);
});

it('extracts structured software jobs from the curated vtex fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_vtex_technology.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://fixtures.vtex.com/careers',
        'company_name' => 'VTEX',
        'region' => 'São Paulo',
        'website_url' => 'https://vtex.com',
        'parser_strategy' => 'structured_lists',
    ]);

    expect($parsed['candidate_links'])->toBe(2)
        ->and($parsed['entries'])->toHaveCount(2)
        ->and($parsed['entries'][0]['company_name'])->toBe('VTEX');
});

it('extracts multiple visible ats jobs from an ats board fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_gupy_multi.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://fixtures.stone.com.br/careers',
        'company_name' => 'Stone',
        'region' => 'Brazil',
        'website_url' => 'https://stone.com.br',
        'parser_strategy' => 'ats_board',
    ]);

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(3)
        ->and($parsed['entries'][0]['target_identifier'])->toBe('Stone')
        ->and($parsed['entries'][0]['target_name'])->toBe('Stone')
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://producttech.gupy.io/jobs/2001')
        ->and($parsed['entries'][1]['detail_url'])->toBe('https://producttech.gupy.io/jobs/2002')
        ->and($parsed['entries'][2]['detail_url'])->toBe('https://producttech.gupy.io/jobs/2003');
});

it('rejects fallback-only pages without a detectable title even when software text is present', function (): void {
    $html = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
  <body>
    <main>
      <section>
        <h1>Carreiras em Tecnologia</h1>
        <p>Nosso time usa PHP, Python, Vue e SQL em produtos digitais para o Brasil.</p>
      </section>
    </main>
  </body>
</html>
HTML;

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://fixtures.magalu.com.br/carreiras',
        'company_name' => 'Magazine Luiza',
        'region' => 'Brazil',
        'website_url' => 'https://magazineluiza.com.br',
        'parser_strategy' => 'structured_lists',
    ]);

    expect($parsed['entries'])->toBe([]);
});
