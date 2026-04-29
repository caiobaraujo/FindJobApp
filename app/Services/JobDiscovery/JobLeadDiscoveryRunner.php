<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use App\Models\UserProfile;
use App\Services\JobLeadImportService;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class JobLeadDiscoveryRunner
{
    public function __construct(
        private readonly PythonJobBoardDiscoverySource $pythonJobBoardDiscoverySource,
        private readonly DjangoCommunityJobsDiscoverySource $djangoCommunityJobsDiscoverySource,
        private readonly WeWorkRemotelyDiscoverySource $weWorkRemotelyDiscoverySource,
        private readonly RemotiveDiscoverySource $remotiveDiscoverySource,
        private readonly LaraJobsDiscoverySource $laraJobsDiscoverySource,
        private readonly CompanyCareerPagesDiscoverySource $companyCareerPagesDiscoverySource,
        private readonly JobDiscoveryQueryMatcher $jobDiscoveryQueryMatcher,
        private readonly JobLeadImportService $jobLeadImportService,
    ) {
    }

    /**
     * @return list<string>
     */
    public function supportedSources(): array
    {
        $configuredSources = config('job_discovery.supported_sources', []);

        if (! is_array($configuredSources)) {
            return [];
        }

        return array_values(array_filter(
            $configuredSources,
            fn (mixed $source): bool => is_string($source) && isset($this->sources()[$source]),
        ));
    }

    public function source(string $source): JobDiscoverySource
    {
        return $this->resolveSource($source);
    }

    /**
     * @return array{
     *     source: string,
     *     listing_status_code: int,
     *     candidate_links: int,
     *     parsed_jobs: int,
     *     invalid_links: int,
     *     fetched: int,
     *     created: int,
     *     duplicates: int,
     *     skipped_not_matching_query: int,
     *     invalid: int,
     *     failed: int,
     *     query_used: bool,
     *     query_profile_keys: list<string>,
     *     matched_by_query_profiles: int,
     *     created_by_query_profiles: int,
     *     created_match_details: list<array{job_lead_id: int, query_profile_keys: list<string>}>,
     *     target_diagnostics?: list<array<string, int|string>>
     * }
     */
    public function discoverForUser(
        int $userId,
        string $source,
        ?string $searchQuery = null,
        ?string $discoveryBatchId = null,
        array $queryProfiles = [],
    ): array
    {
        $discoverySource = $this->resolveSource($source);
        $normalizedSearchQuery = $this->normalizedSearchQuery($searchQuery);
        $discoveryBatchId ??= (string) Str::uuid();

        $listing = $discoverySource->discoverEntriesWithDiagnostics();
        $entries = $listing['entries'];
        $fetchedCount = $listing['candidate_links'];
        $createdCount = 0;
        $duplicateCount = 0;
        $skippedNotMatchingQueryCount = 0;
        $invalidCount = $listing['invalid_links'];
        $failedCount = 0;
        $matchedByQueryProfilesCount = 0;
        $createdByQueryProfilesCount = 0;
        $queryProfileKeys = [];
        $createdMatchDetails = [];
        $targetDiagnostics = $this->initialTargetDiagnostics($listing['targets'] ?? []);

        foreach ($entries as $entry) {
            $targetIdentifier = $this->targetIdentifier($entry);

            if (! filter_var($entry['detail_url'], FILTER_VALIDATE_URL)) {
                $invalidCount++;

                continue;
            }

            try {
                $discoveredJob = $discoverySource->enrichEntry($entry);
            } catch (Throwable) {
                $failedCount++;

                continue;
            }

            $queryExplanation = $this->jobDiscoveryQueryMatcher->explainWithProfiles(
                $normalizedSearchQuery,
                $discoveredJob,
                $queryProfiles,
            );

            if (! $queryExplanation['matches']) {
                $skippedNotMatchingQueryCount++;
                $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'skipped_by_query');

                continue;
            }

            if ($queryExplanation['matched_query_profile_keys'] !== []) {
                $matchedByQueryProfilesCount++;
                $queryProfileKeys = array_values(array_unique([
                    ...$queryProfileKeys,
                    ...$queryExplanation['matched_query_profile_keys'],
                ]));
            }

            $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'matched_candidates');
            $result = $this->jobLeadImportService->importForUser($userId, $discoveredJob['source_url'], [
                'source_name' => $discoverySource->sourceName(),
                'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
                'company_name' => $discoveredJob['company_name'],
                'job_title' => $discoveredJob['job_title'],
                'location' => $discoveredJob['location'],
                'work_mode' => $discoveredJob['work_mode'] ?? null,
                'description_text' => $discoveredJob['description_text'],
                'discovery_batch_id' => $discoveryBatchId,
                'default_job_title' => 'Imported job lead',
            ]);

            if ($result['status'] === JobLeadImportService::STATUS_CREATED) {
                $createdCount++;
                $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'imported');
                $targetDiagnostics = $this->applyVisibilityMetrics(
                    $targetDiagnostics,
                    $targetIdentifier,
                    $result['job_lead'],
                );

                if ($queryExplanation['matched_query_profile_keys'] !== [] && $result['job_lead'] !== null) {
                    $createdByQueryProfilesCount++;
                    $createdMatchDetails[] = [
                        'job_lead_id' => (int) $result['job_lead']->id,
                        'query_profile_keys' => $queryExplanation['matched_query_profile_keys'],
                    ];
                }

                continue;
            }

            if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE) {
                $duplicateCount++;
                $targetDiagnostics = $this->incrementTargetMetric($targetDiagnostics, $targetIdentifier, 'deduplicated');

                continue;
            }

            $invalidCount++;
        }

        return [
            'source' => $discoverySource->sourceKey(),
            'listing_status_code' => $listing['status_code'],
            'candidate_links' => $listing['candidate_links'],
            'parsed_jobs' => $listing['parsed_jobs'],
            'invalid_links' => $listing['invalid_links'],
            'fetched' => $fetchedCount,
            'created' => $createdCount,
            'duplicates' => $duplicateCount,
            'skipped_not_matching_query' => $skippedNotMatchingQueryCount,
            'invalid' => $invalidCount,
            'failed' => $failedCount,
            'query_used' => $normalizedSearchQuery !== null,
            'query_profile_keys' => $queryProfileKeys,
            'matched_by_query_profiles' => $matchedByQueryProfilesCount,
            'created_by_query_profiles' => $createdByQueryProfilesCount,
            'created_match_details' => $createdMatchDetails,
            'discovery_batch_id' => $discoveryBatchId,
            'target_diagnostics' => $this->finalizeTargetDiagnostics($targetDiagnostics),
        ];
    }

    public function recordDiscoveryRun(int $userId, int $createdCount, ?string $discoveryBatchId): void
    {
        $userProfile = UserProfile::query()
            ->where('user_id', $userId)
            ->first();

        if ($userProfile === null) {
            return;
        }

        $userProfile->forceFill([
            'last_discovered_at' => now(),
            'last_discovered_new_count' => $createdCount,
            'last_discovery_batch_id' => $discoveryBatchId,
        ])->save();
    }

    private function resolveSource(string $source): JobDiscoverySource
    {
        $sources = $this->sources();

        if (isset($sources[$source])) {
            return $sources[$source];
        }

        throw new RuntimeException(sprintf(
            'Unsupported source [%s]. Supported sources: %s',
            $source,
            implode(', ', $this->supportedSources()),
        ));
    }

    private function normalizedSearchQuery(?string $searchQuery): ?string
    {
        if ($searchQuery === null) {
            return null;
        }

        $normalizedSearchQuery = trim(preg_replace('/\s+/', ' ', $searchQuery) ?? $searchQuery);

        if ($normalizedSearchQuery === '') {
            return null;
        }

        return $normalizedSearchQuery;
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
                'parser_strategy' => is_string($target['parser_strategy'] ?? null) && trim((string) $target['parser_strategy']) !== ''
                    ? trim((string) $target['parser_strategy'])
                    : 'structured_lists',
                'fetched_candidates' => (int) ($target['candidate_links'] ?? 0),
                'matched_candidates' => 0,
                'imported' => 0,
                'deduplicated' => 0,
                'skipped_by_query' => 0,
                'hidden_by_default' => 0,
                'international_hidden' => 0,
            ];
        }

        return $diagnostics;
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
     * @return array<string, array<string, int|string>>
     */
    private function applyVisibilityMetrics(array $targetDiagnostics, ?string $targetIdentifier, ?JobLead $jobLead): array
    {
        if ($targetIdentifier === null || $jobLead === null || ! isset($targetDiagnostics[$targetIdentifier])) {
            return $targetDiagnostics;
        }

        $isInternational = $jobLead->locationClassification() === JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL;
        $isIgnored = $jobLead->lead_status === JobLead::STATUS_IGNORED;

        if ($isInternational || $isIgnored) {
            $targetDiagnostics[$targetIdentifier]['hidden_by_default'] = (int) $targetDiagnostics[$targetIdentifier]['hidden_by_default'] + 1;
        }

        if ($isInternational) {
            $targetDiagnostics[$targetIdentifier]['international_hidden'] = (int) $targetDiagnostics[$targetIdentifier]['international_hidden'] + 1;
        }

        return $targetDiagnostics;
    }

    /**
     * @param array<string, array<string, int|string>> $targetDiagnostics
     * @return list<array<string, int|string>>
     */
    private function finalizeTargetDiagnostics(array $targetDiagnostics): array
    {
        return collect($targetDiagnostics)
            ->map(function (array $target): array {
                $fetchedCandidates = (int) $target['fetched_candidates'];

                return [
                    ...$target,
                    'query_skip_rate' => $this->percentage((int) $target['skipped_by_query'], $fetchedCandidates),
                    'import_rate' => $this->percentage((int) $target['imported'], $fetchedCandidates),
                ];
            })
            ->sortBy('target_name')
            ->values()
            ->all();
    }

    private function percentage(int $count, int $total): string
    {
        if ($total <= 0) {
            return '0.0%';
        }

        return number_format(($count / $total) * 100, 1).'%';
    }

    /**
     * @return array<string, JobDiscoverySource>
     */
    private function sources(): array
    {
        return [
            $this->pythonJobBoardDiscoverySource->sourceKey() => $this->pythonJobBoardDiscoverySource,
            $this->djangoCommunityJobsDiscoverySource->sourceKey() => $this->djangoCommunityJobsDiscoverySource,
            $this->weWorkRemotelyDiscoverySource->sourceKey() => $this->weWorkRemotelyDiscoverySource,
            $this->remotiveDiscoverySource->sourceKey() => $this->remotiveDiscoverySource,
            $this->laraJobsDiscoverySource->sourceKey() => $this->laraJobsDiscoverySource,
            $this->companyCareerPagesDiscoverySource->sourceKey() => $this->companyCareerPagesDiscoverySource,
        ];
    }
}
