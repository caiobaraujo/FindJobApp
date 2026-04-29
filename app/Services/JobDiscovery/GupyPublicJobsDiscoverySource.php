<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GupyPublicJobsDiscoverySource implements JobDiscoverySource
{
    public const SOURCE_KEY = 'gupy-public-jobs';

    /**
     * @var array<string, true>
     */
    private const EXPIRED_SIGNALS = [
        'closed' => true,
        'encerrada' => true,
        'encerrado' => true,
        'expired' => true,
        'expirada' => true,
        'expirado' => true,
        'job closed' => true,
        'position closed' => true,
        'vaga encerrada' => true,
        'vaga fechada' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const SOFTWARE_SIGNALS = [
        'ai' => true,
        'angular' => true,
        'api' => true,
        'backend' => true,
        'cloud' => true,
        'dados' => true,
        'data' => true,
        'desenvolvedor' => true,
        'desenvolvedora' => true,
        'devops' => true,
        'django' => true,
        'docker' => true,
        'engenheiro' => true,
        'frontend' => true,
        'full stack' => true,
        'fullstack' => true,
        'golang' => true,
        'java' => true,
        'javascript' => true,
        'kubernetes' => true,
        'laravel' => true,
        'mobile' => true,
        'mysql' => true,
        'node' => true,
        'node.js' => true,
        'php' => true,
        'platform engineer' => true,
        'python' => true,
        'qa' => true,
        'quality assurance' => true,
        'react' => true,
        'software' => true,
        'sql' => true,
        'sre' => true,
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
        return 'Gupy Public Jobs';
    }

    public function discoverEntriesWithDiagnostics(): array
    {
        $statusCode = 200;
        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];
        $targets = [];

        foreach (config('job_discovery.gupy_public_job_targets', []) as $target) {
            if (! is_array($target)) {
                continue;
            }

            $listingUrl = $this->nullableString($target['listing_url'] ?? null);

            if ($listingUrl === null) {
                continue;
            }

            $normalizedTarget = [
                'listing_url' => $listingUrl,
                'target_name' => $this->nullableString($target['name'] ?? null),
                'company_name' => $this->nullableString($target['name'] ?? null),
                'platform' => 'gupy',
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
        $fallback = [
            'source_url' => $this->nullableString($entry['detail_url'] ?? null),
            'job_title' => $this->nullableString($entry['job_title'] ?? null),
            'company_name' => $this->nullableString($entry['company_name'] ?? null),
            'location' => $this->nullableString($entry['location'] ?? null),
            'work_mode' => $this->nullableWorkMode($entry['work_mode'] ?? null),
            'description_text' => $this->nullableString($entry['description_text'] ?? null),
            'source_platform' => 'gupy',
            'target_identifier' => $this->nullableString($entry['target_identifier'] ?? null),
            'target_name' => $this->nullableString($entry['target_name'] ?? null),
            '_detail_enrichment_status' => 'failed',
        ];

        $detailUrl = $this->nullableString($entry['detail_url'] ?? null);

        if ($detailUrl === null) {
            return $fallback;
        }

        $detailPage = $this->fetchDetailPageHtml($detailUrl);

        if ($detailPage === null || ! $detailPage['successful']) {
            return $fallback;
        }

        $detailedEntry = $this->detailEntry($detailPage['body'], $entry);

        if ($detailedEntry === null) {
            return $fallback;
        }

        return [
            ...$detailedEntry,
            '_detail_enrichment_status' => 'success',
        ];
    }

    /**
     * @param array{
     *     listing_url: string,
     *     target_name: string|null,
     *     company_name: string|null,
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
     *         source_platform: string,
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
     * @return array{successful: bool, status_code: int, body: string}|null
     */
    private function fetchListingPageHtml(string $listingUrl): ?array
    {
        if (config('job_discovery.use_fixture_responses')) {
            $fixtureResponses = config('job_discovery.fixture_responses.gupy_public_jobs', []);
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
     * @return array{successful: bool, status_code: int, body: string}|null
     */
    private function fetchDetailPageHtml(string $detailUrl): ?array
    {
        if (config('job_discovery.use_fixture_responses')) {
            $fixtureResponses = config('job_discovery.fixture_responses.gupy_public_jobs', []);
            $fixturePath = is_array($fixtureResponses) ? ($fixtureResponses[$detailUrl] ?? null) : null;

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

            return null;
        }

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
     *     company_name: string|null,
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
     *     source_platform: string,
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
    ): ?array {
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

        $jobTitle = $this->jobTitleFromLink($xpath, $link, $contextNode);

        if ($jobTitle === null || ! $this->softwareTextMatches($jobTitle.' '.$contextText)) {
            return null;
        }

        $companyName = $target['company_name'] ?? null;

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
            'description_text' => Str::limit($contextText, 2000, ''),
            'source_platform' => 'gupy',
            'target_identifier' => $this->targetIdentifier($target),
            'target_name' => $target['target_name'] ?? $this->targetIdentifier($target),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, string|null>|null
     */
    private function detailEntry(string $html, array $entry): ?array
    {
        $xpath = $this->xpath($html);
        $jobTitle = $this->nodeText($xpath, null, '//h1[1]');
        $companyName = $this->nullableString($entry['company_name'] ?? null);
        $descriptionText = $this->detailDescriptionText($xpath) ?? $this->pageText($html);

        if ($this->isExpired($descriptionText ?? '')) {
            return null;
        }

        if (! $this->isCompleteEntry($this->nullableString($entry['detail_url'] ?? null), $jobTitle, $companyName)) {
            return null;
        }

        $location = $this->locationFromDetailText($xpath, $descriptionText);

        return [
            'source_url' => $this->nullableString($entry['detail_url'] ?? null),
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'location' => $location,
            'work_mode' => $this->nullableWorkMode($location ?? $descriptionText),
            'description_text' => Str::limit($descriptionText ?? '', 4000, ''),
            'source_platform' => 'gupy',
            'target_identifier' => $this->nullableString($entry['target_identifier'] ?? null),
            'target_name' => $this->nullableString($entry['target_name'] ?? null),
        ];
    }

    private function candidateDetailUrl(DOMElement $link, array $target): ?string
    {
        $href = $this->nullableString($link->getAttribute('href'));

        if ($href === null) {
            return null;
        }

        $detailUrl = $this->absoluteUrl($href, $target['listing_url']) ?? $href;

        return $this->isSupportedJobUrl($detailUrl) ? $detailUrl : null;
    }

    /**
     * @param array<string, mixed> $target
     * @return array<string, int|string|null>
     */
    private function initialTargetSummary(array $target): array
    {
        $targetIdentifier = $this->targetIdentifier($target);

        return [
            'target_identifier' => $targetIdentifier,
            'target_name' => $target['target_name'] ?? $targetIdentifier,
            'platform' => $target['platform'] ?? 'gupy',
            'parser_strategy' => $target['parser_strategy'] ?? 'gupy_listing',
            'candidate_links' => 0,
            'invalid_links' => 0,
            'parsed_jobs' => 0,
            'skipped_expired' => 0,
            'skipped_missing_company' => 0,
            'detail_enrichment_succeeded' => 0,
            'detail_enrichment_failed' => 0,
            'failed' => 0,
        ];
    }

    private function parserStrategy(array $target): string
    {
        return $this->nullableString($target['parser_strategy'] ?? null) ?? 'gupy_listing';
    }

    private function targetIdentifier(array $target): string
    {
        return $target['target_name']
            ?? $target['company_name']
            ?? $target['listing_url'];
    }

    private function isSupportedJobUrl(string $detailUrl): bool
    {
        $host = parse_url($detailUrl, PHP_URL_HOST);
        $path = parse_url($detailUrl, PHP_URL_PATH);

        if (! is_string($host) || ! str_contains($host, 'gupy.io') || ! is_string($path)) {
            return false;
        }

        return preg_match('#/jobs/\d+#', $path) === 1;
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

            if (
                str_contains($className, 'job')
                || str_contains($className, 'vaga')
                || str_contains($className, 'card')
                || str_contains($className, 'opening')
            ) {
                return $node;
            }

            $node = $node->parentNode;
        }

        return $link->parentNode;
    }

    private function jobTitleFromLink(DOMXPath $xpath, DOMElement $link, ?DOMNode $contextNode): ?string
    {
        $selectors = [
            './/*[self::h1 or self::h2 or self::h3][1]',
            './/*[contains(@class, "job-title")][1]',
            './/*[contains(@class, "title")][1]',
            './/*[contains(@class, "opening")][1]',
        ];

        foreach ($selectors as $selector) {
            $jobTitle = $this->nodeText($xpath, $contextNode, $selector);

            if ($jobTitle !== null) {
                return $jobTitle;
            }
        }

        return $this->nullableString($link->textContent);
    }

    private function locationFromContext(DOMXPath $xpath, ?DOMNode $contextNode, string $contextText): ?string
    {
        $selectors = [
            './/*[contains(@class, "location")][1]',
            './/*[contains(@class, "cidade")][1]',
            './/*[contains(@class, "city")][1]',
            './/*[contains(@class, "local")][1]',
        ];

        foreach ($selectors as $selector) {
            $location = $this->nodeText($xpath, $contextNode, $selector);

            if ($location !== null) {
                return $location;
            }
        }

        return $this->locationTextFromText($contextText);
    }

    private function locationFromDetailText(DOMXPath $xpath, ?string $detailText): ?string
    {
        $selectors = [
            '//main//*[contains(@class, "location")][1]',
            '//main//*[contains(@class, "city")][1]',
            '//main//*[contains(@class, "local")][1]',
        ];

        foreach ($selectors as $selector) {
            $location = $this->nodeText($xpath, null, $selector);

            if ($location !== null) {
                return $location;
            }
        }

        return $this->locationTextFromText($detailText);
    }

    private function locationTextFromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\b(Remoto|Remote|H[íi]brido|H[íi]brida|Presencial)\b/u', $text, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        if (preg_match('/\b([A-ZÀ-Ý][\p{L} .-]+(?:,\s*[A-Z]{2})?(?:,\s*Brasil)?)\b/u', $text, $matches) === 1) {
            return $this->nullableString($matches[1] ?? null);
        }

        return null;
    }

    private function detailDescriptionText(DOMXPath $xpath): ?string
    {
        $selectors = [
            '//main//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "job-description")]',
            '//main//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "description")]',
            '//article',
            '//main',
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }

                $text = $this->normalizeText($node->textContent);

                if ($text !== null && mb_strlen($text) >= 180) {
                    return $text;
                }
            }
        }

        return null;
    }

    private function pageText(string $html): ?string
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        foreach (['script', 'style', 'noscript', 'svg', 'header', 'footer', 'nav'] as $tagName) {
            while (true) {
                $nodes = $document->getElementsByTagName($tagName);

                if ($nodes->length === 0) {
                    break;
                }

                $node = $nodes->item(0);

                if ($node === null || $node->parentNode === null) {
                    break;
                }

                $node->parentNode->removeChild($node);
            }
        }

        return $this->normalizeText($document->textContent);
    }

    private function isExpired(string $text): bool
    {
        $normalizedText = mb_strtolower(Str::ascii($text));

        foreach (array_keys(self::EXPIRED_SIGNALS) as $signal) {
            if (str_contains($normalizedText, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function softwareTextMatches(string $text): bool
    {
        $normalizedText = $this->normalizeText($text);

        if ($normalizedText === null) {
            return false;
        }

        $normalizedText = mb_strtolower(Str::ascii($normalizedText));

        foreach (array_keys(self::SOFTWARE_SIGNALS) as $signal) {
            if (str_contains($normalizedText, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function isCompleteEntry(?string $detailUrl, ?string $jobTitle, ?string $companyName): bool
    {
        return $detailUrl !== null
            && filter_var($detailUrl, FILTER_VALIDATE_URL) !== false
            && $jobTitle !== null
            && $companyName !== null;
    }

    private function absoluteUrl(string $url, string $baseUrl): ?string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($baseScheme) || ! is_string($baseHost)) {
            return null;
        }

        if (Str::startsWith($url, '//')) {
            return $baseScheme.':'.$url;
        }

        if (Str::startsWith($url, '/')) {
            return $baseScheme.'://'.$baseHost.$url;
        }

        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        $baseDirectory = is_string($basePath) ? rtrim(dirname($basePath), '/') : '';

        return $baseScheme.'://'.$baseHost.$baseDirectory.'/'.$url;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function nodeText(DOMXPath $xpath, ?DOMNode $contextNode, string $selector): ?string
    {
        $nodes = $xpath->query($selector, $contextNode);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }

            $text = $this->normalizeText($node->textContent);

            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function nullableWorkMode(?string $text): ?string
    {
        $normalizedText = $this->normalizeText($text);

        if ($normalizedText === null) {
            return null;
        }

        $asciiText = mb_strtolower(Str::ascii($normalizedText));

        if (str_contains($asciiText, 'remote') || str_contains($asciiText, 'remoto') || str_contains($asciiText, 'home office')) {
            return JobLead::WORK_MODE_REMOTE;
        }

        if (str_contains($asciiText, 'hybrid') || str_contains($asciiText, 'hibrido') || str_contains($asciiText, 'hibrida')) {
            return JobLead::WORK_MODE_HYBRID;
        }

        if (str_contains($asciiText, 'onsite') || str_contains($asciiText, 'presencial')) {
            return JobLead::WORK_MODE_ONSITE;
        }

        return null;
    }

    private function normalizeText(?string $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
