<?php

namespace App\Console\Commands;

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class DiagnoseDiscovery extends Command
{
    private const DIAGNOSTIC_EMAIL = 'discovery-diagnostics@example.com';

    private const REPORT_RELATIVE_PATH = 'app/discovery-diagnostics/latest.md';

    /**
     * @var list<string>
     */
    private const SCENARIOS = [
        'laravel',
        'php',
        'javascript',
        'vue',
        'python',
        'django',
        'docker',
        'laravel remoto',
        'javascript belo horizonte',
        'php laravel remoto brasil',
    ];

    protected $signature = 'discovery:diagnose {user_id?} {--fresh} {--fixture}';

    protected $description = 'Run discovery scenarios and write a product-quality diagnostics report.';

    public function handle(JobLeadDiscoveryRunner $jobLeadDiscoveryRunner): int
    {
        if ($this->option('fixture')) {
            config([
                'job_discovery.use_fixture_responses' => true,
                'job_discovery.supported_sources' => config('job_discovery.fixture_supported_sources', config('job_discovery.supported_sources', [])),
                'job_discovery.company_career_targets' => config('job_discovery.fixture_company_career_targets', config('job_discovery.company_career_targets', [])),
            ]);
        }

        $user = $this->resolvedUser();

        if ($user === null) {
            $this->error('User not found.');

            return SymfonyCommand::FAILURE;
        }

        $this->resetDiagnosticUserWorkspaceIfRequested($user);

        $scenarioResults = [];
        $sourcePerformance = [];
        $companyCareerTargetPerformance = [];
        $usingSyntheticUser = $this->argument('user_id') === null;

        foreach (self::SCENARIOS as $scenario) {
            if ($usingSyntheticUser) {
                $this->resetUserWorkspace($user);
            }

            $scenarioResult = $this->runScenario($jobLeadDiscoveryRunner, $user, $scenario);
            $scenarioResults[] = $scenarioResult;
            $sourcePerformance = $this->mergeSourcePerformance($sourcePerformance, $scenarioResult['sources']);
            $companyCareerTargetPerformance = $this->mergeCompanyCareerTargetPerformance($companyCareerTargetPerformance, $scenarioResult['sources']);
        }

        $warnings = $this->warnings($scenarioResults, $sourcePerformance);
        $recommendations = $this->recommendations($scenarioResults, $sourcePerformance, $warnings);
        $report = $this->markdownReport(
            $user->id,
            $scenarioResults,
            $sourcePerformance,
            $companyCareerTargetPerformance,
            $warnings,
            $recommendations,
        );
        $reportPath = storage_path(self::REPORT_RELATIVE_PATH);

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, $report);

        $this->renderConsoleSummary($user->id, $scenarioResults, $warnings, $recommendations);
        $this->line(sprintf('Report saved to %s', $reportPath));

        return SymfonyCommand::SUCCESS;
    }

    private function resolvedUser(): ?User
    {
        $userId = $this->argument('user_id');

        if (is_string($userId) && $userId !== '') {
            return User::query()->find((int) $userId);
        }

        $user = User::query()->firstOrNew([
            'email' => self::DIAGNOSTIC_EMAIL,
        ]);

        $user->forceFill([
            'name' => 'Discovery Diagnostics',
            'email' => self::DIAGNOSTIC_EMAIL,
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'base_resume_text' => 'Laravel PHP Vue JavaScript frontend backend full stack developer based in Brazil.',
                'core_skills' => ['Laravel', 'PHP', 'Vue', 'JavaScript', 'Frontend', 'Backend', 'Full stack', 'Docker'],
                'auto_discover_jobs' => false,
            ],
        );

        return $user;
    }

    private function resetDiagnosticUserWorkspaceIfRequested(User $user): void
    {
        if (! $this->option('fresh')) {
            return;
        }

        if ($this->argument('user_id') !== null) {
            $this->warn('--fresh is ignored when a user_id is provided.');

            return;
        }

        $this->resetUserWorkspace($user);
    }

    private function resetUserWorkspace(User $user): void
    {
        JobLead::query()
            ->where('user_id', $user->id)
            ->delete();

        UserProfile::query()
            ->where('user_id', $user->id)
            ->update([
                'last_discovered_at' => null,
                'last_discovered_new_count' => null,
                'last_discovery_batch_id' => null,
            ]);
    }

    /**
     * @return array{
     *     query: string,
     *     aggregate: array<string, int>,
     *     sources: list<array<string, mixed>>,
     *     created_lead_ids: list<int>,
     *     location_counts: array<string, int>,
     *     visibility: array<string, int|bool>,
     *     analysis: array<string, int>,
     *     source_observability: list<array<string, int|string>>,
     *     warnings: list<string>,
     *     batch_id: string
     * }
     */
    private function runScenario(JobLeadDiscoveryRunner $jobLeadDiscoveryRunner, User $user, string $query): array
    {
        $discoveryBatchId = (string) Str::uuid();
        $aggregate = [
            'fetched' => 0,
            'created' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'failed' => 0,
            'skipped_not_matching_query' => 0,
        ];
        $sources = [];

        foreach ($jobLeadDiscoveryRunner->supportedSources() as $source) {
            try {
                $summary = $jobLeadDiscoveryRunner->discoverForUser($user->id, $source, $query, $discoveryBatchId);
                $summary['source_name'] = $jobLeadDiscoveryRunner->source($source)->sourceName();
            } catch (Throwable $throwable) {
                $summary = [
                    'source' => $source,
                    'source_name' => $jobLeadDiscoveryRunner->source($source)->sourceName(),
                    'fetched' => 0,
                    'created' => 0,
                    'duplicates' => 0,
                    'invalid' => 0,
                    'failed' => 1,
                    'skipped_not_matching_query' => 0,
                    'query_used' => true,
                    'discovery_batch_id' => $discoveryBatchId,
                    'error' => $throwable->getMessage(),
                ];
            }

            $aggregate['fetched'] += $summary['fetched'];
            $aggregate['created'] += $summary['created'];
            $aggregate['duplicates'] += $summary['duplicates'];
            $aggregate['invalid'] += $summary['invalid'];
            $aggregate['failed'] += $summary['failed'];
            $aggregate['skipped_not_matching_query'] += $summary['skipped_not_matching_query'];
            $sources[] = $summary;
        }

        $jobLeadDiscoveryRunner->recordDiscoveryRun($user->id, $aggregate['created'], $discoveryBatchId);

        $createdJobLeads = JobLead::query()
            ->where('user_id', $user->id)
            ->where('discovery_batch_id', $discoveryBatchId)
            ->orderBy('id')
            ->get();

        $locationCounts = [
            JobLead::LOCATION_CLASSIFICATION_BRAZIL => 0,
            JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL => 0,
            JobLead::LOCATION_CLASSIFICATION_UNKNOWN => 0,
        ];

        foreach ($createdJobLeads as $jobLead) {
            $locationCounts[$jobLead->locationClassification()]++;
        }

        $defaultWorkspaceVisibleCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status !== JobLead::STATUS_IGNORED)
            ->filter(fn (JobLead $jobLead): bool => $jobLead->locationClassification() !== JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL)
            ->count();

        $allWorkspaceVisibleCount = $createdJobLeads->count();
        $hiddenByStatusCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status === JobLead::STATUS_IGNORED)
            ->count();
        $hiddenByLocationCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->locationClassification() === JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL)
            ->count();
        $hiddenByBothCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status === JobLead::STATUS_IGNORED)
            ->filter(fn (JobLead $jobLead): bool => $jobLead->locationClassification() === JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL)
            ->count();
        $analysis = $this->analysisCounts($createdJobLeads);
        $sourceObservability = $this->sourceObservability($createdJobLeads, $sources);
        $sources = array_map(function (array $sourceSummary) use ($sourceObservability): array {
            $observability = collect($sourceObservability)
                ->firstWhere('source', (string) $sourceSummary['source']);

            if (! is_array($observability)) {
                return $sourceSummary;
            }

            return [
                ...$sourceSummary,
                'visible_by_default' => $observability['visible_by_default'],
                'hidden_by_default' => $observability['hidden_by_default'],
                'ready_analysis' => $observability['ready_analysis'],
                'limited_analysis' => $observability['limited_analysis'],
                'missing_description' => $observability['missing_description'],
                'missing_keywords' => $observability['missing_keywords'],
            ];
        }, $sources);

        return [
            'query' => $query,
            'aggregate' => $aggregate,
            'sources' => $sources,
            'created_lead_ids' => $createdJobLeads->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            'location_counts' => $locationCounts,
            'visibility' => [
                'latest_batch' => $createdJobLeads->count(),
                'default_brazil' => $defaultWorkspaceVisibleCount,
                'all_workspace' => $allWorkspaceVisibleCount,
                'hidden_total' => $allWorkspaceVisibleCount - $defaultWorkspaceVisibleCount,
                'hidden_by_status' => $hiddenByStatusCount,
                'hidden_by_location' => $hiddenByLocationCount,
                'hidden_by_both' => $hiddenByBothCount,
                'hidden_by_default' => $aggregate['created'] > 0 && $defaultWorkspaceVisibleCount < $aggregate['created'],
            ],
            'analysis' => $analysis,
            'source_observability' => $sourceObservability,
            'warnings' => $this->scenarioWarnings($query, $aggregate, $sources, $locationCounts, $defaultWorkspaceVisibleCount),
            'batch_id' => $discoveryBatchId,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $sourcePerformance
     * @param list<array<string, mixed>> $sources
     * @return array<string, array<string, mixed>>
     */
    private function mergeSourcePerformance(array $sourcePerformance, array $sources): array
    {
        foreach ($sources as $sourceSummary) {
            $sourceKey = (string) $sourceSummary['source'];

            if (! isset($sourcePerformance[$sourceKey])) {
                $sourcePerformance[$sourceKey] = [
                    'source' => $sourceKey,
                    'fetched' => 0,
                    'created' => 0,
                    'duplicates' => 0,
                    'invalid' => 0,
                    'failed' => 0,
                    'skipped_not_matching_query' => 0,
                    'visible_by_default' => 0,
                    'hidden_by_default' => 0,
                    'ready_analysis' => 0,
                    'limited_analysis' => 0,
                    'missing_description' => 0,
                    'missing_keywords' => 0,
                ];
            }

            $sourcePerformance[$sourceKey]['fetched'] += (int) ($sourceSummary['fetched'] ?? 0);
            $sourcePerformance[$sourceKey]['created'] += (int) ($sourceSummary['created'] ?? 0);
            $sourcePerformance[$sourceKey]['duplicates'] += (int) ($sourceSummary['duplicates'] ?? 0);
            $sourcePerformance[$sourceKey]['invalid'] += (int) ($sourceSummary['invalid'] ?? 0);
            $sourcePerformance[$sourceKey]['failed'] += (int) ($sourceSummary['failed'] ?? 0);
            $sourcePerformance[$sourceKey]['skipped_not_matching_query'] += (int) ($sourceSummary['skipped_not_matching_query'] ?? 0);

            foreach ([
                'visible_by_default',
                'hidden_by_default',
                'ready_analysis',
                'limited_analysis',
                'missing_description',
                'missing_keywords',
            ] as $metric) {
                $sourcePerformance[$sourceKey][$metric] += (int) ($sourceSummary[$metric] ?? 0);
            }
        }

        ksort($sourcePerformance);

        return $sourcePerformance;
    }

    /**
     * @param array<string, array<string, mixed>> $targetPerformance
     * @param list<array<string, mixed>> $sources
     * @return array<string, array<string, mixed>>
     */
    private function mergeCompanyCareerTargetPerformance(array $targetPerformance, array $sources): array
    {
        foreach ($sources as $sourceSummary) {
            if ((string) ($sourceSummary['source'] ?? '') !== 'company-career-pages') {
                continue;
            }

            foreach (($sourceSummary['target_diagnostics'] ?? []) as $targetSummary) {
                if (! is_array($targetSummary)) {
                    continue;
                }

                $targetIdentifier = is_string($targetSummary['target_identifier'] ?? null)
                    ? trim((string) $targetSummary['target_identifier'])
                    : '';

                if ($targetIdentifier === '') {
                    continue;
                }

                if (! isset($targetPerformance[$targetIdentifier])) {
                    $targetPerformance[$targetIdentifier] = [
                        'target_identifier' => $targetIdentifier,
                        'target_name' => $targetSummary['target_name'] ?? $targetIdentifier,
                        'fetched_candidates' => 0,
                        'matched_candidates' => 0,
                        'imported' => 0,
                        'deduplicated' => 0,
                        'skipped_by_query' => 0,
                        'hidden_by_default' => 0,
                        'international_hidden' => 0,
                    ];
                }

                foreach ([
                    'fetched_candidates',
                    'matched_candidates',
                    'imported',
                    'deduplicated',
                    'skipped_by_query',
                    'hidden_by_default',
                    'international_hidden',
                ] as $metric) {
                    $targetPerformance[$targetIdentifier][$metric] += (int) ($targetSummary[$metric] ?? 0);
                }
            }
        }

        uasort($targetPerformance, fn (array $left, array $right): int => strcmp((string) $left['target_name'], (string) $right['target_name']));

        return $targetPerformance;
    }

    /**
     * @param array<string, int> $aggregate
     * @param list<array<string, mixed>> $sources
     * @param array<string, int> $locationCounts
     * @return list<string>
     */
    private function scenarioWarnings(
        string $query,
        array $aggregate,
        array $sources,
        array $locationCounts,
        int $defaultWorkspaceVisibleCount,
    ): array {
        $warnings = [];

        if ($aggregate['created'] === 0) {
            $warnings[] = sprintf('Search "%s" created 0 leads.', $query);
        }

        if ($aggregate['fetched'] >= 3 && $aggregate['created'] === 0) {
            $warnings[] = sprintf('Search "%s" fetched many results but created 0 leads.', $query);
        }

        if ($aggregate['skipped_not_matching_query'] >= max(3, (int) ceil($aggregate['fetched'] / 2))) {
            $warnings[] = sprintf('Search "%s" skipped many fetched jobs because they did not match the query.', $query);
        }

        if ($aggregate['duplicates'] >= max(3, (int) ceil($aggregate['fetched'] / 2))) {
            $warnings[] = sprintf('Search "%s" hit a high duplicate rate.', $query);
        }

        if ($aggregate['created'] > 0 && $locationCounts[JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL] > $aggregate['created'] / 2) {
            $warnings[] = sprintf('Search "%s" created mostly international leads.', $query);
        }

        if ($aggregate['created'] > 0 && $defaultWorkspaceVisibleCount < $aggregate['created']) {
            $warnings[] = sprintf('Search "%s" created leads hidden from the default Brazil workspace.', $query);
        }

        if ($aggregate['created'] > 0) {
            $limitedAnalysisCount = collect($sources)
                ->sum(fn (array $sourceSummary): int => (int) ($sourceSummary['limited_analysis'] ?? 0));

            if ($limitedAnalysisCount >= max(2, (int) ceil($aggregate['created'] / 2))) {
                $warnings[] = sprintf('Search "%s" created many leads with limited analysis.', $query);
            }
        }

        foreach ($sources as $sourceSummary) {
            $source = (string) $sourceSummary['source'];
            $fetched = (int) ($sourceSummary['fetched'] ?? 0);
            $invalid = (int) ($sourceSummary['invalid'] ?? 0);
            $failed = (int) ($sourceSummary['failed'] ?? 0);

            if ($failed > 0) {
                $warnings[] = sprintf('Source %s failed during search "%s".', $source, $query);
            }

            if ($fetched > 0 && $invalid >= max(3, (int) ceil($fetched / 2))) {
                $warnings[] = sprintf('Source %s returned mostly invalid links during search "%s".', $source, $query);
            }

            if (
                (int) ($sourceSummary['created'] ?? 0) > 0
                && (int) ($sourceSummary['limited_analysis'] ?? 0) >= max(2, (int) ceil((int) $sourceSummary['created'] / 2))
            ) {
                $warnings[] = sprintf('Source %s created mostly limited-analysis leads during search "%s".', $source, $query);
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param list<array<string, mixed>> $scenarioResults
     * @param array<string, array<string, mixed>> $sourcePerformance
     * @return list<string>
     */
    private function warnings(array $scenarioResults, array $sourcePerformance): array
    {
        $warnings = [];

        foreach ($scenarioResults as $scenarioResult) {
            foreach ($scenarioResult['warnings'] as $warning) {
                $warnings[] = $warning;
            }
        }

        foreach ($sourcePerformance as $sourceSummary) {
            if ((int) $sourceSummary['failed'] > 0) {
                $warnings[] = sprintf('Source %s failed in one or more scenarios.', $sourceSummary['source']);
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param list<array<string, mixed>> $scenarioResults
     * @param array<string, array<string, mixed>> $sourcePerformance
     * @param list<string> $warnings
     * @return list<string>
     */
    private function recommendations(array $scenarioResults, array $sourcePerformance, array $warnings): array
    {
        $recommendations = [];

        foreach ($scenarioResults as $scenarioResult) {
            $query = (string) $scenarioResult['query'];
            $created = (int) $scenarioResult['aggregate']['created'];

            if ($created === 0 && (str_contains($query, 'laravel') || str_contains($query, 'php'))) {
                $recommendations[] = sprintf('%s returned 0 new leads: add or repair Laravel/PHP sources.', Str::headline($query));
            }

            if ($created <= 1 && (str_contains($query, 'javascript') || str_contains($query, 'vue'))) {
                $recommendations[] = sprintf('%s returned low results: add more frontend and JavaScript sources.', Str::headline($query));
            }

            if ((bool) $scenarioResult['visibility']['hidden_by_default']) {
                $recommendations[] = 'Created leads hidden by default: review location classification or default filter behavior.';
            }

            if ((int) $scenarioResult['analysis']['limited'] > 0) {
                $recommendations[] = 'Limited-analysis leads are reducing lead usefulness: preserve URL-only leads but prioritize deterministic detail coverage in current sources.';
            }

            if (
                (int) $scenarioResult['aggregate']['created'] > 0
                && (int) $scenarioResult['location_counts'][JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL] > (int) $scenarioResult['aggregate']['created'] / 2
            ) {
                $recommendations[] = 'Most created leads are international: add Brazil-focused sources or company career targets.';
            }
        }

        foreach ($sourcePerformance as $sourceSummary) {
            if ((int) $sourceSummary['failed'] > 0) {
                $recommendations[] = sprintf('Source %s failed: inspect the source URL or parser.', $sourceSummary['source']);
            }

            if (
                (int) $sourceSummary['created'] > 0
                && (int) $sourceSummary['limited_analysis'] >= max(2, (int) ceil((int) $sourceSummary['created'] / 2))
            ) {
                $recommendations[] = sprintf('Source %s creates many limited-analysis leads: improve deterministic detail capture before adding new sources.', $sourceSummary['source']);
            }
        }

        if ($recommendations === [] && $warnings === []) {
            $recommendations[] = 'Discovery scenarios completed without obvious parser or visibility issues.';
        }

        return array_values(array_unique($recommendations));
    }

    /**
     * @param list<array<string, mixed>> $scenarioResults
     * @param array<string, array<string, mixed>> $sourcePerformance
     * @param array<string, array<string, mixed>> $companyCareerTargetPerformance
     * @param list<string> $warnings
     * @param list<string> $recommendations
     */
    private function markdownReport(
        int $userId,
        array $scenarioResults,
        array $sourcePerformance,
        array $companyCareerTargetPerformance,
        array $warnings,
        array $recommendations,
    ): string {
        $lines = [
            '# Discovery Diagnostics',
            '',
            sprintf('- Timestamp: %s', now()->toIso8601String()),
            sprintf('- User ID: %d', $userId),
            '',
            '## Scenario Summary',
            '',
            '| Search | Fetched | Imported | Deduplicated | Invalid | Failed | Skipped | Brazil | International | Unknown | Latest batch | Default Brazil | Hidden total | Limited analysis | Ready analysis | Hidden by default |',
            '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |',
        ];

        foreach ($scenarioResults as $scenarioResult) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %s |',
                $scenarioResult['query'],
                $scenarioResult['aggregate']['fetched'],
                $scenarioResult['aggregate']['created'],
                $scenarioResult['aggregate']['duplicates'],
                $scenarioResult['aggregate']['invalid'],
                $scenarioResult['aggregate']['failed'],
                $scenarioResult['aggregate']['skipped_not_matching_query'],
                $scenarioResult['location_counts'][JobLead::LOCATION_CLASSIFICATION_BRAZIL],
                $scenarioResult['location_counts'][JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL],
                $scenarioResult['location_counts'][JobLead::LOCATION_CLASSIFICATION_UNKNOWN],
                $scenarioResult['visibility']['latest_batch'],
                $scenarioResult['visibility']['default_brazil'],
                $scenarioResult['visibility']['hidden_total'],
                $scenarioResult['analysis']['limited'],
                $scenarioResult['analysis']['ready'],
                $scenarioResult['visibility']['hidden_by_default'] ? 'yes' : 'no',
            );
        }

        $lines[] = '';
        $lines[] = '## Source Performance';
        $lines[] = '';
        $lines[] = '| Source | Fetched | Imported | Deduplicated | Invalid | Failed | Skipped | Visible by default | Hidden by default | Ready analysis | Limited analysis |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

        foreach ($sourcePerformance as $sourceSummary) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d |',
                $sourceSummary['source'],
                $sourceSummary['fetched'],
                $sourceSummary['created'],
                $sourceSummary['duplicates'],
                $sourceSummary['invalid'],
                $sourceSummary['failed'],
                $sourceSummary['skipped_not_matching_query'],
                $sourceSummary['visible_by_default'],
                $sourceSummary['hidden_by_default'],
                $sourceSummary['ready_analysis'],
                $sourceSummary['limited_analysis'],
            );
        }

        if ($companyCareerTargetPerformance !== []) {
            $lines[] = '';
            $lines[] = '## Company Career Page Target Performance';
            $lines[] = '';
            $lines[] = '| Target | Bucket | Action | Fetched | Matched | Imported | Deduplicated | Skipped | Hidden by default | International hidden | Query skip rate | Import rate |';
            $lines[] = '| --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

            foreach ($companyCareerTargetPerformance as $targetSummary) {
                $classification = $this->classifyCompanyCareerTarget($targetSummary);

                $lines[] = sprintf(
                    '| %s | %s | %s | %d | %d | %d | %d | %d | %d | %d | %s | %s |',
                    $targetSummary['target_name'],
                    $classification['bucket'],
                    $classification['action'],
                    $targetSummary['fetched_candidates'],
                    $targetSummary['matched_candidates'],
                    $targetSummary['imported'],
                    $targetSummary['deduplicated'],
                    $targetSummary['skipped_by_query'],
                    $targetSummary['hidden_by_default'],
                    $targetSummary['international_hidden'],
                    $this->percentage((int) $targetSummary['skipped_by_query'], (int) $targetSummary['fetched_candidates']),
                    $this->percentage((int) $targetSummary['imported'], (int) $targetSummary['fetched_candidates']),
                );
            }

            $lines[] = '';
            $lines[] = '## Company Career Page Target Recommendations';
            $lines[] = '';

            foreach ($this->companyCareerTargetRecommendations($companyCareerTargetPerformance) as $recommendation) {
                $lines[] = sprintf('- %s', $recommendation);
            }
        }

        $lines[] = '';
        $lines[] = '## Scenario Details';
        $lines[] = '';

        foreach ($scenarioResults as $scenarioResult) {
            $lines[] = sprintf('### %s', $scenarioResult['query']);
            $lines[] = '';
            $lines[] = sprintf('- Batch ID: `%s`', $scenarioResult['batch_id']);
            $lines[] = sprintf('- Created lead IDs: %s', $scenarioResult['created_lead_ids'] === [] ? 'none' : implode(', ', $scenarioResult['created_lead_ids']));
            $lines[] = sprintf(
                '- Visibility: latest batch %d, default Brazil %d, hidden total %d',
                $scenarioResult['visibility']['latest_batch'],
                $scenarioResult['visibility']['default_brazil'],
                $scenarioResult['visibility']['hidden_total'],
            );
            $lines[] = '';
            $lines[] = 'Batch observability:';
            $lines[] = sprintf('- Imported vs deduplicated: %d imported, %d deduplicated', $scenarioResult['aggregate']['created'], $scenarioResult['aggregate']['duplicates']);
            $lines[] = sprintf(
                '- Hidden by default filters: %d total, %d ignored, %d international, %d both',
                $scenarioResult['visibility']['hidden_total'],
                $scenarioResult['visibility']['hidden_by_status'],
                $scenarioResult['visibility']['hidden_by_location'],
                $scenarioResult['visibility']['hidden_by_both'],
            );
            $lines[] = sprintf(
                '- Analysis coverage: %d ready, %d limited, %d missing description, %d missing keywords',
                $scenarioResult['analysis']['ready'],
                $scenarioResult['analysis']['limited'],
                $scenarioResult['analysis']['missing_description'],
                $scenarioResult['analysis']['missing_keywords'],
            );
            $lines[] = '';
            $lines[] = '| Source | Imported | Deduplicated | Visible by default | Hidden by default | Ready analysis | Limited analysis |';
            $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: |';

            foreach ($scenarioResult['source_observability'] as $sourceSummary) {
                $lines[] = sprintf(
                    '| %s | %d | %d | %d | %d | %d | %d |',
                    $sourceSummary['source'],
                    $sourceSummary['imported'],
                    $sourceSummary['duplicates'],
                    $sourceSummary['visible_by_default'],
                    $sourceSummary['hidden_by_default'],
                    $sourceSummary['ready_analysis'],
                    $sourceSummary['limited_analysis'],
                );
            }

            $companyCareerTargets = $this->companyCareerTargetDiagnostics($scenarioResult['sources']);

            if ($companyCareerTargets !== []) {
                $lines[] = '';
                $lines[] = 'Company career page targets:';
                $lines[] = '';
                $lines[] = '| Target | Bucket | Fetched | Matched | Imported | Deduplicated | Skipped | Hidden by default | International hidden | Query skip rate | Import rate |';
                $lines[] = '| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

                foreach ($companyCareerTargets as $targetSummary) {
                    $classification = $this->classifyCompanyCareerTarget($targetSummary);

                    $lines[] = sprintf(
                        '| %s | %s | %d | %d | %d | %d | %d | %d | %d | %s | %s |',
                        $targetSummary['target_name'],
                        $classification['bucket'],
                        $targetSummary['fetched_candidates'],
                        $targetSummary['matched_candidates'],
                        $targetSummary['imported'],
                        $targetSummary['deduplicated'],
                        $targetSummary['skipped_by_query'],
                        $targetSummary['hidden_by_default'],
                        $targetSummary['international_hidden'],
                        $targetSummary['query_skip_rate'],
                        $targetSummary['import_rate'],
                    );
                }
            }

            if ($scenarioResult['warnings'] !== []) {
                $lines[] = '';
                $lines[] = 'Warnings:';

                foreach ($scenarioResult['warnings'] as $warning) {
                    $lines[] = sprintf('- %s', $warning);
                }
            }

            $lines[] = '';
        }

        $lines[] = '## Warnings';
        $lines[] = '';

        if ($warnings === []) {
            $lines[] = '- None.';
        } else {
            foreach ($warnings as $warning) {
                $lines[] = sprintf('- %s', $warning);
            }
        }

        $lines[] = '';
        $lines[] = '## Recommendations';
        $lines[] = '';

        foreach ($recommendations as $recommendation) {
            $lines[] = sprintf('- %s', $recommendation);
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, mixed>> $scenarioResults
     * @param list<string> $warnings
     * @param list<string> $recommendations
     */
    private function renderConsoleSummary(int $userId, array $scenarioResults, array $warnings, array $recommendations): void
    {
        $this->line(sprintf('Discovery diagnostics for user %d', $userId));

        $this->table(
            ['Search', 'Fetched', 'Imported', 'Deduplicated', 'Limited analysis', 'Hidden by default'],
            array_map(
                fn (array $scenarioResult): array => [
                    $scenarioResult['query'],
                    $scenarioResult['aggregate']['fetched'],
                    $scenarioResult['aggregate']['created'],
                    $scenarioResult['aggregate']['duplicates'],
                    $scenarioResult['analysis']['limited'],
                    $scenarioResult['visibility']['hidden_total'],
                ],
                $scenarioResults,
            ),
        );

        if ($warnings !== []) {
            $this->line('Warnings:');

            foreach ($warnings as $warning) {
                $this->line(sprintf('- %s', $warning));
            }
        }

        if ($recommendations !== []) {
            $this->line('Recommendations:');

            foreach ($recommendations as $recommendation) {
                $this->line(sprintf('- %s', $recommendation));
            }
        }
    }

    /**
     * @param Collection<int, JobLead> $createdJobLeads
     * @return array{ready: int, limited: int, missing_description: int, missing_keywords: int}
     */
    private function analysisCounts(Collection $createdJobLeads): array
    {
        $readyCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => ! $jobLead->hasLimitedAnalysis())
            ->count();
        $missingDescriptionCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => ! Str::of((string) $jobLead->description_text)->trim()->isNotEmpty())
            ->count();
        $missingKeywordsCount = $createdJobLeads
            ->filter(fn (JobLead $jobLead): bool => ($jobLead->extracted_keywords ?? []) === [])
            ->count();

        return [
            'ready' => $readyCount,
            'limited' => $createdJobLeads->count() - $readyCount,
            'missing_description' => $missingDescriptionCount,
            'missing_keywords' => $missingKeywordsCount,
        ];
    }

    /**
     * @param Collection<int, JobLead> $createdJobLeads
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, int|string>>
     */
    private function sourceObservability(Collection $createdJobLeads, array $sources): array
    {
        $observability = [];

        foreach ($sources as $sourceSummary) {
            $sourceKey = (string) $sourceSummary['source'];
            $sourceName = (string) ($sourceSummary['source_name'] ?? $sourceKey);
            $sourceJobLeads = $createdJobLeads
                ->filter(fn (JobLead $jobLead): bool => $jobLead->source_name === $sourceName)
                ->values();
            $visibleByDefaultCount = $sourceJobLeads
                ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status !== JobLead::STATUS_IGNORED)
                ->filter(fn (JobLead $jobLead): bool => $jobLead->locationClassification() !== JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL)
                ->count();
            $analysis = $this->analysisCounts($sourceJobLeads);
            $sourceSummary['visible_by_default'] = $visibleByDefaultCount;
            $sourceSummary['hidden_by_default'] = $sourceJobLeads->count() - $visibleByDefaultCount;
            $sourceSummary['ready_analysis'] = $analysis['ready'];
            $sourceSummary['limited_analysis'] = $analysis['limited'];
            $sourceSummary['missing_description'] = $analysis['missing_description'];
            $sourceSummary['missing_keywords'] = $analysis['missing_keywords'];

            $observability[] = [
                'source' => $sourceKey,
                'imported' => $sourceJobLeads->count(),
                'duplicates' => (int) ($sourceSummary['duplicates'] ?? 0),
                'visible_by_default' => (int) $sourceSummary['visible_by_default'],
                'hidden_by_default' => (int) $sourceSummary['hidden_by_default'],
                'ready_analysis' => (int) $sourceSummary['ready_analysis'],
                'limited_analysis' => (int) $sourceSummary['limited_analysis'],
                'missing_description' => (int) $sourceSummary['missing_description'],
                'missing_keywords' => (int) $sourceSummary['missing_keywords'],
            ];
        }

        return $observability;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, mixed>>
     */
    private function companyCareerTargetDiagnostics(array $sources): array
    {
        foreach ($sources as $sourceSummary) {
            if ((string) ($sourceSummary['source'] ?? '') !== 'company-career-pages') {
                continue;
            }

            $targetDiagnostics = $sourceSummary['target_diagnostics'] ?? [];

            return is_array($targetDiagnostics) ? $targetDiagnostics : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $targetSummary
     * @return array{bucket: string, action: string}
     */
    public function classifyCompanyCareerTarget(array $targetSummary): array
    {
        $fetchedCandidates = (int) ($targetSummary['fetched_candidates'] ?? 0);
        $matchedCandidates = (int) ($targetSummary['matched_candidates'] ?? 0);
        $imported = (int) ($targetSummary['imported'] ?? 0);
        $hiddenByDefault = (int) ($targetSummary['hidden_by_default'] ?? 0);
        $querySkipRate = $this->percentageValue((int) ($targetSummary['skipped_by_query'] ?? 0), $fetchedCandidates);
        $importRate = $this->percentageValue($imported, $fetchedCandidates);

        if ($fetchedCandidates === 0 || ($matchedCandidates === 0 && $imported === 0)) {
            return [
                'bucket' => 'no-signal',
                'action' => 'investigate no-signal targets',
            ];
        }

        if ($imported >= 3 && $importRate >= 35.0 && $hiddenByDefault === 0) {
            return [
                'bucket' => 'strong',
                'action' => 'keep strong targets',
            ];
        }

        if ($imported >= 1 && $matchedCandidates >= 2 && $importRate >= 15.0) {
            return [
                'bucket' => 'promising',
                'action' => 'review promising targets',
            ];
        }

        if ($matchedCandidates > 0 || $querySkipRate < 100.0) {
            return [
                'bucket' => 'weak',
                'action' => 'deprioritize weak targets',
            ];
        }

        return [
            'bucket' => 'no-signal',
            'action' => 'investigate no-signal targets',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $companyCareerTargetPerformance
     * @return list<string>
     */
    private function companyCareerTargetRecommendations(array $companyCareerTargetPerformance): array
    {
        $targetsByBucket = [
            'strong' => [],
            'promising' => [],
            'weak' => [],
            'no-signal' => [],
        ];

        foreach ($companyCareerTargetPerformance as $targetSummary) {
            $classification = $this->classifyCompanyCareerTarget($targetSummary);
            $targetsByBucket[$classification['bucket']][] = (string) $targetSummary['target_name'];
        }

        $recommendations = [];

        foreach ([
            'strong' => 'Keep strong targets:',
            'promising' => 'Review promising targets:',
            'weak' => 'Deprioritize weak targets:',
            'no-signal' => 'Investigate no-signal targets:',
        ] as $bucket => $prefix) {
            if ($targetsByBucket[$bucket] === []) {
                continue;
            }

            $recommendations[] = sprintf('%s %s', $prefix, implode(', ', $targetsByBucket[$bucket]));
        }

        return $recommendations;
    }

    private function percentageValue(int $count, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($count / $total) * 100, 1);
    }

    private function percentage(int $count, int $total): string
    {
        return number_format($this->percentageValue($count, $total), 1).'%';
    }
}
