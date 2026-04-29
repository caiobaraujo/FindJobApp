<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use App\Services\ResumeDiscoveryQueryProfileResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class DiscoverJobLeads extends Command
{
    protected $signature = 'job-leads:discover {user_id} {source} {--query=}';

    protected $description = 'Fetch public jobs from a supported source and create JobLeads for a user.';

    public function handle(
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        ResumeDiscoveryQueryProfileResolver $resumeDiscoveryQueryProfileResolver,
    ): int {
        $user = User::query()->find($this->argument('user_id'));

        if ($user === null) {
            $this->error('User not found.');

            return SymfonyCommand::FAILURE;
        }

        $source = (string) $this->argument('source');
        $query = $this->option('query');
        $userProfile = UserProfile::query()
            ->where('user_id', $user->id)
            ->first();
        $queryProfiles = $resumeDiscoveryQueryProfileResolver->resolve(
            is_string($query) ? $query : null,
            $userProfile?->base_resume_text,
            $userProfile?->core_skills ?? [],
        );
        $discoveryBatchId = (string) Str::uuid();

        try {
            $summary = $jobLeadDiscoveryRunner->discoverForUser(
                $user->id,
                $source,
                is_string($query) ? $query : null,
                $discoveryBatchId,
                $queryProfiles,
            );
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $jobLeadDiscoveryRunner->recordDiscoveryRun($user->id, $summary['created'], $discoveryBatchId);

        if ($this->getOutput()->isVerbose()) {
            $this->line(sprintf('Listing HTTP status: %d', $summary['listing_status_code']));
            $this->line(sprintf('Candidate links found: %d', $summary['candidate_links']));
            $this->line(sprintf('Parsed jobs after filtering: %d', $summary['parsed_jobs']));

            foreach (($summary['target_diagnostics'] ?? []) as $targetSummary) {
                if (! is_array($targetSummary)) {
                    continue;
                }

                $targetName = is_string($targetSummary['target_name'] ?? null)
                    ? trim((string) $targetSummary['target_name'])
                    : 'unknown';
                $platform = is_string($targetSummary['platform'] ?? null)
                    ? trim((string) $targetSummary['platform'])
                    : $targetName;

                $this->line(sprintf(
                    'Target %s (%s): fetched %d · matched %d · imported %d · duplicates %d · query-skipped %d · expired %d · missing company %d · failed %d',
                    $targetName,
                    $platform,
                    (int) ($targetSummary['fetched_candidates'] ?? 0),
                    (int) ($targetSummary['matched_candidates'] ?? 0),
                    (int) ($targetSummary['imported'] ?? 0),
                    (int) ($targetSummary['deduplicated'] ?? 0),
                    (int) ($targetSummary['skipped_by_query'] ?? 0),
                    (int) ($targetSummary['skipped_expired'] ?? 0),
                    (int) ($targetSummary['skipped_missing_company'] ?? 0),
                    (int) ($targetSummary['failed'] ?? 0),
                ).$this->detailEnrichmentSuffix($targetSummary));
            }
        }

        if ($summary['parsed_jobs'] === 0) {
            $this->warn('No valid jobs were parsed from the listing page.');
        }

        $this->line(sprintf('Fetched: %d', $summary['fetched']));
        $this->line(sprintf('Created: %d', $summary['created']));
        $this->line(sprintf('Duplicates skipped: %d', $summary['duplicates']));
        if ($summary['query_used']) {
            $this->line(sprintf('Skipped not matching query: %d', $summary['skipped_not_matching_query']));
        }
        $this->line(sprintf('Invalid skipped: %d', $summary['invalid']));
        $this->line(sprintf('Failed: %d', $summary['failed']));

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @param array<string, mixed> $targetSummary
     */
    private function detailEnrichmentSuffix(array $targetSummary): string
    {
        $detailSucceeded = (int) ($targetSummary['detail_enrichment_succeeded'] ?? 0);
        $detailFailed = (int) ($targetSummary['detail_enrichment_failed'] ?? 0);

        if ($detailSucceeded === 0 && $detailFailed === 0) {
            return '';
        }

        return sprintf(' · detail ok %d · detail failed %d', $detailSucceeded, $detailFailed);
    }
}
