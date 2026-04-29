<?php

use App\Models\JobLead;
use App\Services\JobDiscovery\BrazilianTechJobBoardsDiscoverySource;

it('extracts valid tecnologia jobs from the programathor fixture and skips expired or incomplete entries', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/brazilian_tech_job_boards_programathor.html');

    expect($html)->not->toBeFalse();

    $parsed = app(BrazilianTechJobBoardsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html, [
        'listing_url' => 'https://fixtures.programathor.com.br/jobs',
        'target_name' => 'ProgramaThor',
        'platform' => 'programathor',
        'parser_strategy' => 'programathor_cards',
    ]);

    expect($parsed['candidate_links'])->toBe(4)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['skipped_expired'])->toBe(1)
        ->and($parsed['skipped_missing_company'])->toBe(1)
        ->and($parsed['entries'])->toHaveCount(2)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://programathor.com.br/jobs/7010-acme-backend-laravel')
        ->and($parsed['entries'][0]['company_name'])->toBe('Acme Sistemas')
        ->and($parsed['entries'][0]['job_title'])->toBe('Pessoa Desenvolvedora PHP Laravel')
        ->and($parsed['entries'][0]['location'])->toBe('Belo Horizonte, Brasil')
        ->and($parsed['entries'][0]['work_mode'])->toBe(JobLead::WORK_MODE_REMOTE)
        ->and($parsed['entries'][0]['source_platform'])->toBe('programathor')
        ->and($parsed['entries'][1]['company_name'])->toBe('Byte Labs');
});

it('extracts valid remote tecnologia jobs from the remotar fixture and skips expired jobs', function (): void {
    $html = file_get_contents(__DIR__.'/../Fixtures/brazilian_tech_job_boards_remotar.html');

    expect($html)->not->toBeFalse();

    $parsed = app(BrazilianTechJobBoardsDiscoverySource::class)->parseListingHtmlWithDiagnostics($html, [
        'listing_url' => 'https://fixtures.remotar.com.br/vagas-tecnologia-remoto',
        'target_name' => 'Remotar',
        'platform' => 'remotar',
        'parser_strategy' => 'remotar_cards',
    ]);

    expect($parsed['candidate_links'])->toBe(2)
        ->and($parsed['invalid_links'])->toBe(0)
        ->and($parsed['skipped_expired'])->toBe(1)
        ->and($parsed['skipped_missing_company'])->toBe(0)
        ->and($parsed['entries'])->toHaveCount(1)
        ->and($parsed['entries'][0]['detail_url'])->toBe('https://remotar.com.br/job/9911/atlas-labs/vue-js-product-engineer')
        ->and($parsed['entries'][0]['job_title'])->toBe('Vue.js Product Engineer')
        ->and($parsed['entries'][0]['company_name'])->toBe('Atlas Labs')
        ->and($parsed['entries'][0]['location'])->toBe('Brasil')
        ->and($parsed['entries'][0]['work_mode'])->toBe(JobLead::WORK_MODE_REMOTE)
        ->and($parsed['entries'][0]['source_platform'])->toBe('remotar');
});
