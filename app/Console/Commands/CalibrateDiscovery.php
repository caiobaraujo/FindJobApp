<?php

namespace App\Console\Commands;

use App\Models\JobLead;
use App\Models\User;
use App\Services\JobDiscovery\JobDiscoveryQueryMatcher;
use App\Services\JobDiscovery\JobDiscoverySource;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use App\Services\JobLeadKeywordExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class CalibrateDiscovery extends Command
{
    private const DIAGNOSTIC_EMAIL = 'discovery-diagnostics@example.com';

    protected $signature = 'discovery:calibrate {query}';

    protected $description = 'Dry-run discovery for one query and explain what each source returns or rejects.';

    public function handle(
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        JobDiscoveryQueryMatcher $jobDiscoveryQueryMatcher,
        JobLeadKeywordExtractor $jobLeadKeywordExtractor,
    ): int {
        $query = $this->normalizedQuery((string) $this->argument('query'));

        if ($query === null) {
            $this->error('A non-empty query is required.');

            return SymfonyCommand::FAILURE;
        }

        $diagnosticUser = User::query()
            ->where('email', self::DIAGNOSTIC_EMAIL)
            ->first();

        $sourceReports = [];
        $globalSummary = [
            'fetched' => 0,
            'parsed' => 0,
            'matched' => 0,
            'valid_urls' => 0,
            'duplicates' => 0,
            'would_create' => 0,
            'failed_sources' => 0,
        ];

        foreach ($jobLeadDiscoveryRunner->supportedSources() as $sourceKey) {
            $sourceReport = $this->calibrateSource(
                $jobLeadDiscoveryRunner->source($sourceKey),
                $jobDiscoveryQueryMatcher,
                $jobLeadKeywordExtractor,
                $diagnosticUser?->id,
                $query,
            );

            $sourceReports[] = $sourceReport;
            $globalSummary['fetched'] += $sourceReport['fetched'];
            $globalSummary['parsed'] += $sourceReport['parsed'];
            $globalSummary['matched'] += $sourceReport['matched'];
            $globalSummary['valid_urls'] += $sourceReport['valid_urls'];
            $globalSummary['duplicates'] += $sourceReport['duplicates'];
            $globalSummary['would_create'] += $sourceReport['would_create'];
            $globalSummary['failed_sources'] += $sourceReport['failed'] ? 1 : 0;
        }

        $reportPath = $this->reportPath($query);
        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, $this->markdownReport($query, $sourceReports, $globalSummary));

        $this->renderConsoleReport($query, $sourceReports, $globalSummary, $reportPath);

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @return array{
     *     source: string,
     *     source_name: string,
     *     fetched: int,
     *     parsed: int,
     *     matched: int,
     *     valid_urls: int,
     *     duplicates: int,
     *     would_create: int,
     *     failed: bool,
     *     error: string|null,
     *     rejections: array<string, int>,
     *     matched_examples: list<string>,
     *     rejected_examples: list<string>,
     *     candidates: list<array<string, mixed>>
     * }
     */
    private function calibrateSource(
        JobDiscoverySource $source,
        JobDiscoveryQueryMatcher $jobDiscoveryQueryMatcher,
        JobLeadKeywordExtractor $jobLeadKeywordExtractor,
        ?int $diagnosticUserId,
        string $query,
    ): array {
        try {
            $listing = $source->discoverEntriesWithDiagnostics();
        } catch (Throwable $throwable) {
            return [
                'source' => $source->sourceKey(),
                'source_name' => $source->sourceName(),
                'fetched' => 0,
                'parsed' => 0,
                'matched' => 0,
                'valid_urls' => 0,
                'duplicates' => 0,
                'would_create' => 0,
                'failed' => true,
                'error' => $throwable->getMessage(),
                'rejections' => [],
                'matched_examples' => [],
                'rejected_examples' => [],
                'candidates' => [],
            ];
        }

        $report = [
            'source' => $source->sourceKey(),
            'source_name' => $source->sourceName(),
            'fetched' => (int) ($listing['candidate_links'] ?? 0),
            'parsed' => (int) ($listing['parsed_jobs'] ?? 0),
            'matched' => 0,
            'valid_urls' => 0,
            'duplicates' => 0,
            'would_create' => 0,
            'failed' => false,
            'error' => null,
            'rejections' => [
                'query' => 0,
                'invalid_url' => 0,
                'duplicate' => 0,
                'enrich_failed' => 0,
            ],
            'matched_examples' => [],
            'rejected_examples' => [],
            'candidates' => [],
        ];

        foreach ($listing['entries'] ?? [] as $entry) {
            try {
                $job = $source->enrichEntry($entry);
            } catch (Throwable $throwable) {
                $report['rejections']['enrich_failed']++;
                $this->pushRejectedExample(
                    $report['rejected_examples'],
                    sprintf('%s → reason: failed to enrich (%s)', $this->candidateLabel($entry), $throwable->getMessage()),
                );

                continue;
            }

            $keywords = $job['extracted_keywords'] ?? null;

            if (! is_array($keywords) || $keywords === []) {
                $keywords = $jobLeadKeywordExtractor->extractKeywords($job['description_text'] ?? null);
            }

            $queryMatch = $jobDiscoveryQueryMatcher->explain($query, $job);
            $urlInspection = $this->inspectUrl($job['source_url'] ?? null);
            $locationClassification = $this->locationClassification($job, $source);
            $duplicate = $urlInspection['normalized_source_url'] !== null
                && $diagnosticUserId !== null
                && JobLead::query()
                    ->where('user_id', $diagnosticUserId)
                    ->where('normalized_source_url', $urlInspection['normalized_source_url'])
                    ->exists();

            $wouldCreate = $queryMatch['matches'] && $urlInspection['valid'] && ! $duplicate;

            if ($queryMatch['matches']) {
                $report['matched']++;
                $this->pushMatchedExample($report['matched_examples'], $this->candidateTitle($job));
            } else {
                $report['rejections']['query']++;
                $this->pushRejectedExample(
                    $report['rejected_examples'],
                    sprintf(
                        '%s → reason: %s',
                        $this->candidateTitle($job),
                        implode('; ', $queryMatch['reasons']),
                    ),
                );
            }

            if ($urlInspection['valid']) {
                $report['valid_urls']++;
            } else {
                $report['rejections']['invalid_url']++;
                $this->pushRejectedExample(
                    $report['rejected_examples'],
                    sprintf('%s → reason: invalid source URL', $this->candidateTitle($job)),
                );
            }

            if ($duplicate) {
                $report['duplicates']++;
                $report['rejections']['duplicate']++;
                $this->pushRejectedExample(
                    $report['rejected_examples'],
                    sprintf('%s → reason: duplicate for diagnostic user', $this->candidateTitle($job)),
                );
            }

            if ($wouldCreate) {
                $report['would_create']++;
            }

            $report['candidates'][] = [
                'title' => $this->candidateTitle($job),
                'company' => $this->nullableString($job['company_name'] ?? null),
                'location' => $this->nullableString($job['location'] ?? null),
                'source_url' => $this->nullableString($job['source_url'] ?? null),
                'work_mode' => $this->nullableString($job['work_mode'] ?? null),
                'keywords' => array_values(array_filter($keywords, fn (mixed $keyword): bool => is_string($keyword))),
                'location_classification' => $locationClassification,
                'matches_query' => $queryMatch['matches'],
                'why_not_match' => $queryMatch['matches'] ? null : implode('; ', $queryMatch['reasons']),
                'url_valid' => $urlInspection['valid'],
                'duplicate' => $duplicate,
                'would_create' => $wouldCreate,
            ];
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function locationClassification(array $job, JobDiscoverySource $source): string
    {
        $jobLead = new JobLead([
            'source_name' => $source->sourceName(),
            'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
            'source_url' => $job['source_url'] ?? null,
            'location' => $job['location'] ?? null,
            'source_context_text' => $job['description_text'] ?? null,
            'description_excerpt' => $job['description_text'] ?? null,
        ]);

        return $jobLead->locationClassification();
    }

    /**
     * @return array{valid: bool, normalized_source_url: string|null}
     */
    private function inspectUrl(?string $sourceUrl): array
    {
        $sourceUrl = $this->nullableString($sourceUrl);

        if ($sourceUrl === null || ! filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'normalized_source_url' => null,
            ];
        }

        $scheme = strtolower((string) parse_url($sourceUrl, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return [
                'valid' => false,
                'normalized_source_url' => null,
            ];
        }

        $parts = parse_url($sourceUrl);

        if (! is_array($parts)) {
            return [
                'valid' => false,
                'normalized_source_url' => null,
            ];
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return [
                'valid' => false,
                'normalized_source_url' => null,
            ];
        }

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/+#', '/', $path);
        $path = is_string($path) ? $path : '/';

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return [
            'valid' => true,
            'normalized_source_url' => sprintf('%s://%s%s%s', $scheme, $host, $port, $path),
        ];
    }

    private function renderConsoleReport(string $query, array $sourceReports, array $globalSummary, string $reportPath): void
    {
        $this->line(sprintf('Discovery calibration for "%s"', $query));
        $this->newLine();

        foreach ($sourceReports as $sourceReport) {
            $this->line(sprintf('Source: %s', $sourceReport['source']));
            $this->line(str_repeat('-', 39));

            if ($sourceReport['failed']) {
                $this->line(sprintf('Failed: %s', $sourceReport['error']));
                $this->newLine();

                continue;
            }

            $this->line(sprintf('Fetched: %d', $sourceReport['fetched']));
            $this->line(sprintf('Parsed: %d', $sourceReport['parsed']));
            $this->line(sprintf('Matched query: %d', $sourceReport['matched']));
            $this->line(sprintf('Valid URLs: %d', $sourceReport['valid_urls']));
            $this->line(sprintf('Duplicates: %d', $sourceReport['duplicates']));
            $this->line(sprintf('Would create: %d', $sourceReport['would_create']));
            $this->newLine();
            $this->line('Rejection breakdown:');
            $this->line(sprintf('- %d rejected by query', $sourceReport['rejections']['query']));
            $this->line(sprintf('- %d invalid URL', $sourceReport['rejections']['invalid_url']));
            $this->line(sprintf('- %d duplicate', $sourceReport['rejections']['duplicate']));

            if ($sourceReport['rejections']['enrich_failed'] > 0) {
                $this->line(sprintf('- %d failed to enrich', $sourceReport['rejections']['enrich_failed']));
            }

            $this->newLine();
            $this->line('Examples (first 5 matched):');

            foreach ($sourceReport['matched_examples'] as $example) {
                $this->line(sprintf('- %s', $example));
            }

            if ($sourceReport['matched_examples'] === []) {
                $this->line('- none');
            }

            $this->newLine();
            $this->line('Examples rejected:');

            foreach ($sourceReport['rejected_examples'] as $example) {
                $this->line(sprintf('- %s', $example));
            }

            if ($sourceReport['rejected_examples'] === []) {
                $this->line('- none');
            }

            $this->newLine();
        }

        $this->line('GLOBAL SUMMARY');
        $this->line(sprintf('- total fetched: %d', $globalSummary['fetched']));
        $this->line(sprintf('- total parsed: %d', $globalSummary['parsed']));
        $this->line(sprintf('- total matched: %d', $globalSummary['matched']));
        $this->line(sprintf('- total valid URLs: %d', $globalSummary['valid_urls']));
        $this->line(sprintf('- total duplicates: %d', $globalSummary['duplicates']));
        $this->line(sprintf('- total would create: %d', $globalSummary['would_create']));
        $this->line(sprintf('- failed sources: %d', $globalSummary['failed_sources']));
        $this->newLine();
        $this->line(sprintf('Report saved to %s', $reportPath));
    }

    private function markdownReport(string $query, array $sourceReports, array $globalSummary): string
    {
        $lines = [
            '# Discovery Calibration',
            '',
            sprintf('- Generated at: %s', now()->toIso8601String()),
            sprintf('- Query: `%s`', $query),
            '',
            '## Global Summary',
            '',
            '| Metric | Value |',
            '| --- | ---: |',
            sprintf('| Total fetched | %d |', $globalSummary['fetched']),
            sprintf('| Total parsed | %d |', $globalSummary['parsed']),
            sprintf('| Total matched | %d |', $globalSummary['matched']),
            sprintf('| Total valid URLs | %d |', $globalSummary['valid_urls']),
            sprintf('| Total duplicates | %d |', $globalSummary['duplicates']),
            sprintf('| Total would create | %d |', $globalSummary['would_create']),
            sprintf('| Failed sources | %d |', $globalSummary['failed_sources']),
            '',
        ];

        foreach ($sourceReports as $sourceReport) {
            $lines[] = sprintf('## Source: %s', $sourceReport['source']);
            $lines[] = '';

            if ($sourceReport['failed']) {
                $lines[] = sprintf('Failed: %s', $sourceReport['error']);
                $lines[] = '';

                continue;
            }

            $lines[] = '| Metric | Value |';
            $lines[] = '| --- | ---: |';
            $lines[] = sprintf('| Fetched | %d |', $sourceReport['fetched']);
            $lines[] = sprintf('| Parsed | %d |', $sourceReport['parsed']);
            $lines[] = sprintf('| Matched query | %d |', $sourceReport['matched']);
            $lines[] = sprintf('| Valid URLs | %d |', $sourceReport['valid_urls']);
            $lines[] = sprintf('| Duplicates | %d |', $sourceReport['duplicates']);
            $lines[] = sprintf('| Would create | %d |', $sourceReport['would_create']);
            $lines[] = '';
            $lines[] = '### Rejection Breakdown';
            $lines[] = '';
            $lines[] = sprintf('- %d rejected by query', $sourceReport['rejections']['query']);
            $lines[] = sprintf('- %d invalid URL', $sourceReport['rejections']['invalid_url']);
            $lines[] = sprintf('- %d duplicate', $sourceReport['rejections']['duplicate']);

            if ($sourceReport['rejections']['enrich_failed'] > 0) {
                $lines[] = sprintf('- %d failed to enrich', $sourceReport['rejections']['enrich_failed']);
            }

            $lines[] = '';
            $lines[] = '### Examples (first 5 matched)';
            $lines[] = '';

            foreach ($sourceReport['matched_examples'] as $example) {
                $lines[] = sprintf('- %s', $example);
            }

            if ($sourceReport['matched_examples'] === []) {
                $lines[] = '- none';
            }

            $lines[] = '';
            $lines[] = '### Examples rejected';
            $lines[] = '';

            foreach ($sourceReport['rejected_examples'] as $example) {
                $lines[] = sprintf('- %s', $example);
            }

            if ($sourceReport['rejected_examples'] === []) {
                $lines[] = '- none';
            }

            $lines[] = '';
            $lines[] = '### Candidate Details';
            $lines[] = '';
            $lines[] = '| Title | Company | Location | Keywords | Classification | Matches | URL valid | Duplicate | Would create | Reason |';
            $lines[] = '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |';

            foreach ($sourceReport['candidates'] as $candidate) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
                    $this->markdownCell($candidate['title']),
                    $this->markdownCell($candidate['company'] ?? null),
                    $this->markdownCell($candidate['location'] ?? null),
                    $this->markdownCell(implode(', ', $candidate['keywords'] ?? [])),
                    $this->markdownCell($candidate['location_classification']),
                    $candidate['matches_query'] ? 'yes' : 'no',
                    $candidate['url_valid'] ? 'yes' : 'no',
                    $candidate['duplicate'] ? 'yes' : 'no',
                    $candidate['would_create'] ? 'yes' : 'no',
                    $this->markdownCell($candidate['why_not_match'] ?? null),
                );
            }

            if ($sourceReport['candidates'] === []) {
                $lines[] = '| none |  |  |  |  |  |  |  |  |  |';
            }

            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    private function reportPath(string $query): string
    {
        $slug = Str::slug($query);

        if ($slug === '') {
            $slug = 'query';
        }

        return storage_path(sprintf('app/discovery-diagnostics/calibration-%s.md', $slug));
    }

    /**
     * @param list<string> $examples
     */
    private function pushMatchedExample(array &$examples, string $example): void
    {
        if (count($examples) >= 5 || in_array($example, $examples, true)) {
            return;
        }

        $examples[] = $example;
    }

    /**
     * @param list<string> $examples
     */
    private function pushRejectedExample(array &$examples, string $example): void
    {
        if (count($examples) >= 5 || in_array($example, $examples, true)) {
            return;
        }

        $examples[] = $example;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function candidateTitle(array $job): string
    {
        return $this->nullableString($job['job_title'] ?? null)
            ?? $this->nullableString($job['company_name'] ?? null)
            ?? $this->nullableString($job['source_url'] ?? null)
            ?? 'Untitled candidate';
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function candidateLabel(array $entry): string
    {
        return $this->nullableString($entry['job_title'] ?? null)
            ?? $this->nullableString($entry['detail_url'] ?? null)
            ?? 'Unknown candidate';
    }

    private function normalizedQuery(string $query): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? trim($query);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function markdownCell(mixed $value): string
    {
        $string = is_string($value) ? $value : '';
        $string = $string === '' ? '—' : $string;

        return str_replace('|', '\|', $string);
    }
}
