<?php

namespace App\Services\JobDiscovery;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DjangoCommunityJobsDiscoverySource implements JobDiscoverySource
{
    public const SOURCE_KEY = 'django-community-jobs';

    private const LISTING_URL = 'https://www.djangoproject.com/community/jobs/';

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'Django Community Jobs';
    }

    /**
     * @return list<array{
     *     detail_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     description_text: string|null
     * }>
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
     *     entries: list<array{
     *         detail_url: string,
     *         job_title: string|null,
     *         company_name: string|null,
     *         location: string|null,
     *         description_text: string|null
     *     }>
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
                'Failed to fetch the Django Community Jobs listing page (HTTP %d).',
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
     * @param array{
     *     detail_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     description_text: string|null
     * } $entry
     * @return array{
     *     source_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     description_text: string|null
     * }
     */
    public function enrichEntry(array $entry): array
    {
        return [
            'source_url' => $entry['detail_url'],
            'job_title' => $entry['job_title'],
            'company_name' => $entry['company_name'],
            'location' => $entry['location'],
            'description_text' => $entry['description_text'],
        ];
    }

    /**
     * @return list<array{
     *     detail_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     description_text: string|null
     * }>
     */
    public function parseListingHtml(string $html): array
    {
        return $this->parseListingHtmlWithDiagnostics($html)['entries'];
    }

    /**
     * @return array{
     *     candidate_links: int,
     *     invalid_links: int,
     *     entries: list<array{
     *         detail_url: string,
     *         job_title: string|null,
     *         company_name: string|null,
     *         location: string|null,
     *         description_text: string|null
     *     }>
     * }
     */
    public function parseListingHtmlWithDiagnostics(string $html): array
    {
        $xpath = $this->xpath($html);
        $items = $xpath->query('//ul[contains(@class, "list-news")]/li');

        if ($items === false) {
            return [
                'candidate_links' => 0,
                'invalid_links' => 0,
                'entries' => [],
            ];
        }

        $candidateCount = 0;
        $invalidCount = 0;
        $entries = [];

        foreach ($items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $link = $xpath->query('./h2/a[@href]', $item)?->item(0);

            if (! $link instanceof DOMElement) {
                continue;
            }

            $candidateCount++;

            $detailUrl = trim($link->getAttribute('href'));

            if (! filter_var($detailUrl, FILTER_VALIDATE_URL)) {
                $invalidCount++;

                continue;
            }

            $titleText = $this->normalizedText($link->textContent);
            [$jobTitle, $companyName] = $this->splitTitleAndCompany($titleText);
            $descriptionText = $this->listingDescription($xpath, $item);

            $entries[] = [
                'detail_url' => $detailUrl,
                'job_title' => $jobTitle,
                'company_name' => $companyName,
                'location' => null,
                'description_text' => $descriptionText,
            ];
        }

        return [
            'candidate_links' => $candidateCount,
            'invalid_links' => $invalidCount,
            'entries' => $entries,
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

    private function listingDescription(DOMXPath $xpath, DOMElement $item): ?string
    {
        $descriptionNode = $xpath->query('./div[1]', $item)?->item(0);

        if (! $descriptionNode instanceof DOMNode) {
            return null;
        }

        return $this->normalizedText($descriptionNode->textContent);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitTitleAndCompany(?string $title): array
    {
        if ($title === null) {
            return [null, null];
        }

        $parts = preg_split('/\s+at\s+/i', $title);

        if (! is_array($parts) || count($parts) < 2) {
            return [$title, null];
        }

        $companyName = array_pop($parts);
        $jobTitle = implode(' at ', $parts);

        $normalizedJobTitle = $this->normalizedText($jobTitle);
        $normalizedCompanyName = $this->normalizedText($companyName);

        if ($normalizedJobTitle === null || $normalizedCompanyName === null) {
            return [$title, null];
        }

        return [$normalizedJobTitle, $normalizedCompanyName];
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
