<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

abstract class AbstractRssJobDiscoverySource implements JobDiscoverySource
{
    /**
     * @return list<string>
     */
    abstract protected function softwareSignals(): array;

    abstract protected function feedUrl(): string;

    /**
     * @return array{
     *     detail_url: string,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     work_mode: string|null,
     *     description_text: string|null
     * }|null
     */
    abstract protected function entryFromItem(SimpleXMLElement $item): ?array;

    public function discoverEntriesWithDiagnostics(): array
    {
        $response = Http::timeout(10)
            ->accept('application/rss+xml, application/xml, text/xml')
            ->withUserAgent('FindJobApp/1.0')
            ->get($this->feedUrl());

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Failed to fetch the %s feed (HTTP %d).',
                $this->sourceName(),
                $response->status(),
            ));
        }

        $parsed = $this->parseFeedXmlWithDiagnostics($response->body());

        return [
            'status_code' => $response->status(),
            'candidate_links' => $parsed['candidate_links'],
            'parsed_jobs' => count($parsed['entries']),
            'invalid_links' => $parsed['invalid_links'],
            'entries' => $parsed['entries'],
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
    public function parseFeedXmlWithDiagnostics(string $xml): array
    {
        $feed = $this->feedFromXml($xml);

        if ($feed === null || ! isset($feed->channel->item)) {
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

        foreach ($feed->channel->item as $item) {
            $candidateCount++;
            $entry = $this->entryFromItem($item);

            if ($entry === null) {
                $invalidCount++;

                continue;
            }

            if (! filter_var($entry['detail_url'], FILTER_VALIDATE_URL)) {
                $invalidCount++;

                continue;
            }

            if (in_array($entry['detail_url'], $seenDetailUrls, true)) {
                continue;
            }

            $seenDetailUrls[] = $entry['detail_url'];
            $entries[] = $entry;
        }

        return [
            'candidate_links' => $candidateCount,
            'invalid_links' => $invalidCount,
            'entries' => $entries,
        ];
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

        $normalized = mb_strtolower($normalized);

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

    private function feedFromXml(string $xml): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();

        return $feed instanceof SimpleXMLElement ? $feed : null;
    }
}
