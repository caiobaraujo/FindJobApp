<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CompanyCareerPagesDiscoverySource implements JobDiscoverySource
{
    public const SOURCE_KEY = 'company-career-pages';

    /**
     * @var array<string, true>
     */
    private const JOB_PAGE_SIGNALS = [
        'vaga' => true,
        'vagas' => true,
        'trabalhe conosco' => true,
        'carreira' => true,
        'carreiras' => true,
        'jobs' => true,
        'careers' => true,
        'openings' => true,
        'opportunities' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const SOFTWARE_SIGNALS = [
        'backend' => true,
        'dados' => true,
        'data' => true,
        'desenvolvedor' => true,
        'desenvolvedora' => true,
        'django' => true,
        'frontend' => true,
        'full stack' => true,
        'full-stack' => true,
        'laravel' => true,
        'mysql' => true,
        'node' => true,
        'php' => true,
        'programador' => true,
        'programadora' => true,
        'python' => true,
        'software' => true,
        'sql' => true,
        'vue' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const REGION_SIGNALS = [
        'belo horizonte' => true,
        'bh' => true,
        'betim' => true,
        'contagem' => true,
        'hibrido' => true,
        'híbrido' => true,
        'nova lima' => true,
        'regiao metropolitana' => true,
        'região metropolitana' => true,
        'remoto' => true,
    ];

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'Company Career Pages';
    }

    public function discoverEntriesWithDiagnostics(): array
    {
        $statusCode = 200;
        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];
        $targets = [];

        foreach (config('job_discovery.company_career_targets', []) as $target) {
            if (! is_array($target)) {
                continue;
            }

            foreach ($target['career_urls'] ?? [] as $careerUrl) {
                $careerUrl = is_string($careerUrl) ? trim($careerUrl) : '';

                if ($careerUrl === '') {
                    continue;
                }

                $pageFetch = $this->fetchCareerPageHtml($careerUrl);

                if ($pageFetch === null) {
                    $statusCode = max($statusCode, 500);

                    continue;
                }

                $statusCode = max($statusCode, $pageFetch['status_code']);

                if (! $pageFetch['successful']) {
                    continue;
                }

                $parsed = $this->parseCareerPageHtmlWithDiagnostics($pageFetch['body'], [
                    'career_url' => $careerUrl,
                    'company_name' => $this->nullableString($target['name'] ?? null),
                    'region' => $this->nullableString($target['region'] ?? null),
                    'website_url' => $this->nullableString($target['website_url'] ?? null),
                    'parser_strategy' => $this->parserStrategy($target),
                ]);
                $targetIdentifier = $this->targetIdentifier([
                    'career_url' => $careerUrl,
                    'company_name' => $this->nullableString($target['name'] ?? null),
                ]);

                $candidateCount += $parsed['candidate_links'];
                $invalidCount += $parsed['invalid_links'];
                $entries = array_merge($entries, $parsed['entries']);
                $targets[$targetIdentifier] = [
                    'target_identifier' => $targetIdentifier,
                    'target_name' => $this->nullableString($target['name'] ?? null) ?? $careerUrl,
                    'career_url' => $careerUrl,
                    'parser_strategy' => $this->parserStrategy($target),
                    'candidate_links' => ($targets[$targetIdentifier]['candidate_links'] ?? 0) + $parsed['candidate_links'],
                    'invalid_links' => ($targets[$targetIdentifier]['invalid_links'] ?? 0) + $parsed['invalid_links'],
                    'parsed_jobs' => ($targets[$targetIdentifier]['parsed_jobs'] ?? 0) + count($parsed['entries']),
                ];
            }
        }

        return [
            'status_code' => $statusCode,
            'candidate_links' => $candidateCount,
            'parsed_jobs' => count($entries),
            'invalid_links' => $invalidCount,
            'targets' => array_values($targets),
            'entries' => $entries,
        ];
    }

    public function enrichEntry(array $entry): array
    {
        return [
            'source_url' => $this->nullableString($entry['detail_url'] ?? null),
            'job_title' => $this->nullableString($entry['job_title'] ?? null),
            'company_name' => $this->nullableString($entry['company_name'] ?? null),
            'location' => $this->nullableString($entry['location'] ?? null),
            'work_mode' => $this->nullableWorkMode($entry['work_mode'] ?? null),
            'description_text' => $this->nullableString($entry['description_text'] ?? null),
            'target_identifier' => $this->nullableString($entry['target_identifier'] ?? null),
            'target_name' => $this->nullableString($entry['target_name'] ?? null),
        ];
    }

    /**
     * @param array{
     *     career_url: string,
     *     company_name: string|null,
     *     region: string|null,
     *     website_url: string|null,
     *     parser_strategy?: string|null
     * } $target
     * @return array{
     *     candidate_links: int,
     *     invalid_links: int,
     *     entries: list<array{
     *         detail_url: string,
     *         job_title: string|null,
     *         company_name: string|null,
     *         location: string|null,
     *         work_mode: string|null,
     *         description_text: string|null
     *     }>
     * }
     */
    public function parseCareerPageHtmlWithDiagnostics(string $html, array $target): array
    {
        $parserStrategy = $this->parserStrategy($target);
        $xpath = $this->xpath($html);
        $links = $xpath->query('//a[@href]');

        if ($links === false) {
            return [
                'candidate_links' => 0,
                'invalid_links' => 0,
                'entries' => $this->fallbackPageEntry($html, $target, $parserStrategy),
            ];
        }

        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];
        $seenUrls = [];

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $entry = $this->entryFromLink($xpath, $link, $target, $parserStrategy);

            if ($entry === null) {
                continue;
            }

            $candidateCount++;

            if (! filter_var($entry['detail_url'], FILTER_VALIDATE_URL)) {
                $invalidCount++;

                continue;
            }

            if (in_array($entry['detail_url'], $seenUrls, true)) {
                continue;
            }

            $seenUrls[] = $entry['detail_url'];
            $entries[] = $entry;
        }

        if ($entries !== []) {
            return [
                'candidate_links' => $candidateCount,
                'invalid_links' => $invalidCount,
                'entries' => $entries,
            ];
        }

        return [
            'candidate_links' => $candidateCount,
            'invalid_links' => $invalidCount,
            'entries' => $this->fallbackPageEntry($html, $target, $parserStrategy),
        ];
    }

    private function entryFromLink(DOMXPath $xpath, DOMElement $link, array $target, string $parserStrategy): ?array
    {
        $href = $this->nullableString($link->getAttribute('href'));

        if ($href === null) {
            return null;
        }

        $detailUrl = $this->absoluteUrl($href, $target['career_url']) ?? $href;
        $contextText = $this->linkContextText($xpath, $link);
        $normalizedContext = $this->normalizeText($contextText);

        if ($normalizedContext === null) {
            return null;
        }

        if (! $this->isPotentialJobLink($detailUrl, $normalizedContext, $link->textContent, $parserStrategy)) {
            return null;
        }

        $jobTitle = $this->jobTitleFromLink($link->textContent);
        $location = $this->locationFromContext($normalizedContext, $target['region']);
        $workMode = $this->nullableWorkMode($normalizedContext);

        if (! $this->isCompleteEntry($detailUrl, $jobTitle, $target['company_name'])) {
            return null;
        }

        return [
            'detail_url' => $detailUrl,
            'job_title' => $jobTitle,
            'company_name' => $target['company_name'],
            'location' => $location,
            'work_mode' => $workMode,
            'description_text' => Str::limit($contextText, 1600, ''),
            'target_identifier' => $this->targetIdentifier($target),
            'target_name' => $target['company_name'] ?? $target['career_url'],
        ];
    }

    /**
     * @return list<array{
     *     detail_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     work_mode: string|null,
     *     description_text: string|null
     * }>
     */
    private function fallbackPageEntry(string $html, array $target, string $parserStrategy): array
    {
        if ($parserStrategy === 'ats_board') {
            return [];
        }

        $pageText = $this->pageText($html);
        $normalizedPageText = $this->normalizeText($pageText);

        if ($normalizedPageText === null) {
            return [];
        }

        if (! $this->hasJobPageSignal($normalizedPageText) || ! $this->hasSoftwareSignal($normalizedPageText)) {
            return [];
        }

        $jobTitle = $this->jobTitleFromPageText($pageText);

        if (! $this->isCompleteEntry($target['career_url'], $jobTitle, $target['company_name'])) {
            return [];
        }

        return [[
            'detail_url' => $target['career_url'],
            'job_title' => $jobTitle,
            'company_name' => $target['company_name'],
            'location' => $this->locationFromContext($normalizedPageText, $target['region']),
            'work_mode' => $this->nullableWorkMode($normalizedPageText),
            'description_text' => Str::limit($pageText, 1600, ''),
            'target_identifier' => $this->targetIdentifier($target),
            'target_name' => $target['company_name'] ?? $target['career_url'],
        ]];
    }

    /**
     * @param array{career_url: string, company_name: string|null} $target
     */
    private function targetIdentifier(array $target): string
    {
        return $target['company_name'] ?? $target['career_url'];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function pageText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? strip_tags($html));
    }

    private function linkContextText(DOMXPath $xpath, DOMElement $link): string
    {
        $text = $this->normalizeText($link->textContent) ?? '';
        $parent = $link->parentNode;

        while ($parent instanceof DOMNode) {
            if ($parent instanceof DOMElement && in_array($parent->tagName, ['li', 'article', 'section', 'div'], true)) {
                $parentText = $this->normalizeText($parent->textContent);

                if ($parentText !== null && mb_strlen($parentText) >= 40) {
                    return $parentText;
                }
            }

            $parent = $parent->parentNode;
        }

        return $text;
    }

    /**
     * @return array{successful: bool, status_code: int, body: string}|null
     */
    private function fetchCareerPageHtml(string $careerUrl): ?array
    {
        if (config('job_discovery.use_fixture_responses')) {
            $fixtureResponses = config('job_discovery.fixture_responses.company_career_pages', []);
            $fixturePath = is_array($fixtureResponses) ? ($fixtureResponses[$careerUrl] ?? null) : null;

            if (is_string($fixturePath) && $fixturePath !== '' && is_file($fixturePath)) {
                $contents = file_get_contents($fixturePath);

                if (is_string($contents)) {
                    return [
                        'successful' => true,
                        'status_code' => 200,
                        'body' => $contents,
                    ];
                }
            }
        }

        $response = Http::timeout(8)
            ->accept('text/html')
            ->withUserAgent('FindJobApp/1.0')
            ->get($careerUrl);

        return [
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'body' => $response->body(),
        ];
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);

            if (! is_string($scheme) || $scheme === '') {
                return null;
            }

            return "{$scheme}:{$href}";
        }

        if (! str_starts_with($href, '/')) {
            return null;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($scheme) || $scheme === '' || ! is_string($host) || $host === '') {
            return null;
        }

        return "{$scheme}://{$host}{$href}";
    }

    private function isPotentialJobLink(string $detailUrl, string $normalizedContext, string $linkText, string $parserStrategy): bool
    {
        $normalizedUrl = $this->normalizeText($detailUrl) ?? '';
        $normalizedLinkText = $this->normalizeText($linkText) ?? '';
        $knownAtsUrl = $this->knownApplicantTrackingSystemUrl($detailUrl);

        if (! $this->hasSoftwareSignal($normalizedContext) && ! $this->hasSoftwareSignal($normalizedLinkText)) {
            return false;
        }

        if ($parserStrategy === 'ats_board') {
            return $knownAtsUrl;
        }

        if ($knownAtsUrl) {
            return true;
        }

        if ($this->hasJobPageSignal($normalizedContext) || $this->hasJobPageSignal($normalizedLinkText)) {
            return true;
        }

        foreach ([
            '/jobs/',
            '/job/',
            '/careers/',
            '/carreiras/',
            '/trabalhe-conosco/',
            '/vaga/',
            '/vagas/',
            '/opportunities/',
            '/positions/',
        ] as $signal) {
            if (str_contains($normalizedUrl, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function parserStrategy(array $target): string
    {
        return $this->nullableString($target['parser_strategy'] ?? null) === 'ats_board'
            ? 'ats_board'
            : 'structured_lists';
    }

    private function isCompleteEntry(?string $detailUrl, ?string $jobTitle, ?string $companyName): bool
    {
        if ($detailUrl === null || ! filter_var($detailUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        return $jobTitle !== null && $companyName !== null;
    }

    private function knownApplicantTrackingSystemUrl(string $detailUrl): bool
    {
        $host = parse_url($detailUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $normalizedHost = strtolower($host);

        foreach ([
            'gupy.io',
            'greenhouse.io',
            'lever.co',
            'workable.com',
            'solides.com.br',
            'kenoby.com',
        ] as $signal) {
            if (str_contains($normalizedHost, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function hasJobPageSignal(string $text): bool
    {
        foreach (array_keys(self::JOB_PAGE_SIGNALS) as $signal) {
            if (str_contains($this->normalizeText($text) ?? '', $signal)) {
                return true;
            }
        }

        return false;
    }

    private function hasSoftwareSignal(string $text): bool
    {
        foreach (array_keys(self::SOFTWARE_SIGNALS) as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function locationFromContext(string $contextText, ?string $fallbackRegion): ?string
    {
        foreach (array_keys(self::REGION_SIGNALS) as $signal) {
            if (str_contains($contextText, $signal)) {
                return $fallbackRegion ?? ucfirst($signal);
            }
        }

        return $fallbackRegion;
    }

    private function jobTitleFromLink(string $text): ?string
    {
        $jobTitle = $this->nullableString(trim(preg_replace('/\s+/', ' ', $text) ?? $text));

        if ($jobTitle === null) {
            return null;
        }

        if ($this->hasJobPageSignal(strtolower($jobTitle)) && ! $this->hasSoftwareSignal(strtolower($jobTitle))) {
            return null;
        }

        return $jobTitle;
    }

    private function jobTitleFromPageText(string $pageText): ?string
    {
        if (preg_match('/(desenvolvedor[a]?[^.]{0,80}|software engineer[^.]{0,80}|backend[^.]{0,80}|frontend[^.]{0,80}|full[- ]stack[^.]{0,80})/iu', $pageText, $matches) === 1) {
            return $this->nullableString($matches[1]);
        }

        return null;
    }

    private function nullableWorkMode(?string $text): ?string
    {
        $normalizedText = $this->normalizeText($text);

        if ($normalizedText === null) {
            return null;
        }

        if (str_contains($normalizedText, 'hibrido') || str_contains($normalizedText, 'híbrido') || str_contains($normalizedText, 'hybrid')) {
            return JobLead::WORK_MODE_HYBRID;
        }

        if (str_contains($normalizedText, 'remoto') || str_contains($normalizedText, 'remote')) {
            return JobLead::WORK_MODE_REMOTE;
        }

        return null;
    }

    private function normalizeText(?string $text): ?string
    {
        $value = $this->nullableString($text);

        if ($value === null) {
            return null;
        }

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
