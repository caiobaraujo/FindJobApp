<?php

namespace App\Console\Commands;

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\JobDiscovery\DiscoverySourceObservability;
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
    private const DEFAULT_SCENARIOS = [
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

    /**
     * @var list<string|null>
     */
    private const BRAZIL_SCENARIOS = [
        null,
        'python',
        'javascript',
        'frontend',
        'backend',
        'remoto',
        'data',
        'devops',
    ];

    /**
     * @var list<string>
     */
    private const BRAZIL_SOURCE_KEYS = [
        'company-career-pages',
        'brazilian-tech-job-boards',
        'gupy-public-jobs',
    ];

    protected $signature = 'discovery:diagnose
        {user_id?}
        {--fresh}
        {--fixture}
        {--brazil}
        {--query=*}';

    protected $description = 'Run deterministic discovery diagnostics and write a fixture-friendly calibration report.';

    public function handle(
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        DiscoverySourceObservability $discoverySourceObservability,
    ): int {
        $this->configureFixtureMode();
        $this->configureBrazilMode();

        $user = $this->resolvedUser();

        if ($user === null) {
            $this->error('User not found.');

            return SymfonyCommand::FAILURE;
        }

        $this->resetDiagnosticUserWorkspaceIfRequested($user);

        $scenarioDefinitions = $this->scenarioDefinitions();
        $scenarioResults = [];
        $sourcePerformance = [];
        $targetPerformance = [];
        $usingSyntheticUser = $this->argument('user_id') === null;

        foreach ($scenarioDefinitions as $scenarioDefinition) {
            if ($usingSyntheticUser) {
                $this->resetUserWorkspace($user);
            }

            $scenarioResult = $this->runScenario(
                $jobLeadDiscoveryRunner,
                $discoverySourceObservability,
                $user,
                $scenarioDefinition['query'],
                $scenarioDefinition['label'],
            );

            $scenarioResults[] = $scenarioResult;
            $sourcePerformance = $this->mergeSourcePerformance($sourcePerformance, $scenarioResult['sources']);
            $targetPerformance = $this->mergeTargetPerformance($targetPerformance, $scenarioResult['sources']);
        }

        $warnings = $this->warnings($scenarioResults, $sourcePerformance);
        $recommendations = $this->recommendations($scenarioResults, $sourcePerformance, $targetPerformance, $warnings);
        $report = $this->markdownReport(
            $user->id,
            $scenarioResults,
            $sourcePerformance,
            $targetPerformance,
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

    private function configureFixtureMode(): void
    {
        if (! $this->option('fixture')) {
            return;
        }

        config([
            'job_discovery.use_fixture_responses' => true,
            'job_discovery.supported_sources' => config('job_discovery.fixture_supported_sources', config('job_discovery.supported_sources', [])),
            'job_discovery.company_career_targets' => config('job_discovery.fixture_company_career_targets', config('job_discovery.company_career_targets', [])),
            'job_discovery.brazilian_tech_job_board_targets' => config('job_discovery.fixture_brazilian_tech_job_board_targets', config('job_discovery.brazilian_tech_job_board_targets', [])),
            'job_discovery.gupy_public_job_targets' => config('job_discovery.fixture_gupy_public_job_targets', config('job_discovery.gupy_public_job_targets', [])),
        ]);
    }

    private function configureBrazilMode(): void
    {
        if (! $this->option('brazil')) {
            return;
        }

        config([
            'job_discovery.supported_sources' => self::BRAZIL_SOURCE_KEYS,
        ]);
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
     * @return list<array{label: string, query: string|null}>
     */
    private function scenarioDefinitions(): array
    {
        $queries = $this->queryOptionValues();

        if ($queries !== []) {
            return array_map(
                fn (string $query): array => [
                    'label' => $query,
                    'query' => $query,
                ],
                $queries,
            );
        }

        $defaults = $this->option('brazil') ? self::BRAZIL_SCENARIOS : self::DEFAULT_SCENARIOS;

        return array_map(
            fn (?string $query): array => [
                'label' => $query === null ? 'no query' : $query,
                'query' => $query,
            ],
            $defaults,
        );
    }

    /**
     * @return list<string>
     */
    private function queryOptionValues(): array
    {
        $rawQueries = $this->option('query');

        if (! is_array($rawQueries)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(function (mixed $query): ?string {
                if (! is_string($query)) {
                    return null;
                }

                $normalized = trim(preg_replace('/\s+/', ' ', $query) ?? $query);

                return $normalized === '' ? null : $normalized;
            }, $rawQueries),
            fn (?string $query): bool => $query !== null,
        )));
    }

    /**
     * @return array{
     *     label: string,
     *     query: string|null,
     *     aggregate: array<string, int>,
     *     sources: list<array<string, mixed>>,
     *     created_lead_ids: list<int>,
     *     location_counts: array<string, int>,
     *     visibility: array<string, int|bool>,
     *     analysis: array<string, int>,
     *     source_observability: list<array<string, mixed>>,
     *     warnings: list<string>,
     *     batch_id: string
     * }
     */
    private function runScenario(
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        DiscoverySourceObservability $discoverySourceObservability,
        User $user,
        ?string $query,
        string $label,
    ): array {
        $discoveryBatchId = (string) Str::uuid();
        $aggregate = [
            'fetched' => 0,
            'parsed' => 0,
            'matched' => 0,
            'created' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'failed' => 0,
            'skipped_not_matching_query' => 0,
            'skipped_missing_company' => 0,
            'skipped_expired' => 0,
        ];
        $sources = [];

        foreach ($jobLeadDiscoveryRunner->supportedSources() as $source) {
            try {
                $summary = $jobLeadDiscoveryRunner->discoverForUser($user->id, $source, $query, $discoveryBatchId);
                $summary['source_name'] = $jobLeadDiscoveryRunner->source($source)->sourceName();
            } catch (Throwable $throwable) {
                $summary = $this->failedSourceSummary($jobLeadDiscoveryRunner, $source, $discoveryBatchId, $throwable);
            }

            $summary = $this->appendSourceTargetTotals($summary);

            foreach ([
                'fetched',
                'parsed',
                'matched',
                'created',
                'duplicates',
                'invalid',
                'failed',
                'skipped_not_matching_query',
                'skipped_missing_company',
                'skipped_expired',
            ] as $metric) {
                $aggregate[$metric] += (int) ($summary[$metric] ?? 0);
            }

            $sources[] = $summary;
        }

        $jobLeadDiscoveryRunner->recordDiscoveryRun($user->id, $aggregate['created'], $discoveryBatchId);

        $createdJobLeads = JobLead::query()
            ->where('user_id', $user->id)
            ->where('discovery_batch_id', $discoveryBatchId)
            ->orderBy('id')
            ->get();

        $locationCounts = $this->locationCounts($createdJobLeads);
        $visibility = $this->visibilityCounts($createdJobLeads);
        $analysis = $discoverySourceObservability->analysisCounts($createdJobLeads);
        $sourceObservability = $discoverySourceObservability->summarizeSources($createdJobLeads, $sources);
        $sources = $this->mergeSourceObservabilityIntoSummaries($sources, $sourceObservability);

        return [
            'label' => $label,
            'query' => $query,
            'aggregate' => $aggregate,
            'sources' => $sources,
            'created_lead_ids' => $createdJobLeads->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            'location_counts' => $locationCounts,
            'visibility' => $visibility,
            'analysis' => $analysis,
            'source_observability' => $sourceObservability,
            'warnings' => $this->scenarioWarnings($label, $aggregate, $sources, $locationCounts, (int) $visibility['default_brazil']),
            'batch_id' => $discoveryBatchId,
        ];
    }

    private function failedSourceSummary(
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        string $source,
        string $discoveryBatchId,
        Throwable $throwable,
    ): array {
        return [
            'source' => $source,
            'source_name' => $jobLeadDiscoveryRunner->source($source)->sourceName(),
            'fetched' => 0,
            'parsed' => 0,
            'matched' => 0,
            'created' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'failed' => 1,
            'skipped_not_matching_query' => 0,
            'skipped_missing_company' => 0,
            'skipped_expired' => 0,
            'query_used' => false,
            'discovery_batch_id' => $discoveryBatchId,
            'error' => $throwable->getMessage(),
            'target_diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function appendSourceTargetTotals(array $summary): array
    {
        $targets = is_array($summary['target_diagnostics'] ?? null)
            ? $summary['target_diagnostics']
            : [];

        $summary['parsed'] = (int) ($summary['parsed_jobs'] ?? 0);
        $summary['matched'] = collect($targets)->sum(fn (array $target): int => (int) ($target['matched_candidates'] ?? 0));
        $summary['skipped_missing_company'] = collect($targets)->sum(fn (array $target): int => (int) ($target['skipped_missing_company'] ?? 0));
        $summary['skipped_expired'] = collect($targets)->sum(fn (array $target): int => (int) ($target['skipped_expired'] ?? 0));

        return $summary;
    }

    /**
     * @param Collection<int, JobLead> $createdJobLeads
     * @return array<string, int>
     */
    private function locationCounts(Collection $createdJobLeads): array
    {
        $counts = [
            JobLead::LOCATION_CLASSIFICATION_BRAZIL => 0,
            JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL => 0,
            JobLead::LOCATION_CLASSIFICATION_UNKNOWN => 0,
        ];

        foreach ($createdJobLeads as $jobLead) {
            $counts[$jobLead->locationClassification()]++;
        }

        return $counts;
    }

    /**
     * @param Collection<int, JobLead> $createdJobLeads
     * @return array<string, int|bool>
     */
    private function visibilityCounts(Collection $createdJobLeads): array
    {
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

        return [
            'latest_batch' => $createdJobLeads->count(),
            'default_brazil' => $defaultWorkspaceVisibleCount,
            'all_workspace' => $allWorkspaceVisibleCount,
            'hidden_total' => $allWorkspaceVisibleCount - $defaultWorkspaceVisibleCount,
            'hidden_by_status' => $hiddenByStatusCount,
            'hidden_by_location' => $hiddenByLocationCount,
            'hidden_by_both' => $hiddenByBothCount,
            'hidden_by_default' => $defaultWorkspaceVisibleCount < $allWorkspaceVisibleCount,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sourceSummaries
     * @param list<array<string, mixed>> $sourceObservability
     * @return list<array<string, mixed>>
     */
    private function mergeSourceObservabilityIntoSummaries(array $sourceSummaries, array $sourceObservability): array
    {
        return array_map(function (array $sourceSummary) use ($sourceObservability): array {
            $observability = collect($sourceObservability)
                ->firstWhere('source', (string) ($sourceSummary['source'] ?? ''));

            if (! is_array($observability)) {
                return $sourceSummary;
            }

            return [
                ...$sourceSummary,
                'visible_by_default' => (int) ($observability['visible_by_default'] ?? 0),
                'hidden_by_default' => (int) ($observability['hidden_by_default'] ?? 0),
                'ready_analysis' => (int) ($observability['ready_analysis'] ?? 0),
                'limited_analysis' => (int) ($observability['limited_analysis'] ?? 0),
                'missing_description' => (int) ($observability['missing_description'] ?? 0),
                'missing_keywords' => (int) ($observability['missing_keywords'] ?? 0),
            ];
        }, $sourceSummaries);
    }

    /**
     * @param array<string, array<string, mixed>> $sourcePerformance
     * @param list<array<string, mixed>> $sources
     * @return array<string, array<string, mixed>>
     */
    private function mergeSourcePerformance(array $sourcePerformance, array $sources): array
    {
        foreach ($sources as $sourceSummary) {
            $sourceKey = (string) ($sourceSummary['source'] ?? '');

            if (! isset($sourcePerformance[$sourceKey])) {
                $sourcePerformance[$sourceKey] = [
                    'source' => $sourceKey,
                    'parsed' => 0,
                    'matched' => 0,
                    'fetched' => 0,
                    'created' => 0,
                    'duplicates' => 0,
                    'skipped_not_matching_query' => 0,
                    'skipped_missing_company' => 0,
                    'skipped_expired' => 0,
                    'invalid' => 0,
                    'failed' => 0,
                    'visible_by_default' => 0,
                    'hidden_by_default' => 0,
                    'ready_analysis' => 0,
                    'limited_analysis' => 0,
                    'missing_description' => 0,
                    'missing_keywords' => 0,
                ];
            }

            foreach ([
                'parsed',
                'matched',
                'fetched',
                'created',
                'duplicates',
                'skipped_not_matching_query',
                'skipped_missing_company',
                'skipped_expired',
                'invalid',
                'failed',
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
    private function mergeTargetPerformance(array $targetPerformance, array $sources): array
    {
        foreach ($sources as $sourceSummary) {
            $sourceKey = (string) ($sourceSummary['source'] ?? '');

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

                $performanceKey = sprintf('%s::%s', $sourceKey, $targetIdentifier);

                if (! isset($targetPerformance[$performanceKey])) {
                    $targetPerformance[$performanceKey] = [
                        'source' => $sourceKey,
                        'target_identifier' => $targetIdentifier,
                        'target_name' => $targetSummary['target_name'] ?? $targetIdentifier,
                        'platform' => $targetSummary['platform'] ?? $targetSummary['target_name'] ?? $targetIdentifier,
                        'parser_strategy' => $targetSummary['parser_strategy'] ?? 'structured_lists',
                        'fetched_candidates' => 0,
                        'matched_candidates' => 0,
                        'imported' => 0,
                        'deduplicated' => 0,
                        'skipped_by_query' => 0,
                        'skipped_missing_company' => 0,
                        'skipped_expired' => 0,
                        'failed' => 0,
                        'hidden_by_default' => 0,
                        'limited_analysis' => 0,
                        'missing_description' => 0,
                        'missing_keywords' => 0,
                    ];
                }

                foreach ([
                    'fetched_candidates',
                    'matched_candidates',
                    'imported',
                    'deduplicated',
                    'skipped_by_query',
                    'skipped_missing_company',
                    'skipped_expired',
                    'failed',
                    'hidden_by_default',
                ] as $metric) {
                    $targetPerformance[$performanceKey][$metric] += (int) ($targetSummary[$metric] ?? 0);
                }
            }
        }

        foreach ($targetPerformance as $performanceKey => $targetSummary) {
            $targetPerformance[$performanceKey]['query_skip_rate'] = $this->percentage(
                (int) $targetSummary['skipped_by_query'],
                (int) $targetSummary['fetched_candidates'],
            );
            $targetPerformance[$performanceKey]['import_rate'] = $this->percentage(
                (int) $targetSummary['imported'],
                (int) $targetSummary['fetched_candidates'],
            );
        }

        uasort($targetPerformance, function (array $left, array $right): int {
            $sourceComparison = strcmp((string) $left['source'], (string) $right['source']);

            if ($sourceComparison !== 0) {
                return $sourceComparison;
            }

            return strcmp((string) $left['target_name'], (string) $right['target_name']);
        });

        return $targetPerformance;
    }

    /**
     * @param array<string, int> $aggregate
     * @param list<array<string, mixed>> $sources
     * @param array<string, int> $locationCounts
     * @return list<string>
     */
    private function scenarioWarnings(
        string $label,
        array $aggregate,
        array $sources,
        array $locationCounts,
        int $defaultWorkspaceVisibleCount,
    ): array {
        $warnings = [];

        if ($aggregate['created'] === 0) {
            $warnings[] = sprintf('Search "%s" created 0 leads.', $label);
        }

        if ($aggregate['fetched'] >= 3 && $aggregate['created'] === 0) {
            $warnings[] = sprintf('Search "%s" fetched many results but created 0 leads.', $label);
        }

        if ($aggregate['skipped_not_matching_query'] >= max(3, (int) ceil($aggregate['fetched'] / 2))) {
            $warnings[] = sprintf('Search "%s" skipped many fetched jobs because they did not match the query.', $label);
        }

        if ($aggregate['duplicates'] >= max(3, (int) ceil($aggregate['fetched'] / 2))) {
            $warnings[] = sprintf('Search "%s" hit a high duplicate rate.', $label);
        }

        if ($aggregate['created'] > 0 && $locationCounts[JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL] > $aggregate['created'] / 2) {
            $warnings[] = sprintf('Search "%s" created mostly international leads.', $label);
        }

        if ($aggregate['created'] > 0 && $defaultWorkspaceVisibleCount < $aggregate['created']) {
            $warnings[] = sprintf('Search "%s" created leads hidden from the default Brazil workspace.', $label);
        }

        if ($aggregate['created'] > 0) {
            $limitedAnalysisCount = collect($sources)
                ->sum(fn (array $sourceSummary): int => (int) ($sourceSummary['limited_analysis'] ?? 0));

            if ($limitedAnalysisCount >= max(2, (int) ceil($aggregate['created'] / 2))) {
                $warnings[] = sprintf('Search "%s" created many leads with limited analysis.', $label);
            }
        }

        foreach ($sources as $sourceSummary) {
            $source = (string) ($sourceSummary['source'] ?? '');
            $fetched = (int) ($sourceSummary['fetched'] ?? 0);
            $invalid = (int) ($sourceSummary['invalid'] ?? 0);
            $failed = (int) ($sourceSummary['failed'] ?? 0);

            if ($failed > 0) {
                $warnings[] = sprintf('Source %s failed during search "%s".', $source, $label);
            }

            if ($fetched > 0 && $invalid >= max(3, (int) ceil($fetched / 2))) {
                $warnings[] = sprintf('Source %s returned mostly invalid links during search "%s".', $source, $label);
            }

            if (
                (int) ($sourceSummary['created'] ?? 0) > 0
                && (int) ($sourceSummary['limited_analysis'] ?? 0) >= max(2, (int) ceil((int) $sourceSummary['created'] / 2))
            ) {
                $warnings[] = sprintf('Source %s created mostly limited-analysis leads during search "%s".', $source, $label);
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
     * @param array<string, array<string, mixed>> $targetPerformance
     * @param list<string> $warnings
     * @return list<string>
     */
    private function recommendations(
        array $scenarioResults,
        array $sourcePerformance,
        array $targetPerformance,
        array $warnings,
    ): array {
        $recommendations = [];

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

        if ($this->option('brazil')) {
            foreach ($this->targetRecommendations($targetPerformance) as $recommendation) {
                $recommendations[] = $recommendation;
            }
        } else {
            foreach ($scenarioResults as $scenarioResult) {
                if ((bool) ($scenarioResult['visibility']['hidden_by_default'] ?? false)) {
                    $recommendations[] = 'Created leads hidden by default: review location classification or default filter behavior.';
                }
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
     * @param array<string, array<string, mixed>> $targetPerformance
     * @param list<string> $warnings
     * @param list<string> $recommendations
     */
    private function markdownReport(
        int $userId,
        array $scenarioResults,
        array $sourcePerformance,
        array $targetPerformance,
        array $warnings,
        array $recommendations,
    ): string {
        $lines = [
            '# Discovery Diagnostics',
            '',
            sprintf('- Timestamp: %s', now()->toIso8601String()),
            sprintf('- User ID: %d', $userId),
            sprintf('- Mode: %s', $this->option('brazil') ? 'brazil calibration' : 'general diagnostics'),
            '',
            '## Scenario Summary',
            '',
            '| Search | Fetched | Parsed | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Brazil | International | Hidden by default | Limited analysis | Missing description | Missing keywords |',
            '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |',
        ];

        foreach ($scenarioResults as $scenarioResult) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d |',
                $scenarioResult['label'],
                $scenarioResult['aggregate']['fetched'],
                $scenarioResult['aggregate']['parsed'],
                $scenarioResult['aggregate']['matched'],
                $scenarioResult['aggregate']['created'],
                $scenarioResult['aggregate']['duplicates'],
                $scenarioResult['aggregate']['skipped_not_matching_query'],
                $scenarioResult['aggregate']['skipped_missing_company'],
                $scenarioResult['aggregate']['skipped_expired'],
                $scenarioResult['aggregate']['failed'],
                $scenarioResult['location_counts'][JobLead::LOCATION_CLASSIFICATION_BRAZIL],
                $scenarioResult['location_counts'][JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL],
                $scenarioResult['visibility']['hidden_total'],
                $scenarioResult['analysis']['limited'],
                $scenarioResult['analysis']['missing_description'],
                $scenarioResult['analysis']['missing_keywords'],
            );
        }

        $lines[] = '';
        $lines[] = '## Source Performance';
        $lines[] = '';
        $lines[] = '| Source | Fetched | Parsed | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Hidden by default | Limited analysis | Missing description | Missing keywords |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

        foreach ($sourcePerformance as $sourceSummary) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d |',
                $sourceSummary['source'],
                $sourceSummary['fetched'],
                $sourceSummary['parsed'],
                $sourceSummary['matched'],
                $sourceSummary['created'],
                $sourceSummary['duplicates'],
                $sourceSummary['skipped_not_matching_query'],
                $sourceSummary['skipped_missing_company'],
                $sourceSummary['skipped_expired'],
                $sourceSummary['failed'],
                $sourceSummary['hidden_by_default'],
                $sourceSummary['limited_analysis'],
                $sourceSummary['missing_description'],
                $sourceSummary['missing_keywords'],
            );
        }

        if ($targetPerformance !== []) {
            $lines[] = '';
            $lines[] = '## Target Performance';
            $lines[] = '';
            $lines[] = '| Source | Target | Platform | Parser | Bucket | Recommendation | Fetched | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Import rate | Query skip rate |';
            $lines[] = '| --- | --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

            foreach ($targetPerformance as $targetSummary) {
                $classification = $this->classifyCompanyCareerTarget($targetSummary);

                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s | %d | %d | %d | %d | %d | %d | %d | %d | %s | %s |',
                    $targetSummary['source'],
                    $targetSummary['target_name'],
                    $targetSummary['platform'],
                    $targetSummary['parser_strategy'],
                    $classification['bucket'],
                    $classification['action'],
                    $targetSummary['fetched_candidates'],
                    $targetSummary['matched_candidates'],
                    $targetSummary['imported'],
                    $targetSummary['deduplicated'],
                    $targetSummary['skipped_by_query'],
                    $targetSummary['skipped_missing_company'],
                    $targetSummary['skipped_expired'],
                    $targetSummary['failed'],
                    $targetSummary['import_rate'],
                    $targetSummary['query_skip_rate'],
                );
            }

            $lines[] = '';
            $lines[] = '## Target Recommendations';
            $lines[] = '';

            foreach ($this->targetRecommendations($targetPerformance) as $recommendation) {
                $lines[] = sprintf('- %s', $recommendation);
            }
        }

        $lines[] = '';
        $lines[] = '## Scenario Details';
        $lines[] = '';

        foreach ($scenarioResults as $scenarioResult) {
            $lines[] = sprintf('### %s', $scenarioResult['label']);
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
            $lines[] = sprintf(
                '- Imported vs deduplicated: %d imported, %d deduplicated',
                $scenarioResult['aggregate']['created'],
                $scenarioResult['aggregate']['duplicates'],
            );
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
            $lines[] = '| Source | Fetched | Parsed | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Hidden by default | Limited analysis | Missing description | Missing keywords |';
            $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

            foreach ($scenarioResult['sources'] as $sourceSummary) {
                $lines[] = sprintf(
                    '| %s | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d | %d |',
                    $sourceSummary['source'],
                    $sourceSummary['fetched'],
                    $sourceSummary['parsed'],
                    $sourceSummary['matched'],
                    $sourceSummary['created'],
                    $sourceSummary['duplicates'],
                    $sourceSummary['skipped_not_matching_query'],
                    $sourceSummary['skipped_missing_company'],
                    $sourceSummary['skipped_expired'],
                    $sourceSummary['failed'],
                    $sourceSummary['hidden_by_default'],
                    $sourceSummary['limited_analysis'],
                    $sourceSummary['missing_description'],
                    $sourceSummary['missing_keywords'],
                );
            }

            $scenarioTargets = $this->scenarioTargetDiagnostics($scenarioResult['sources']);

            if ($scenarioTargets !== []) {
                $lines[] = '';
                $lines[] = 'Target diagnostics:';
                $lines[] = '';
                $lines[] = '| Source | Target | Platform | Parser | Bucket | Recommendation | Fetched | Matched | Imported | Deduplicated | Query skipped | Missing company | Expired/closed | Failed | Import rate | Query skip rate |';
                $lines[] = '| --- | --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

                foreach ($scenarioTargets as $targetSummary) {
                    $classification = $this->classifyCompanyCareerTarget($targetSummary);

                    $lines[] = sprintf(
                        '| %s | %s | %s | %s | %s | %s | %d | %d | %d | %d | %d | %d | %d | %d | %s | %s |',
                        $targetSummary['source'],
                        $targetSummary['target_name'],
                        $targetSummary['platform'],
                        $targetSummary['parser_strategy'],
                        $classification['bucket'],
                        $classification['action'],
                        $targetSummary['fetched_candidates'],
                        $targetSummary['matched_candidates'],
                        $targetSummary['imported'],
                        $targetSummary['deduplicated'],
                        $targetSummary['skipped_by_query'],
                        $targetSummary['skipped_missing_company'],
                        $targetSummary['skipped_expired'],
                        $targetSummary['failed'],
                        $targetSummary['import_rate'],
                        $targetSummary['query_skip_rate'],
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
            ['Search', 'Fetched', 'Parsed', 'Matched', 'Imported', 'Deduplicated', 'Missing company', 'Expired/closed'],
            array_map(
                fn (array $scenarioResult): array => [
                    $scenarioResult['label'],
                    $scenarioResult['aggregate']['fetched'],
                    $scenarioResult['aggregate']['parsed'],
                    $scenarioResult['aggregate']['matched'],
                    $scenarioResult['aggregate']['created'],
                    $scenarioResult['aggregate']['duplicates'],
                    $scenarioResult['aggregate']['skipped_missing_company'],
                    $scenarioResult['aggregate']['skipped_expired'],
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
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, mixed>>
     */
    private function scenarioTargetDiagnostics(array $sources): array
    {
        $targets = [];

        foreach ($sources as $sourceSummary) {
            $sourceKey = (string) ($sourceSummary['source'] ?? '');

            foreach (($sourceSummary['target_diagnostics'] ?? []) as $targetSummary) {
                if (! is_array($targetSummary)) {
                    continue;
                }

                $targets[] = [
                    ...$targetSummary,
                    'source' => $sourceKey,
                ];
            }
        }

        usort($targets, function (array $left, array $right): int {
            $sourceComparison = strcmp((string) $left['source'], (string) $right['source']);

            if ($sourceComparison !== 0) {
                return $sourceComparison;
            }

            return strcmp((string) $left['target_name'], (string) $right['target_name']);
        });

        return $targets;
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
        $failed = (int) ($targetSummary['failed'] ?? 0);
        $hiddenByDefault = (int) ($targetSummary['hidden_by_default'] ?? 0);
        $querySkipRate = $this->percentageValue((int) ($targetSummary['skipped_by_query'] ?? 0), $fetchedCandidates);
        $importRate = $this->percentageValue($imported, $fetchedCandidates);

        if ($fetchedCandidates === 0 || ($matchedCandidates === 0 && $imported === 0)) {
            return [
                'bucket' => 'no-signal',
                'action' => 'investigate',
            ];
        }

        if ($failed > 0) {
            return [
                'bucket' => 'weak',
                'action' => 'investigate',
            ];
        }

        if ($imported >= 2 && $matchedCandidates >= 2 && $importRate >= 35.0 && $hiddenByDefault === 0) {
            return [
                'bucket' => 'strong',
                'action' => 'keep',
            ];
        }

        if ($imported >= 1 && $matchedCandidates >= 1 && $importRate >= 15.0) {
            return [
                'bucket' => 'promising',
                'action' => 'review',
            ];
        }

        if ($matchedCandidates > 0 || $querySkipRate < 100.0) {
            return [
                'bucket' => 'weak',
                'action' => 'deprioritize',
            ];
        }

        return [
            'bucket' => 'no-signal',
            'action' => 'investigate',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $targetPerformance
     * @return list<string>
     */
    private function targetRecommendations(array $targetPerformance): array
    {
        $targetsByRecommendation = [
            'keep' => [],
            'review' => [],
            'deprioritize' => [],
            'investigate' => [],
        ];

        foreach ($targetPerformance as $targetSummary) {
            if ($this->skipTargetRecommendation($targetSummary)) {
                continue;
            }

            $classification = $this->classifyCompanyCareerTarget($targetSummary);
            $targetsByRecommendation[$classification['action']][] = sprintf(
                '%s / %s',
                (string) $targetSummary['source'],
                (string) $targetSummary['target_name'],
            );
        }

        $recommendations = [];

        foreach ([
            'keep' => 'Keep:',
            'review' => 'Review:',
            'deprioritize' => 'Deprioritize:',
            'investigate' => 'Investigate:',
        ] as $action => $prefix) {
            if ($targetsByRecommendation[$action] === []) {
                continue;
            }

            $recommendations[] = sprintf('%s %s', $prefix, implode(', ', $targetsByRecommendation[$action]));
        }

        return $recommendations;
    }

    /**
     * @param array<string, mixed> $targetSummary
     */
    private function skipTargetRecommendation(array $targetSummary): bool
    {
        $targetName = is_string($targetSummary['target_name'] ?? null)
            ? trim((string) $targetSummary['target_name'])
            : '';

        if ($targetName === '') {
            return false;
        }

        return filter_var($targetName, FILTER_VALIDATE_URL) !== false;
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
