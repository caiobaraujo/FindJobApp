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

        foreach (config('job_discovery.company_career_targets', []) as $target) {
            if (! is_array($target)) {
                continue;
            }

            foreach ($target['career_urls'] ?? [] as $careerUrl) {
                $careerUrl = is_string($careerUrl) ? trim($careerUrl) : '';

                if ($careerUrl === '') {
                    continue;
                }

                $response = Http::timeout(8)
                    ->accept('text/html')
                    ->withUserAgent('FindJobApp/1.0')
                    ->get($careerUrl);

                if (! $response->successful()) {
                    $statusCode = max($statusCode, $response->status());

                    continue;
                }

                $parsed = $this->parseCareerPageHtmlWithDiagnostics($response->body(), [
                    'career_url' => $careerUrl,
                    'company_name' => $this->nullableString($target['name'] ?? null),
                    'region' => $this->nullableString($target['region'] ?? null),
                    'website_url' => $this->nullableString($target['website_url'] ?? null),
                ]);

                $candidateCount += $parsed['candidate_links'];
                $invalidCount += $parsed['invalid_links'];
                $entries = array_merge($entries, $parsed['entries']);
            }
        }

        return [
            'status_code' => $statusCode,
            'candidate_links' => $candidateCount,
            'parsed_jobs' => count($entries),
            'invalid_links' => $invalidCount,
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
        ];
    }

    /**
     * @param array{
     *     career_url: string,
     *     company_name: string|null,
     *     region: string|null,
     *     website_url: string|null
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
        $xpath = $this->xpath($html);
        $links = $xpath->query('//a[@href]');

        if ($links === false) {
            return [
                'candidate_links' => 0,
                'invalid_links' => 0,
                'entries' => $this->fallbackPageEntry($html, $target),
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

            $candidateCount++;
            $entry = $this->entryFromLink($xpath, $link, $target);

            if ($entry === null) {
                continue;
            }

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
            'entries' => $this->fallbackPageEntry($html, $target),
        ];
    }

    private function entryFromLink(DOMXPath $xpath, DOMElement $link, array $target): ?array
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

        if (! $this->hasSoftwareSignal($normalizedContext)) {
            return null;
        }

        if (! $this->hasJobPageSignal($normalizedContext) && ! $this->hasJobPageSignal($detailUrl)) {
            return null;
        }

        $jobTitle = $this->jobTitleFromLink($link->textContent);
        $location = $this->locationFromContext($normalizedContext, $target['region']);
        $workMode = $this->nullableWorkMode($normalizedContext);

        return [
            'detail_url' => $detailUrl,
            'job_title' => $jobTitle,
            'company_name' => $target['company_name'],
            'location' => $location,
            'work_mode' => $workMode,
            'description_text' => Str::limit($contextText, 1600, ''),
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
    private function fallbackPageEntry(string $html, array $target): array
    {
        $pageText = $this->pageText($html);
        $normalizedPageText = $this->normalizeText($pageText);

        if ($normalizedPageText === null) {
            return [];
        }

        if (! $this->hasJobPageSignal($normalizedPageText) || ! $this->hasSoftwareSignal($normalizedPageText)) {
            return [];
        }

        return [[
            'detail_url' => $target['career_url'],
            'job_title' => $this->jobTitleFromPageText($pageText),
            'company_name' => $target['company_name'],
            'location' => $this->locationFromContext($normalizedPageText, $target['region']),
            'work_mode' => $this->nullableWorkMode($normalizedPageText),
            'description_text' => Str::limit($pageText, 1600, ''),
        ]];
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

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
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
