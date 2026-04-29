<?php

namespace App\Console\Commands;

use App\Services\JobDiscovery\JobDiscoveryQueryMatcher;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use App\Services\JobSearchIntentParser;
use App\Services\ResumeDiscoveryQueryProfileResolver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class InspectDiscoverySource extends Command
{
    protected $signature = 'discovery:inspect-source
        {source}
        {--query=}
        {--fixture}
        {--resume-text=}
        {--skill=*}';

    protected $description = 'Inspect one discovery source without importing leads so parser and query drops stay visible.';

    public function handle(
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        JobDiscoveryQueryMatcher $jobDiscoveryQueryMatcher,
        JobSearchIntentParser $jobSearchIntentParser,
        ResumeDiscoveryQueryProfileResolver $resumeDiscoveryQueryProfileResolver,
    ): int {
        if ($this->option('fixture')) {
            config([
                'job_discovery.use_fixture_responses' => true,
                'job_discovery.company_career_targets' => config('job_discovery.fixture_company_career_targets', config('job_discovery.company_career_targets', [])),
                'job_discovery.brazilian_tech_job_board_targets' => config('job_discovery.fixture_brazilian_tech_job_board_targets', config('job_discovery.brazilian_tech_job_board_targets', [])),
            ]);
        }

        $sourceKey = (string) $this->argument('source');
        $rawQuery = $this->stringOption('query');
        $searchIntent = $jobSearchIntentParser->parse($rawQuery);
        $normalizedQuery = $searchIntent['query'];
        $resumeText = $this->stringOption('resume-text');
        $skills = $this->skillOptions();
        $queryProfiles = $resumeDiscoveryQueryProfileResolver->resolve($normalizedQuery, $resumeText, $skills);

        try {
            $source = $jobLeadDiscoveryRunner->source($sourceKey);
            $listing = $source->discoverEntriesWithDiagnostics();
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $targetDiagnostics = $this->initialTargetDiagnostics($listing['targets'] ?? []);
        $matchedCount = 0;
        $querySkippedCount = 0;

        foreach (($listing['entries'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $targetIdentifier = $this->targetIdentifier($entry);

            try {
                $job = $source->enrichEntry($entry);
            } catch (Throwable) {
                $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'failed');

                continue;
            }

            $explanation = $jobDiscoveryQueryMatcher->explainWithProfiles(
                $normalizedQuery,
                $job,
                $queryProfiles,
            );

            if (! $explanation['matches']) {
                $querySkippedCount++;
                $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'skipped_by_query');

                continue;
            }

            $matchedCount++;
            $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'matched_candidates');
        }

        $this->line(sprintf('Source: %s', $source->sourceName()));
        $this->line(sprintf('Listing HTTP status: %d', (int) ($listing['status_code'] ?? 0)));
        $this->line(sprintf('Candidate links found: %d', (int) ($listing['candidate_links'] ?? 0)));
        $this->line(sprintf('Parsed jobs after filtering: %d', (int) ($listing['parsed_jobs'] ?? 0)));
        $this->line(sprintf('Matched candidates: %d', $matchedCount));
        $this->line(sprintf('Skipped by query: %d', $querySkippedCount));

        if ($normalizedQuery !== null) {
            $this->line(sprintf('Normalized query: %s', $normalizedQuery));
        }

        if ($queryProfiles !== []) {
            $this->line(sprintf(
                'Query profile keys: %s',
                implode(', ', array_values(array_filter(
                    array_map(fn (array $profile): string => (string) ($profile['key'] ?? ''), $queryProfiles),
                    fn (string $key): bool => $key !== '',
                ))),
            ));
        }

        foreach ($this->finalizeTargetDiagnostics($targetDiagnostics) as $targetSummary) {
            $this->line(sprintf(
                'Target %s (%s): fetched %d · matched %d · query-skipped %d · expired %d · missing company %d · failed %d',
                (string) $targetSummary['target_name'],
                (string) $targetSummary['platform'],
                (int) $targetSummary['fetched_candidates'],
                (int) $targetSummary['matched_candidates'],
                (int) $targetSummary['skipped_by_query'],
                (int) $targetSummary['skipped_expired'],
                (int) $targetSummary['skipped_missing_company'],
                (int) $targetSummary['failed'],
            ));
        }

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @param mixed $targets
     * @return array<string, array<string, int|string>>
     */
    private function initialTargetDiagnostics(mixed $targets): array
    {
        if (! is_array($targets)) {
            return [];
        }

        $diagnostics = [];

        foreach ($targets as $target) {
            if (! is_array($target)) {
                continue;
            }

            $targetIdentifier = is_string($target['target_identifier'] ?? null)
                ? trim((string) $target['target_identifier'])
                : '';

            if ($targetIdentifier === '') {
                continue;
            }

            $diagnostics[$targetIdentifier] = [
                'target_identifier' => $targetIdentifier,
                'target_name' => is_string($target['target_name'] ?? null) && trim((string) $target['target_name']) !== ''
                    ? trim((string) $target['target_name'])
                    : $targetIdentifier,
                'platform' => is_string($target['platform'] ?? null) && trim((string) $target['platform']) !== ''
                    ? trim((string) $target['platform'])
                    : $targetIdentifier,
                'fetched_candidates' => (int) ($target['candidate_links'] ?? 0),
                'matched_candidates' => 0,
                'skipped_by_query' => 0,
                'skipped_expired' => (int) ($target['skipped_expired'] ?? 0),
                'skipped_missing_company' => (int) ($target['skipped_missing_company'] ?? 0),
                'failed' => (int) ($target['failed'] ?? 0),
            ];
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array<string, int|string>> $targetDiagnostics
     * @return array<string, array<string, int|string>>
     */
    private function incrementTargetMetric(array $targetDiagnostics, ?string $targetIdentifier, string $metric): array
    {
        if ($targetIdentifier === null || ! isset($targetDiagnostics[$targetIdentifier])) {
            return $targetDiagnostics;
        }

        $targetDiagnostics[$targetIdentifier][$metric] = (int) $targetDiagnostics[$targetIdentifier][$metric] + 1;

        return $targetDiagnostics;
    }

    /**
     * @param array<string, array<string, int|string>> $targetDiagnostics
     * @return list<array<string, int|string>>
     */
    private function finalizeTargetDiagnostics(array $targetDiagnostics): array
    {
        return collect($targetDiagnostics)
            ->sortBy('target_name')
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function targetIdentifier(array $entry): ?string
    {
        $targetIdentifier = $entry['target_identifier'] ?? null;

        if (! is_string($targetIdentifier)) {
            return null;
        }

        $targetIdentifier = trim($targetIdentifier);

        return $targetIdentifier === '' ? null : $targetIdentifier;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function skillOptions(): array
    {
        $skills = $this->option('skill');

        if (! is_array($skills)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $skill): ?string => is_string($skill) && trim($skill) !== '' ? trim($skill) : null,
                $skills,
            ),
            fn (?string $skill): bool => $skill !== null,
        ));
    }
}
