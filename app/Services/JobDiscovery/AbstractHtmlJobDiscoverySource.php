<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

abstract class AbstractHtmlJobDiscoverySource implements JobDiscoverySource
{
    abstract protected function listingUrl(): string;

    /**
     * @return list<string>
     */
    abstract protected function softwareSignals(): array;

    /**
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
    abstract public function parseListingHtmlWithDiagnostics(string $html): array;

    public function discoverEntriesWithDiagnostics(): array
    {
        if (config('job_discovery.use_fixture_responses')) {
            $fixtureBody = $this->fixtureResponseBody();

            if ($fixtureBody !== null) {
                $parsed = $this->parseListingHtmlWithDiagnostics($fixtureBody);

                return [
                    'status_code' => 200,
                    'candidate_links' => $parsed['candidate_links'],
                    'parsed_jobs' => count($parsed['entries']),
                    'invalid_links' => $parsed['invalid_links'],
                    'entries' => $parsed['entries'],
                ];
            }
        }

        $response = Http::timeout(10)
            ->accept('text/html')
            ->withUserAgent('FindJobApp/1.0')
            ->get($this->listingUrl());

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Failed to fetch the %s listing page (HTTP %d).',
                $this->sourceName(),
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

    protected function fixtureResponseBody(): ?string
    {
        return null;
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

    protected function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    protected function absoluteUrl(string $href): ?string
    {
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        if (! str_starts_with($href, '/')) {
            return null;
        }

        $scheme = parse_url($this->listingUrl(), PHP_URL_SCHEME);
        $host = parse_url($this->listingUrl(), PHP_URL_HOST);

        if (! is_string($scheme) || $scheme === '' || ! is_string($host) || $host === '') {
            return null;
        }

        return "{$scheme}://{$host}{$href}";
    }

    protected function softwareTextMatches(?string $text): bool
    {
        $normalizedText = $this->normalizeText($text);

        if ($normalizedText === null) {
            return false;
        }

        $normalizedText = mb_strtolower($normalizedText);

        foreach ($this->softwareSignals() as $signal) {
            if (str_contains($normalizedText, $signal)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizedExcerpt(?string $text, int $limit = 1600): ?string
    {
        $normalized = $this->normalizeText($this->plainText($text));

        if ($normalized === null) {
            return null;
        }

        return Str::limit($normalized, $limit, '');
    }

    protected function plainText(?string $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $decoded = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->nullableString($decoded);
    }

    protected function nullableWorkMode(mixed $value): ?string
    {
        $normalized = $this->normalizeText(is_string($value) ? $value : null);

        if ($normalized === null) {
            return null;
        }

        $normalized = mb_strtolower(Str::ascii($normalized));

        if (str_contains($normalized, 'hybrid') || str_contains($normalized, 'hibrido')) {
            return JobLead::WORK_MODE_HYBRID;
        }

        if (str_contains($normalized, 'remote') || str_contains($normalized, 'remoto')) {
            return JobLead::WORK_MODE_REMOTE;
        }

        if (str_contains($normalized, 'onsite') || str_contains($normalized, 'on site') || str_contains($normalized, 'presencial')) {
            return JobLead::WORK_MODE_ONSITE;
        }

        return null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function normalizeText(?string $text): ?string
    {
        $trimmed = $this->nullableString($text);

        if ($trimmed === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return trim($normalized);
    }
}
