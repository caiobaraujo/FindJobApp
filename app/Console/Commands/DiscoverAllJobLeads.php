<?php

namespace App\Console\Commands;

use App\Models\UserProfile;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class DiscoverAllJobLeads extends Command
{
    protected $signature = 'job-leads:discover-all';

    protected $description = 'Run job discovery for every user with a resume profile.';

    public function handle(JobLeadDiscoveryRunner $jobLeadDiscoveryRunner): int
    {
        $userIds = UserProfile::query()
            ->where('auto_discover_jobs', true)
            ->select('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');
        $sources = $jobLeadDiscoveryRunner->supportedSources();

        foreach ($userIds as $userId) {
            $this->line(sprintf('Processing user %d', $userId));
            $createdCount = 0;

            foreach ($sources as $source) {
                try {
                    $summary = $jobLeadDiscoveryRunner->discoverForUser((int) $userId, $source);
                } catch (Throwable $throwable) {
                    $this->error(sprintf('User %d source %s failed: %s', $userId, $source, $throwable->getMessage()));

                    continue;
                }

                $this->line(sprintf(
                    'User %d source %s summary: fetched=%d created=%d duplicates=%d invalid=%d failed=%d',
                    $userId,
                    $source,
                    $summary['fetched'],
                    $summary['created'],
                    $summary['duplicates'],
                    $summary['invalid'],
                    $summary['failed'],
                ));

                $createdCount += $summary['created'];
            }

            $jobLeadDiscoveryRunner->recordDiscoveryRun((int) $userId, $createdCount);
        }

        return SymfonyCommand::SUCCESS;
    }
}
