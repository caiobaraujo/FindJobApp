<?php

use App\Models\JobLead;
use App\Services\JobDiscovery\CompanyCareerPagesDiscoverySource;

it('extracts a software job from a company career page fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_software.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://example.com/carreiras',
        'company_name' => 'Example BH Tech',
        'region' => 'Belo Horizonte',
        'website_url' => 'https://example.com',
    ]);

    expect($parsed['candidate_links'])->toBe(1)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(1)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://example.com/carreiras/backend-laravel')
        ->and($parsed['entries'][0]['job_title'])->toBe('Desenvolvedor Backend Laravel')
        ->and($parsed['entries'][0]['company_name'])->toBe('Example BH Tech')
        ->and($parsed['entries'][0]['location'])->toBe('Belo Horizonte')
        ->and($parsed['entries'][0]['work_mode'])->toBe(JobLead::WORK_MODE_HYBRID);
});

it('does not import a generic company page without software job signals', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_generic.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://example.com/trabalhe-conosco',
        'company_name' => 'Example BH Tech',
        'region' => 'Belo Horizonte',
        'website_url' => 'https://example.com',
    ]);

    expect($parsed['entries'])->toBe([]);
});

it('extracts a visible gupy style software role link from a company career page fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_gupy.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://example.com/carreiras',
        'company_name' => 'Example BH Tech',
        'region' => 'Belo Horizonte',
        'website_url' => 'https://example.com',
    ]);

    expect($parsed['candidate_links'])->toBe(1)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(1)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://example.gupy.io/jobs/1234567')
        ->and($parsed['entries'][0]['job_title'])->toBe('Desenvolvedor(a) Full Stack PHP e Laravel')
        ->and($parsed['entries'][0]['company_name'])->toBe('Example BH Tech')
        ->and($parsed['entries'][0]['location'])->toBe('Belo Horizonte')
        ->and($parsed['entries'][0]['work_mode'])->toBe(JobLead::WORK_MODE_HYBRID);
});

it('extracts multiple visible gupy style software jobs from a career page fixture', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/company_career_page_gupy_multi.html');

    expect($html)->not->toBeFalse();

    $parsed = app(CompanyCareerPagesDiscoverySource::class)->parseCareerPageHtmlWithDiagnostics($html, [
        'career_url' => 'https://example-gupy.com/careers',
        'company_name' => 'Example Gupy Product',
        'region' => 'Brazil',
        'website_url' => 'https://example-gupy.com',
    ]);

    expect($parsed['candidate_links'])->toBe(3)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(3)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://producttech.gupy.io/jobs/2001')
        ->and($parsed['entries'][1]['detail_url'])->toBe('https://producttech.gupy.io/jobs/2002')
        ->and($parsed['entries'][2]['detail_url'])->toBe('https://producttech.gupy.io/jobs/2003');
});
