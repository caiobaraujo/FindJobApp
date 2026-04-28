<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
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
    ): int {
        $user = User::query()->find($this->argument('user_id'));

        if ($user === null) {
            $this->error('User not found.');

            return SymfonyCommand::FAILURE;
        }

        $source = (string) $this->argument('source');
        $query = $this->option('query');
        $discoveryBatchId = (string) Str::uuid();

        try {
            $summary = $jobLeadDiscoveryRunner->discoverForUser($user->id, $source, is_string($query) ? $query : null, $discoveryBatchId);
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $jobLeadDiscoveryRunner->recordDiscoveryRun($user->id, $summary['created'], $discoveryBatchId);

        if ($this->getOutput()->isVerbose()) {
            $this->line(sprintf('Listing HTTP status: %d', $summary['listing_status_code']));
            $this->line(sprintf('Candidate links found: %d', $summary['candidate_links']));
            $this->line(sprintf('Parsed jobs after filtering: %d', $summary['parsed_jobs']));
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
}
