<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use App\Models\UserProfile;
use App\Services\JobLeadImportService;
use RuntimeException;
use Throwable;

class JobLeadDiscoveryRunner
{
    public function __construct(
        private readonly PythonJobBoardDiscoverySource $pythonJobBoardDiscoverySource,
        private readonly DjangoCommunityJobsDiscoverySource $djangoCommunityJobsDiscoverySource,
        private readonly WeWorkRemotelyDiscoverySource $weWorkRemotelyDiscoverySource,
        private readonly RemotiveDiscoverySource $remotiveDiscoverySource,
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
     *     query_used: bool
     * }
     */
    public function discoverForUser(int $userId, string $source, ?string $searchQuery = null): array
    {
        $discoverySource = $this->resolveSource($source);
        $normalizedSearchQuery = $this->normalizedSearchQuery($searchQuery);

        $listing = $discoverySource->discoverEntriesWithDiagnostics();
        $entries = $listing['entries'];
        $fetchedCount = $listing['candidate_links'];
        $createdCount = 0;
        $duplicateCount = 0;
        $skippedNotMatchingQueryCount = 0;
        $invalidCount = $listing['invalid_links'];
        $failedCount = 0;

        foreach ($entries as $entry) {
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

            if (
                $normalizedSearchQuery !== null
                && ! $this->jobDiscoveryQueryMatcher->matches($normalizedSearchQuery, $discoveredJob)
            ) {
                $skippedNotMatchingQueryCount++;

                continue;
            }

            $result = $this->jobLeadImportService->importForUser($userId, $discoveredJob['source_url'], [
                'source_name' => $discoverySource->sourceName(),
                'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
                'company_name' => $discoveredJob['company_name'],
                'job_title' => $discoveredJob['job_title'],
                'location' => $discoveredJob['location'],
                'work_mode' => $discoveredJob['work_mode'] ?? null,
                'description_text' => $discoveredJob['description_text'],
                'default_job_title' => 'Imported job lead',
            ]);

            if ($result['status'] === JobLeadImportService::STATUS_CREATED) {
                $createdCount++;

                continue;
            }

            if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE) {
                $duplicateCount++;

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
        ];
    }

    public function recordDiscoveryRun(int $userId, int $createdCount): void
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
     * @return array<string, JobDiscoverySource>
     */
    private function sources(): array
    {
        return [
            $this->pythonJobBoardDiscoverySource->sourceKey() => $this->pythonJobBoardDiscoverySource,
            $this->djangoCommunityJobsDiscoverySource->sourceKey() => $this->djangoCommunityJobsDiscoverySource,
            $this->weWorkRemotelyDiscoverySource->sourceKey() => $this->weWorkRemotelyDiscoverySource,
            $this->remotiveDiscoverySource->sourceKey() => $this->remotiveDiscoverySource,
            $this->companyCareerPagesDiscoverySource->sourceKey() => $this->companyCareerPagesDiscoverySource,
        ];
    }
}
