<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BrazilianTechJobBoardsDiscoverySource implements JobDiscoverySource
{
    public const SOURCE_KEY = 'brazilian-tech-job-boards';

    /**
     * @var array<string, true>
     */
    private const EXPIRED_SIGNALS = [
        'encerrada' => true,
        'encerrado' => true,
        'expired' => true,
        'expirada' => true,
        'expirado' => true,
        'vaga encerrada' => true,
        'vaga vencida' => true,
        'vencida' => true,
        'vencido' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const SOFTWARE_SIGNALS = [
        'ai' => true,
        'angular' => true,
        'api' => true,
        'backend' => true,
        'chatbot' => true,
        'cloud' => true,
        'dados' => true,
        'data' => true,
        'desenvolvedor' => true,
        'desenvolvedora' => true,
        'devops' => true,
        'django' => true,
        'docker' => true,
        'engenheiro de software' => true,
        'frontend' => true,
        'full stack' => true,
        'fullstack' => true,
        'golang' => true,
        'java' => true,
        'javascript' => true,
        'laravel' => true,
        'llm' => true,
        'mobile' => true,
        'mysql' => true,
        'nlp' => true,
        'node' => true,
        'node.js' => true,
        'openai' => true,
        'php' => true,
        'python' => true,
        'qa' => true,
        'react' => true,
        'software' => true,
        'sql' => true,
        'sre' => true,
        'stack' => true,
        'tecnologia' => true,
        'typescript' => true,
        'vue' => true,
    ];

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'Brazilian Tech Job Boards';
    }

    public function discoverEntriesWithDiagnostics(): array
    {
        $statusCode = 200;
        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];
        $targets = [];

        foreach (config('job_discovery.brazilian_tech_job_board_targets', []) as $target) {
            if (! is_array($target)) {
                continue;
            }

            foreach ($target['listing_urls'] ?? [] as $listingUrl) {
                $listingUrl = $this->nullableString($listingUrl);

                if ($listingUrl === null) {
                    continue;
                }

                $normalizedTarget = [
                    'listing_url' => $listingUrl,
                    'target_name' => $this->nullableString($target['name'] ?? null),
                    'platform' => $this->nullableString($target['platform'] ?? null),
                    'parser_strategy' => $this->parserStrategy($target),
                ];
                $targetIdentifier = $this->targetIdentifier($normalizedTarget);
                $targets[$targetIdentifier] ??= $this->initialTargetSummary($normalizedTarget);

                $pageFetch = $this->fetchListingPageHtml($listingUrl);

                if ($pageFetch === null) {
                    $statusCode = max($statusCode, 500);
                    $targets[$targetIdentifier]['failed']++;

                    continue;
                }

                $statusCode = max($statusCode, $pageFetch['status_code']);

                if (! $pageFetch['successful']) {
                    $targets[$targetIdentifier]['failed']++;

                    continue;
                }

                $parsed = $this->parseListingHtmlWithDiagnostics($pageFetch['body'], $normalizedTarget);

                $candidateCount += $parsed['candidate_links'];
                $invalidCount += $parsed['invalid_links'];
                $entries = array_merge($entries, $parsed['entries']);
                $targets[$targetIdentifier]['candidate_links'] += $parsed['candidate_links'];
                $targets[$targetIdentifier]['invalid_links'] += $parsed['invalid_links'];
                $targets[$targetIdentifier]['parsed_jobs'] += count($parsed['entries']);
                $targets[$targetIdentifier]['skipped_expired'] += $parsed['skipped_expired'];
                $targets[$targetIdentifier]['skipped_missing_company'] += $parsed['skipped_missing_company'];
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
        $liveDetail = $this->liveDetailEntry($entry);

        if ($liveDetail !== null) {
            return $liveDetail;
        }

        return [
            'source_url' => $this->nullableString($entry['detail_url'] ?? null),
            'job_title' => $this->nullableString($entry['job_title'] ?? null),
            'company_name' => $this->nullableString($entry['company_name'] ?? null),
            'location' => $this->nullableString($entry['location'] ?? null),
            'work_mode' => $this->nullableWorkMode($entry['work_mode'] ?? null),
            'description_text' => $this->nullableString($entry['description_text'] ?? null),
            'source_platform' => $this->nullableString($entry['source_platform'] ?? null),
            'target_identifier' => $this->nullableString($entry['target_identifier'] ?? null),
            'target_name' => $this->nullableString($entry['target_name'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string|null>|null
     */
    private function liveDetailEntry(array $entry): ?array
    {
        if (config('job_discovery.use_fixture_responses')) {
            return null;
        }

        $detailUrl = $this->nullableString($entry['detail_url'] ?? null);
        $sourcePlatform = $this->nullableString($entry['source_platform'] ?? null);

        if ($detailUrl === null || $sourcePlatform === null) {
            return null;
        }

        $detailPage = $this->fetchDetailPageHtml($detailUrl);

        if ($detailPage === null || ! $detailPage['successful']) {
            return null;
        }

        return match ($sourcePlatform) {
            'programathor' => $this->programathorDetailEntry($detailPage['body'], $entry),
            'remotar' => $this->remotarDetailEntry($detailPage['body'], $entry),
            default => null,
        };
    }

    /**
     * @param array{
     *     listing_url: string,
     *     target_name: string|null,
     *     platform: string|null,
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
     *         description_text: string|null,
     *         source_platform: string|null,
     *         target_identifier: string,
     *         target_name: string
     *     }>,
     *     skipped_expired: int,
     *     skipped_missing_company: int
     * }
     */
    public function parseListingHtmlWithDiagnostics(string $html, array $target): array
    {
        $xpath = $this->xpath($html);
        $links = $xpath->query('//a[@href]');

        if ($links === false) {
            return [
                'candidate_links' => 0,
                'invalid_links' => 0,
                'entries' => [],
                'skipped_expired' => 0,
                'skipped_missing_company' => 0,
            ];
        }

        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];
        $seenUrls = [];
        $skippedExpiredCount = 0;
        $skippedMissingCompanyCount = 0;

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $detailUrl = $this->candidateDetailUrl($link, $target);

            if ($detailUrl === null) {
                continue;
            }

            $candidateCount++;

            if (! filter_var($detailUrl, FILTER_VALIDATE_URL)) {
                $invalidCount++;

                continue;
            }

            $entry = $this->entryFromLink($xpath, $link, $target, $skippedExpiredCount, $skippedMissingCompanyCount);

            if ($entry === null) {
                continue;
            }

            if (in_array($entry['detail_url'], $seenUrls, true)) {
                continue;
            }

            $seenUrls[] = $entry['detail_url'];
            $entries[] = $entry;
        }

        return [
            'candidate_links' => $candidateCount,
            'invalid_links' => $invalidCount,
            'entries' => $entries,
            'skipped_expired' => $skippedExpiredCount,
            'skipped_missing_company' => $skippedMissingCompanyCount,
        ];
    }

    /**
     * @param array{
     *     listing_url: string,
     *     target_name: string|null,
     *     platform: string|null,
     *     parser_strategy?: string|null
     * } $target
     * @return array{
     *     successful: bool,
     *     status_code: int,
     *     body: string
     * }|null
     */
    private function fetchListingPageHtml(string $listingUrl): ?array
    {
        if (config('job_discovery.use_fixture_responses')) {
            $fixtureResponses = config('job_discovery.fixture_responses.brazilian_tech_job_boards', []);
            $fixturePath = is_array($fixtureResponses) ? ($fixtureResponses[$listingUrl] ?? null) : null;

            if (is_string($fixturePath) && is_file($fixturePath)) {
                $fixtureBody = file_get_contents($fixturePath);

                if (is_string($fixtureBody)) {
                    return [
                        'successful' => true,
                        'status_code' => 200,
                        'body' => $fixtureBody,
                    ];
                }
            }
        }

        $response = Http::timeout(10)
            ->accept('text/html')
            ->withUserAgent('FindJobApp/1.0')
            ->get($listingUrl);

        return [
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'body' => $response->body(),
        ];
    }

    /**
     * @return array{
     *     successful: bool,
     *     status_code: int,
     *     body: string
     * }|null
     */
    private function fetchDetailPageHtml(string $detailUrl): ?array
    {
        $response = Http::timeout(10)
            ->accept('text/html')
            ->withUserAgent('FindJobApp/1.0')
            ->get($detailUrl);

        return [
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'body' => $response->body(),
        ];
    }

    /**
     * @param array{
     *     listing_url: string,
     *     target_name: string|null,
     *     platform: string|null,
     *     parser_strategy?: string|null
     * } $target
     * @return array{
     *     detail_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     work_mode: string|null,
     *     description_text: string|null,
     *     source_platform: string|null,
     *     target_identifier: string,
     *     target_name: string
     * }|null
     */
    private function entryFromLink(
        DOMXPath $xpath,
        DOMElement $link,
        array $target,
        int &$skippedExpiredCount,
        int &$skippedMissingCompanyCount,
    ): ?array
    {
        $detailUrl = $this->candidateDetailUrl($link, $target);

        if ($detailUrl === null) {
            return null;
        }

        $contextNode = $this->contextNode($link);
        $contextText = $this->normalizeText($contextNode?->textContent);

        if ($contextText === null) {
            return null;
        }

        if ($this->isExpired($contextText)) {
            $skippedExpiredCount++;

            return null;
        }

        $jobTitle = $this->jobTitleFromLink($link->textContent);

        if ($jobTitle === null || ! $this->softwareTextMatches($jobTitle.' '.$contextText)) {
            return null;
        }

        $companyName = $this->companyNameFromContext($xpath, $contextNode, $jobTitle, $contextText);

        if ($companyName === null) {
            $skippedMissingCompanyCount++;

            return null;
        }

        if (! $this->isCompleteEntry($detailUrl, $jobTitle, $companyName)) {
            return null;
        }

        return [
            'detail_url' => $detailUrl,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'location' => $this->locationFromContext($xpath, $contextNode, $contextText),
            'work_mode' => $this->nullableWorkMode($contextText),
            'description_text' => Str::limit($contextText, 1600, ''),
            'source_platform' => $target['platform'],
            'target_identifier' => $this->targetIdentifier($target),
            'target_name' => $target['target_name'] ?? $this->targetIdentifier($target),
        ];
    }

    /**
     * @param array{
     *     listing_url: string,
     *     target_name: string|null,
     *     platform: string|null,
     *     parser_strategy?: string|null
     * } $target
     * @return array<string, int|string|null>
     */
    private function initialTargetSummary(array $target): array
    {
        $targetIdentifier = $this->targetIdentifier($target);

        return [
            'target_identifier' => $targetIdentifier,
            'target_name' => $target['target_name'] ?? $targetIdentifier,
            'platform' => $target['platform'] ?? $targetIdentifier,
            'parser_strategy' => $target['parser_strategy'] ?? 'programathor_cards',
            'candidate_links' => 0,
            'invalid_links' => 0,
            'parsed_jobs' => 0,
            'skipped_expired' => 0,
            'skipped_missing_company' => 0,
            'failed' => 0,
        ];
    }

    private function candidateDetailUrl(DOMElement $link, array $target): ?string
    {
        $href = $this->nullableString($link->getAttribute('href'));

        if ($href === null) {
            return null;
        }

        $detailUrl = $this->absoluteUrl($href, $target['listing_url']) ?? $href;

        return $this->isSupportedJobUrl($detailUrl, $target)
            ? $detailUrl
            : null;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string|null>|null
     */
    private function programathorDetailEntry(string $html, array $entry): ?array
    {
        $xpath = $this->xpath($html);
        $pageText = $this->pageText($html);
        $jobTitle = $this->nodeText($xpath, null, '//h1[1]');
        $companyName = $this->nodeText($xpath, null, '//h2[1]');
        $location = $this->locationFromDetailText($pageText);

        if (! $this->isCompleteEntry($this->nullableString($entry['detail_url'] ?? null), $jobTitle, $companyName)) {
            return null;
        }

        return [
            'source_url' => $this->nullableString($entry['detail_url'] ?? null),
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'location' => $location,
            'work_mode' => $this->nullableWorkMode($location ?? $pageText),
            'description_text' => Str::limit($pageText, 1600, ''),
            'source_platform' => 'programathor',
            'target_identifier' => $this->nullableString($entry['target_identifier'] ?? null),
            'target_name' => $this->nullableString($entry['target_name'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string|null>|null
     */
    private function remotarDetailEntry(string $html, array $entry): ?array
    {
        $xpath = $this->xpath($html);
        $pageText = $this->pageText($html);
        $jobTitle = $this->nodeText($xpath, null, '//h1[1]');
        $companyName = $this->nodeText($xpath, null, '//h1/following::*[self::h2 or self::div][1]');

        if ($companyName === null) {
            $companyName = $this->companyNameFromPageText($pageText);
        }

        if (! $this->isCompleteEntry($this->nullableString($entry['detail_url'] ?? null), $jobTitle, $companyName)) {
            return null;
        }

        return [
            'source_url' => $this->nullableString($entry['detail_url'] ?? null),
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'location' => $this->locationFromDetailText($pageText),
            'work_mode' => $this->nullableWorkMode($pageText),
            'description_text' => Str::limit($pageText, 1600, ''),
            'source_platform' => 'remotar',
            'target_identifier' => $this->nullableString($entry['target_identifier'] ?? null),
            'target_name' => $this->nullableString($entry['target_name'] ?? null),
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function parserStrategy(array $target): string
    {
        $parserStrategy = $this->nullableString($target['parser_strategy'] ?? null);

        return $parserStrategy ?? 'programathor_cards';
    }

    private function targetIdentifier(array $target): string
    {
        return $target['target_name']
            ?? $target['platform']
            ?? $target['listing_url'];
    }

    private function isSupportedJobUrl(string $detailUrl, array $target): bool
    {
        $host = parse_url($detailUrl, PHP_URL_HOST);
        $path = parse_url($detailUrl, PHP_URL_PATH);

        if (! is_string($host) || $host === '' || ! is_string($path) || $path === '') {
            return false;
        }

        $strategy = $this->parserStrategy($target);

        return match ($strategy) {
            'remotar_cards' => str_contains($host, 'remotar.com.br') && str_contains($path, '/job/'),
            default => str_contains($host, 'programathor.com.br') && str_contains($path, '/jobs/'),
        };
    }

    private function contextNode(DOMElement $link): ?DOMNode
    {
        $node = $link->parentNode;

        while ($node instanceof DOMElement) {
            $tagName = strtolower($node->tagName);
            $className = strtolower((string) $node->getAttribute('class'));

            if (in_array($tagName, ['article', 'li', 'section'], true)) {
                return $node;
            }

            if (str_contains($className, 'job') || str_contains($className, 'vaga') || str_contains($className, 'card')) {
                return $node;
            }

            $node = $node->parentNode;
        }

        return $link->parentNode;
    }

    private function isExpired(string $contextText): bool
    {
        $normalized = mb_strtolower(Str::ascii($contextText));

        foreach (array_keys(self::EXPIRED_SIGNALS) as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function softwareTextMatches(string $text): bool
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === null) {
            return false;
        }

        $normalized = mb_strtolower(Str::ascii($normalized));

        foreach (array_keys(self::SOFTWARE_SIGNALS) as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function jobTitleFromLink(?string $text): ?string
    {
        $title = $this->normalizeText($text);

        if ($title === null) {
            return null;
        }

        $title = preg_replace('/\s+NOVA$/iu', '', $title) ?? $title;
        $title = preg_replace('/\s+nova$/iu', '', $title) ?? $title;

        return $this->nullableString($title);
    }

    private function companyNameFromContext(DOMXPath $xpath, ?DOMNode $contextNode, string $jobTitle, string $contextText): ?string
    {
        $companySelectors = [
            './/*[contains(@class, "company")][1]',
            './/*[contains(@class, "empresa")][1]',
            './/*[contains(@class, "employer")][1]',
            './/*[contains(@class, "client")][1]',
        ];

        foreach ($companySelectors as $selector) {
            $companyName = $this->nodeText($xpath, $contextNode, $selector);

            if ($companyName !== null && ! $this->looksLikeJobTitleOrNoise($companyName, $jobTitle)) {
                return $companyName;
            }
        }

        if (preg_match('/\b(?:na|at)\s+([A-Z][\p{L}\p{N}&.\- ]{1,80})$/u', $contextText, $matches) === 1) {
            $companyName = $this->nullableString($matches[1] ?? null);

            if ($companyName !== null && ! $this->looksLikeJobTitleOrNoise($companyName, $jobTitle)) {
                return $companyName;
            }
        }

        return null;
    }

    private function locationFromContext(DOMXPath $xpath, ?DOMNode $contextNode, string $contextText): ?string
    {
        $locationSelectors = [
            './/*[contains(@class, "location")][1]',
            './/*[contains(@class, "cidade")][1]',
            './/*[contains(@class, "city")][1]',
        ];

        foreach ($locationSelectors as $selector) {
            $location = $this->nodeText($xpath, $contextNode, $selector);

            if ($location !== null) {
                return $location;
            }
        }

        if (preg_match('/\b([A-Z][\p{L} .-]+,\s*Brasil)\b/u', $contextText, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        if (preg_match('/\b(Brasil|Brazil)\b/u', $contextText, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        return null;
    }

    private function locationFromDetailText(?string $pageText): ?string
    {
        if ($pageText === null) {
            return null;
        }

        if (preg_match('/Localiza(?:ç|c)[aã]o:\s*([^\n]+)/iu', $pageText, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        if (preg_match('/(Remoto|Home Office \(Remoto\)|[A-ZÀ-Ý][\p{L} .-]+ \((?:Híbrido|Presencial)\))/u', $pageText, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        return null;
    }

    private function companyNameFromPageText(?string $pageText): ?string
    {
        if ($pageText === null) {
            return null;
        }

        if (preg_match('/Hey!.*?([A-Z][\p{L}\p{N}&. -]{2,80})Somos/usu', $pageText, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        return null;
    }

    private function pageText(string $html): ?string
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return $this->normalizeText($document->textContent);
    }

    private function nodeText(DOMXPath $xpath, ?DOMNode $contextNode, string $selector): ?string
    {
        $nodes = $contextNode === null
            ? $xpath->query($selector)
            : $xpath->query($selector, $contextNode);

        if ($nodes === false || count($nodes) === 0) {
            return null;
        }

        return $this->normalizeText($nodes->item(0)?->textContent);
    }

    private function looksLikeJobTitleOrNoise(string $companyName, string $jobTitle): bool
    {
        $normalizedCompany = mb_strtolower(Str::ascii($companyName));
        $normalizedTitle = mb_strtolower(Str::ascii($jobTitle));

        if ($normalizedCompany === $normalizedTitle) {
            return true;
        }

        return str_contains($normalizedCompany, 'vaga')
            || str_contains($normalizedCompany, 'remoto')
            || str_contains($normalizedCompany, 'hybrid')
            || str_contains($normalizedCompany, 'presencial');
    }

    private function isCompleteEntry(?string $detailUrl, ?string $jobTitle, ?string $companyName): bool
    {
        return $detailUrl !== null
            && filter_var($detailUrl, FILTER_VALIDATE_URL)
            && $jobTitle !== null
            && $companyName !== null;
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

    private function nullableWorkMode(mixed $value): ?string
    {
        $normalized = $this->normalizeText(is_string($value) ? $value : null);

        if ($normalized === null) {
            return null;
        }

        $normalized = mb_strtolower(Str::ascii($normalized));

        if (str_contains($normalized, 'hybrid') || str_contains($normalized, 'hibrido')) {
            return JobLead::WORK_MODE_HYBRID;
        }

        if (str_contains($normalized, 'remote') || str_contains($normalized, 'remoto') || str_contains($normalized, 'home office')) {
            return JobLead::WORK_MODE_REMOTE;
        }

        if (str_contains($normalized, 'onsite') || str_contains($normalized, 'on site') || str_contains($normalized, 'presencial')) {
            return JobLead::WORK_MODE_ONSITE;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeText(?string $text): ?string
    {
        $trimmed = $this->nullableString($text);

        if ($trimmed === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return trim($normalized);
    }
}
