<?php

namespace App\Services\JobDiscovery;

use App\Models\UserProfile;
use App\Services\JobLeadImportService;
use RuntimeException;
use Throwable;

class JobLeadDiscoveryRunner
{
    public function __construct(
        private readonly PythonJobBoardDiscoverySource $pythonJobBoardDiscoverySource,
        private readonly DjangoCommunityJobsDiscoverySource $djangoCommunityJobsDiscoverySource,
        private readonly JobLeadImportService $jobLeadImportService,
    ) {
    }

    /**
     * @return list<string>
     */
    public function supportedSources(): array
    {
        return [
            $this->pythonJobBoardDiscoverySource->sourceKey(),
            $this->djangoCommunityJobsDiscoverySource->sourceKey(),
        ];
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
     *     invalid: int,
     *     failed: int
     * }
     */
    public function discoverForUser(int $userId, string $source): array
    {
        $discoverySource = $this->resolveSource($source);

        $listing = $discoverySource->discoverEntriesWithDiagnostics();
        $entries = $listing['entries'];
        $fetchedCount = $listing['candidate_links'];
        $createdCount = 0;
        $duplicateCount = 0;
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

            $result = $this->jobLeadImportService->importForUser($userId, $discoveredJob['source_url'], [
                'source_name' => $discoverySource->sourceName(),
                'company_name' => $discoveredJob['company_name'],
                'job_title' => $discoveredJob['job_title'],
                'location' => $discoveredJob['location'],
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
            'invalid' => $invalidCount,
            'failed' => $failedCount,
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

    private function resolveSource(string $source): PythonJobBoardDiscoverySource|DjangoCommunityJobsDiscoverySource
    {
        if ($source === $this->pythonJobBoardDiscoverySource->sourceKey()) {
            return $this->pythonJobBoardDiscoverySource;
        }

        if ($source === $this->djangoCommunityJobsDiscoverySource->sourceKey()) {
            return $this->djangoCommunityJobsDiscoverySource;
        }

        throw new RuntimeException(sprintf(
            'Unsupported source [%s]. Supported sources: %s',
            $source,
            implode(', ', $this->supportedSources()),
        ));
    }
}
