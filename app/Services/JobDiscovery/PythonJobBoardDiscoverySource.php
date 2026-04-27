<?php

namespace App\Services\JobDiscovery;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PythonJobBoardDiscoverySource
{
    public const SOURCE_KEY = 'python-job-board';

    private const LISTING_URL = 'https://www.python.org/jobs/';

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'Python Job Board';
    }

    /**
     * @return list<array{detail_url: string, job_title: string|null, company_name: string|null, location: string|null}>
     */
    public function discoverEntries(): array
    {
        return $this->discoverEntriesWithDiagnostics()['entries'];
    }

    /**
     * @return array{
     *     status_code: int,
     *     candidate_links: int,
     *     parsed_jobs: int,
     *     invalid_links: int,
     *     entries: list<array{detail_url: string, job_title: string|null, company_name: string|null, location: string|null}>
     * }
     */
    public function discoverEntriesWithDiagnostics(): array
    {
        $response = Http::timeout(10)
            ->accept('text/html')
            ->withUserAgent('FindJobApp/1.0')
            ->get(self::LISTING_URL);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Failed to fetch the Python Job Board listing page (HTTP %d).',
                $response->status(),
            ));
        }

        $parsed = $this->parseListingHtmlWithDiagnostics($response->body());

        return [
            'status_code' => $response->status(),
            'candidate_links' => $parsed['candidate_links'],
            'parsed_jobs' => count($parsed['entries']),
            'invalid_links' => $parsed['invalid_links'],
            'entries' => $parsed['entries'],
        ];
    }

    /**
     * @param array{detail_url: string, job_title: string|null, company_name: string|null, location: string|null} $entry
     * @return array{source_url: string, job_title: string|null, company_name: string|null, location: string|null, description_text: string|null}
     */
    public function enrichEntry(array $entry): array
    {
        $response = Http::timeout(10)
            ->accept('text/html')
            ->get($entry['detail_url']);

        if (! $response->successful()) {
            throw new RuntimeException("Failed to fetch job detail page [{$entry['detail_url']}].");
        }

        return $this->parseDetailHtml($response->body(), $entry);
    }

    /**
     * @return list<array{detail_url: string, job_title: string|null, company_name: string|null, location: string|null}>
     */
    public function parseListingHtml(string $html): array
    {
        return $this->parseListingHtmlWithDiagnostics($html)['entries'];
    }

    /**
     * @return array{
     *     candidate_links: int,
     *     invalid_links: int,
     *     entries: list<array{detail_url: string, job_title: string|null, company_name: string|null, location: string|null}>
     * }
     */
    public function parseListingHtmlWithDiagnostics(string $html): array
    {
        $xpath = $this->xpath($html);
        $jobLinks = $this->listingCandidateNodes($xpath);

        if ($jobLinks === false) {
            return [
                'candidate_links' => 0,
                'invalid_links' => 0,
                'entries' => [],
            ];
        }

        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];
        $seenDetailUrls = [];

        foreach ($jobLinks as $jobLink) {
            if (! $jobLink instanceof DOMElement) {
                continue;
            }

            $href = trim($jobLink->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            $candidateCount++;

            $detailUrl = $this->absoluteUrl($href, self::LISTING_URL) ?? $href;

            if (! $this->isJobDetailUrl($detailUrl)) {
                $invalidCount++;

                continue;
            }

            if (in_array($detailUrl, $seenDetailUrls, true)) {
                continue;
            }

            $seenDetailUrls[] = $detailUrl;
            $jobTitle = $this->normalizedText($jobLink->textContent);
            $listItem = $this->ancestorListItem($jobLink);
            $location = $this->listingLocation($xpath, $listItem);
            $companyName = $this->listingCompanyName($listItem, $jobTitle, $location);

            $entries[] = [
                'detail_url' => $detailUrl,
                'job_title' => $jobTitle,
                'company_name' => $companyName,
                'location' => $location,
            ];
        }

        return [
            'candidate_links' => $candidateCount,
            'invalid_links' => $invalidCount,
            'entries' => $entries,
        ];
    }

    private function listingCandidateNodes(DOMXPath $xpath): \DOMNodeList|false
    {
        $listingTitleLinks = $xpath->query(
            '//ol[contains(@class, "list-recent-jobs")]//span[contains(@class, "listing-company-name")]//a[@href]'
        );

        if ($listingTitleLinks !== false && $listingTitleLinks->length > 0) {
            return $listingTitleLinks;
        }

        $listingHeadingLinks = $xpath->query(
            '//ol[contains(@class, "list-recent-jobs")]//h2//a[@href] | //ol[contains(@class, "list-recent-jobs")]//h3//a[@href]'
        );

        if ($listingHeadingLinks !== false && $listingHeadingLinks->length > 0) {
            return $listingHeadingLinks;
        }

        return $xpath->query('//h1//a[@href] | //h2//a[@href] | //h3//a[@href]');
    }

    /**
     * @param array{detail_url: string, job_title: string|null, company_name: string|null, location: string|null} $entry
     * @return array{source_url: string, job_title: string|null, company_name: string|null, location: string|null, description_text: string|null}
     */
    public function parseDetailHtml(string $html, array $entry): array
    {
        $xpath = $this->xpath($html);
        $jobTitle = $this->sectionValue($xpath, 'Job Title') ?? $entry['job_title'];
        $descriptionText = $this->sectionText($xpath, 'Job Description');
        $sourceUrl = $this->sectionExternalUrl($xpath, 'Job Description')
            ?? $this->sectionExternalUrl($xpath, 'Contact Info')
            ?? $entry['detail_url'];

        return [
            'source_url' => $sourceUrl,
            'job_title' => $jobTitle,
            'company_name' => $entry['company_name'],
            'location' => $entry['location'],
            'description_text' => $descriptionText,
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

    private function isJobDetailUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($host) || ! str_contains($host, 'python.org')) {
            return false;
        }

        if (! is_string($path) || $path === '') {
            return false;
        }

        return preg_match('#^/jobs/\d+/?$#', $path) === 1;
    }

    private function ancestorListItem(DOMNode $node): ?DOMElement
    {
        $currentNode = $node->parentNode;

        while ($currentNode instanceof DOMNode) {
            if ($currentNode instanceof DOMElement && $currentNode->tagName === 'li') {
                return $currentNode;
            }

            $currentNode = $currentNode->parentNode;
        }

        return null;
    }

    private function listingLocation(DOMXPath $xpath, ?DOMElement $listItem): ?string
    {
        if ($listItem === null) {
            return null;
        }

        $locationNode = $xpath->query('.//a[contains(@href, "/jobs/location/")][1]', $listItem)?->item(0);

        if (! $locationNode instanceof DOMNode) {
            return null;
        }

        return $this->normalizedText($locationNode->textContent);
    }

    private function listingCompanyName(?DOMElement $listItem, ?string $jobTitle, ?string $location): ?string
    {
        if ($listItem === null) {
            return null;
        }

        $text = $this->normalizedText($listItem->textContent);

        if ($text === null || $jobTitle === null) {
            return null;
        }

        $afterTitle = Str::of($text)
            ->after($jobTitle)
            ->replaceMatches('/\bNew\b/', '')
            ->trim()
            ->value();

        if ($afterTitle === '') {
            return null;
        }

        if ($location !== null && str_contains($afterTitle, $location)) {
            $companyName = trim(Str::before($afterTitle, $location));

            return $companyName === '' ? null : $companyName;
        }

        return null;
    }

    private function sectionValue(DOMXPath $xpath, string $heading): ?string
    {
        $sectionHeading = $this->sectionHeading($xpath, $heading);

        if (! $sectionHeading instanceof DOMNode) {
            return null;
        }

        $nextNode = $sectionHeading->nextSibling;

        while ($nextNode instanceof DOMNode) {
            if ($nextNode instanceof DOMElement && $nextNode->tagName === 'h2') {
                return null;
            }

            $text = $this->normalizedText($nextNode->textContent);

            if ($text !== null) {
                return $text;
            }

            $nextNode = $nextNode->nextSibling;
        }

        return null;
    }

    private function sectionText(DOMXPath $xpath, string $heading): ?string
    {
        $sectionHeading = $this->sectionHeading($xpath, $heading);

        if (! $sectionHeading instanceof DOMNode) {
            return null;
        }

        $lines = [];
        $nextNode = $sectionHeading->nextSibling;

        while ($nextNode instanceof DOMNode) {
            if ($nextNode instanceof DOMElement && $nextNode->tagName === 'h2') {
                break;
            }

            $text = $this->normalizedText($nextNode->textContent);

            if ($text !== null) {
                $lines[] = $text;
            }

            $nextNode = $nextNode->nextSibling;
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n\n", $lines);
    }

    private function sectionExternalUrl(DOMXPath $xpath, string $heading): ?string
    {
        $sectionHeading = $this->sectionHeading($xpath, $heading);

        if (! $sectionHeading instanceof DOMNode) {
            return null;
        }

        $nextNode = $sectionHeading->nextSibling;

        while ($nextNode instanceof DOMNode) {
            if ($nextNode instanceof DOMElement && $nextNode->tagName === 'h2') {
                break;
            }

            if ($nextNode instanceof DOMElement) {
                $links = $nextNode->getElementsByTagName('a');

                foreach ($links as $link) {
                    if (! $link instanceof DOMElement) {
                        continue;
                    }

                    $href = trim($link->getAttribute('href'));
                    $absoluteUrl = $this->absoluteUrl($href, self::LISTING_URL) ?? $href;
                    $host = parse_url($absoluteUrl, PHP_URL_HOST);

                    if (! is_string($host) || $host === '' || str_contains($host, 'python.org')) {
                        continue;
                    }

                    if (filter_var($absoluteUrl, FILTER_VALIDATE_URL)) {
                        return $absoluteUrl;
                    }
                }
            }

            $nextNode = $nextNode->nextSibling;
        }

        return null;
    }

    private function sectionHeading(DOMXPath $xpath, string $heading): ?DOMNode
    {
        return $xpath->query(sprintf('//h2[normalize-space()="%s"]', $heading))?->item(0);
    }

    private function normalizedText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = Str::of($value)->squish()->trim()->value();

        return $text === '' ? null : $text;
    }

}
