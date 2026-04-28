<?php

namespace App\Services\JobDiscovery;

use DOMElement;
use Illuminate\Support\Str;

class LaraJobsDiscoverySource extends AbstractHtmlJobDiscoverySource
{
    public const SOURCE_KEY = 'larajobs';

    private const LISTING_URL = 'https://larajobs.com/';

    /**
     * @var array<string, true>
     */
    private const TITLE_START_SIGNALS = [
        'ai-native' => true,
        'architect' => true,
        'backend' => true,
        'back-end' => true,
        'data' => true,
        'designer' => true,
        'developer' => true,
        'devops' => true,
        'engineer' => true,
        'frontend' => true,
        'front-end' => true,
        'full-stack' => true,
        'fullstack' => true,
        'javascript' => true,
        'junior' => true,
        'laravel' => true,
        'lead' => true,
        'manager' => true,
        'mid-level' => true,
        'midlevel' => true,
        'php' => true,
        'principal' => true,
        'product' => true,
        'qa' => true,
        'react' => true,
        'senior' => true,
        'software' => true,
        'staff' => true,
        'typescript' => true,
        'vue' => true,
        'vue.js' => true,
        'vuejs' => true,
    ];

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'LaraJobs';
    }

    protected function listingUrl(): string
    {
        return self::LISTING_URL;
    }

    protected function fixtureResponseBody(): ?string
    {
        $fixturePath = config('job_discovery.fixture_responses.larajobs');

        if (! is_string($fixturePath) || $fixturePath === '' || ! is_file($fixturePath)) {
            return null;
        }

        $contents = file_get_contents($fixturePath);

        return is_string($contents) ? $contents : null;
    }

    protected function softwareSignals(): array
    {
        return [
            'laravel',
            'php',
            'vue',
            'vuejs',
            'javascript',
            'frontend',
            'front-end',
            'backend',
            'back-end',
            'full stack',
            'full-stack',
            'fullstack',
            'engineer',
            'developer',
            'software',
            'typescript',
            'node.js',
            'node',
        ];
    }

    public function parseListingHtmlWithDiagnostics(string $html): array
    {
        $xpath = $this->xpath($html);
        $links = $xpath->query('//a[@href]');

        if ($links === false) {
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

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = trim($link->getAttribute('href'));
            $text = $this->normalizeText($link->textContent);

            if ($href === '' || $text === null) {
                continue;
            }

            if (! $this->isPotentialCandidateLink($href, $text)) {
                continue;
            }

            $candidateCount++;
            $detailUrl = $this->absoluteUrl($href);

            if (! $this->isValidListingLink($detailUrl)) {
                $invalidCount++;

                continue;
            }

            if (in_array($detailUrl, $seenDetailUrls, true)) {
                continue;
            }

            $entry = $this->entryFromLink($text, $detailUrl);

            if ($entry === null) {
                $invalidCount++;

                continue;
            }

            $seenDetailUrls[] = $detailUrl;
            $entries[] = $entry;
        }

        return [
            'candidate_links' => $candidateCount,
            'invalid_links' => $invalidCount,
            'entries' => $entries,
        ];
    }

    private function isPotentialCandidateLink(string $href, string $text): bool
    {
        if (! $this->isJobPath($href)) {
            return false;
        }

        if (! $this->softwareTextMatches($text)) {
            return false;
        }

        return preg_match('/\b\d+\s?(?:h|d|w)\b/i', $text) === 1;
    }

    private function isValidListingLink(?string $detailUrl): bool
    {
        if (! is_string($detailUrl) || ! filter_var($detailUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($detailUrl, PHP_URL_HOST);
        $path = parse_url($detailUrl, PHP_URL_PATH);

        if (! is_string($host) || ! str_contains($host, 'larajobs.com')) {
            return false;
        }

        if (! is_string($path) || in_array($path, ['', '/'], true)) {
            return false;
        }

        if (in_array($path, ['/consultants', '/articles', '/contact'], true)) {
            return false;
        }

        return str_starts_with($path, '/jobs/');
    }

    private function isJobPath(string $href): bool
    {
        if (str_starts_with($href, '/jobs/')) {
            return true;
        }

        $path = parse_url($href, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/jobs/');
    }

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
    private function entryFromLink(string $text, string $detailUrl): ?array
    {
        $recentSummary = preg_split('/\b\d+\s?(?:h|d|w)\b/i', $text, 2)[0] ?? $text;
        $recentSummary = $this->normalizeText($recentSummary);

        if ($recentSummary === null) {
            return null;
        }

        [$companyAndTitle, $metaText] = $this->splitAtEmploymentMarker($recentSummary);
        [$companyName, $jobTitle] = $this->splitCompanyAndTitle($companyAndTitle);
        $location = $this->locationFromMeta($metaText);
        $workMode = $this->nullableWorkMode(implode(' ', array_filter([$metaText, $recentSummary])));

        return [
            'detail_url' => $detailUrl,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'location' => $location,
            'work_mode' => $workMode,
            'description_text' => $recentSummary,
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitAtEmploymentMarker(string $text): array
    {
        $markers = [' Full Time', ' Part Time', ' Part-time', ' Contract', ' Contractor'];

        foreach ($markers as $marker) {
            $position = stripos($text, $marker);

            if ($position === false) {
                continue;
            }

            $before = trim(substr($text, 0, $position));
            $after = trim(substr($text, $position + strlen($marker)));

            return [$before, $this->nullableString($after)];
        }

        return [$text, null];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitCompanyAndTitle(string $companyAndTitle): array
    {
        $tokens = preg_split('/\s+/', $companyAndTitle) ?: [];

        foreach ($tokens as $index => $token) {
            $normalizedToken = Str::of($token)->lower()->ascii()->trim(",()[] \t\n\r\0\x0B.-")->value();

            if ($normalizedToken === '' || ! isset(self::TITLE_START_SIGNALS[$normalizedToken])) {
                continue;
            }

            $companyName = $this->nullableString(implode(' ', array_slice($tokens, 0, $index)));
            $jobTitle = $this->nullableString(implode(' ', array_slice($tokens, $index)));

            return [$companyName, $jobTitle];
        }

        return [null, $this->nullableString($companyAndTitle)];
    }

    private function locationFromMeta(?string $metaText): ?string
    {
        if ($metaText === null) {
            return null;
        }

        $withoutSalary = preg_replace('/^[-–—]\s*/u', '', $metaText) ?? $metaText;
        $withoutSalary = preg_replace('/^[A-Z$€£0-9,.\s\/+-]+(?=\s+[A-Za-z])/', '', $withoutSalary) ?? $withoutSalary;

        return $this->nullableString($withoutSalary);
    }
}
