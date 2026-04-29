<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Http\Requests\BulkImportJobLeadsRequest;
use App\Http\Requests\ImportPostJobLeadRequest;
use App\Http\Requests\ImportJobLeadFromUrlRequest;
use App\Http\Requests\StoreJobLeadRequest;
use App\Http\Requests\UpdateJobLeadRequest;
use App\Models\JobLead;
use App\Models\UserProfile;
use App\Services\JobDiscovery\DiscoverySourceObservability;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use App\Services\JobLeadKeywordExtractor;
use App\Services\JobLeadImportService;
use App\Services\JobLeadMatchAnalyzer;
use App\Services\ResumeDiscoverySignalBuilder;
use App\Services\ResumeDiscoveryQueryProfileResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class JobLeadController extends Controller
{
    private const BULK_IMPORT_LIMIT = 50;

    public function index(Request $request): Response
    {
        $userProfile = $this->userProfile($request->user()->id);
        $filters = $this->normalizedFilters($this->filters($request), $userProfile);
        $matchedOnly = $request->routeIs('matched-jobs.index');
        $detectedResumeSkills = $this->detectedResumeSkills($userProfile);
        $jobLeads = $this->workspaceJobLeads($request->user()->id, $filters, $userProfile);

        if ($filters['is_latest_discovery_view']) {
            Log::info('job lead workspace latest discovery view', [
                'user_id' => $request->user()->id,
                'resolved_discovery_batch_id' => $this->resolvedDiscoveryBatchId($filters['discovery_batch'] ?? null, $userProfile),
                'filters' => $this->workspaceLogFilters($filters),
                'visible_job_lead_ids' => $jobLeads->pluck('id')->all(),
            ]);
        }

        $matchedJobs = $this->jobCards($jobLeads, $userProfile, $matchedOnly, $detectedResumeSkills);

        return Inertia::render('JobLeads/Index', [
            'analysisStates' => JobLead::analysisStates(),
            'analysisReadinessOptions' => JobLead::analysisReadinessOptions(),
            'discoveryStatus' => $this->discoveryStatus($userProfile),
            'detectedResumeSkills' => $detectedResumeSkills,
            'filters' => [
                'analysis_readiness' => $filters['analysis_readiness'] ?? '',
                'analysis_state' => $filters['analysis_state'] ?? '',
                'discovery_batch' => $filters['discovery_batch'] ?? '',
                'lead_status' => $filters['lead_status'] ?? '',
                'location_scope' => $filters['location_scope'],
                'search' => $filters['search'] ?? '',
                'show_ignored' => $filters['show_ignored'],
                'work_mode' => $filters['work_mode'] ?? '',
            ],
            'isLatestDiscoveryView' => $filters['is_latest_discovery_view'],
            'hasResumeProfile' => $userProfile !== null,
            'leadStatuses' => JobLead::leadStatuses(),
            'leadStatusCounts' => $this->leadStatusCounts($request->user()->id),
            'leadsMissingAnalysisCount' => $this->leadsMissingAnalysisCount($jobLeads),
            'matchedOnly' => $matchedOnly,
            'resumeReady' => $this->resumeReady($userProfile),
            'resumeNeedsTextInput' => $this->resumeNeedsTextInput($userProfile),
            'matchedJobs' => $matchedJobs,
            'latestDiscoveryMatchFunnel' => $matchedOnly
                ? $this->latestDiscoveryMatchFunnel(
                    $request->user()->id,
                    $filters,
                    $userProfile,
                )
                : null,
            'matchedJobsVisibilitySummary' => $matchedOnly
                ? $this->matchedJobsVisibilitySummary(
                    $request->user()->id,
                    $filters,
                    $userProfile,
                    count($matchedJobs),
                )
                : null,
            'workModes' => JobLead::workModes(),
            'latestDiscoveryBatchId' => $userProfile?->last_discovery_batch_id,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $userProfile = $this->userProfile($user->id);
        $matchedJobsCount = count($this->matchedJobs(
            $this->workspaceJobLeads($user->id, $this->defaultMatchedJobsFilters(), $userProfile),
            $userProfile,
        ));

        return Inertia::render('Dashboard', [
            'applications' => $user
                ->applications()
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Application $application): array => $this->applicationData($application))
                ->all(),
            'statusCounts' => $this->statusCounts($user->id),
            'totalApplications' => $user->applications()->count(),
            'hasResumeProfile' => $userProfile !== null,
            'resumeReady' => $this->resumeReady($userProfile),
            'resumeNeedsTextInput' => $this->resumeNeedsTextInput($userProfile),
            'matchedJobsCount' => $matchedJobsCount,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('JobLeads/Create', [
            'leadStatuses' => JobLead::leadStatuses(),
            'workModes' => JobLead::workModes(),
        ]);
    }

    public function store(StoreJobLeadRequest $request, JobLeadImportService $jobLeadImportService): RedirectResponse
    {
        $result = $jobLeadImportService->importForUser(
            $request->user()->id,
            $request->string('source_url')->value(),
            $this->storeImportAttributes($request->validated()),
        );

        if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE && $result['job_lead'] !== null) {
            return $this->duplicateJobLeadRedirect($result['job_lead']);
        }

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.job_lead_edit.create_success'));
    }

    public function importFromUrl(ImportJobLeadFromUrlRequest $request, JobLeadImportService $jobLeadImportService): RedirectResponse
    {
        $result = $jobLeadImportService->importForUser(
            $request->user()->id,
            $request->string('source_url')->value(),
            $this->urlImportAttributes($request),
        );

        if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE && $result['job_lead'] !== null) {
            return $this->duplicateJobLeadRedirect($result['job_lead']);
        }

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.matched_jobs.import_success'));
    }

    public function importFromPost(ImportPostJobLeadRequest $request, JobLeadImportService $jobLeadImportService): RedirectResponse|JsonResponse
    {
        $sourceUrl = $request->input('source_url');

        $result = $jobLeadImportService->importForUser(
            $request->user()->id,
            is_string($sourceUrl) ? $this->nullableString($sourceUrl) : null,
            $this->postImportAttributes($request),
        );

        if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE && $result['job_lead'] !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => JobLeadImportService::STATUS_DUPLICATE,
                    'job_lead_id' => $result['job_lead']->id,
                    'redirect_url' => route('job-leads.edit', $result['job_lead']),
                    'message' => __('app.job_lead_create.duplicate_error'),
                ], 409);
            }

            return $this->duplicateJobLeadRedirect($result['job_lead']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => JobLeadImportService::STATUS_CREATED,
                'job_lead_id' => $result['job_lead']?->id,
                'redirect_url' => route('job-leads.index'),
                'message' => __('app.matched_jobs.import_success'),
            ], 201);
        }

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.matched_jobs.import_success'));
    }

    public function bulkImportFromUrls(BulkImportJobLeadsRequest $request, JobLeadImportService $jobLeadImportService): RedirectResponse
    {
        $urls = $this->bulkImportUrls($request->string('source_urls')->value());
        $createdCount = 0;
        $duplicateCount = 0;
        $invalidCount = 0;

        foreach (array_slice($urls, 0, self::BULK_IMPORT_LIMIT) as $sourceUrl) {
            $result = $jobLeadImportService->importForUser(
                $request->user()->id,
                $sourceUrl,
                $this->bulkImportAttributes($sourceUrl),
            );

            if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE) {
                $duplicateCount++;

                continue;
            }

            if ($result['status'] === JobLeadImportService::STATUS_INVALID) {
                $invalidCount++;

                continue;
            }

            $createdCount++;
        }

        $invalidCount += max(0, count($urls) - self::BULK_IMPORT_LIMIT);

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.job_lead_bulk_import.summary', [
                'created' => $createdCount,
                'duplicates' => $duplicateCount,
                'invalid' => $invalidCount,
            ]));
    }

    public function discover(
        Request $request,
        JobLeadDiscoveryRunner $jobLeadDiscoveryRunner,
        DiscoverySourceObservability $discoverySourceObservability,
        ResumeDiscoveryQueryProfileResolver $resumeDiscoveryQueryProfileResolver,
    ): RedirectResponse
    {
        $validatedData = $request->validate([
            'search_query' => ['nullable', 'string', 'max:120'],
        ]);
        $searchQuery = Str::of((string) ($validatedData['search_query'] ?? ''))->squish()->value();
        $searchQuery = $searchQuery === '' ? null : $searchQuery;
        $userProfile = $this->userProfile($request->user()->id);
        $queryProfiles = $resumeDiscoveryQueryProfileResolver->resolve(
            $searchQuery,
            $userProfile?->base_resume_text,
            $userProfile?->core_skills ?? [],
        );
        $discoveryBatchId = (string) Str::uuid();
        $summary = [
            'fetched' => 0,
            'created' => 0,
            'duplicates' => 0,
            'skipped_not_matching_query' => 0,
            'invalid' => 0,
            'failed' => 0,
            'matched_by_query_profiles' => 0,
            'created_by_query_profiles' => 0,
        ];
        $sourceResults = [];

        foreach ($jobLeadDiscoveryRunner->supportedSources() as $source) {
            try {
                $sourceSummary = $jobLeadDiscoveryRunner->discoverForUser(
                    $request->user()->id,
                    $source,
                    $searchQuery,
                    $discoveryBatchId,
                    $queryProfiles,
                );
            } catch (Throwable) {
                $summary['failed']++;
                $sourceResults[] = [
                    'source' => $source,
                    'source_name' => $jobLeadDiscoveryRunner->source($source)->sourceName(),
                    'fetched' => 0,
                    'created' => 0,
                    'duplicates' => 0,
                    'skipped_not_matching_query' => 0,
                    'invalid' => 0,
                    'failed' => 1,
                    'query_used' => $searchQuery !== null,
                    'query_profile_keys' => [],
                    'matched_by_query_profiles' => 0,
                    'created_by_query_profiles' => 0,
                    'created_match_details' => [],
                    'discovery_batch_id' => $discoveryBatchId,
                ];

                continue;
            }

            $summary['fetched'] += $sourceSummary['fetched'];
            $summary['created'] += $sourceSummary['created'];
            $summary['duplicates'] += $sourceSummary['duplicates'];
            $summary['skipped_not_matching_query'] += $sourceSummary['skipped_not_matching_query'];
            $summary['invalid'] += $sourceSummary['invalid'];
            $summary['failed'] += $sourceSummary['failed'];
            $summary['matched_by_query_profiles'] += $sourceSummary['matched_by_query_profiles'];
            $summary['created_by_query_profiles'] += $sourceSummary['created_by_query_profiles'];
            $sourceResults[] = [
                'source' => $sourceSummary['source'],
                'source_name' => $jobLeadDiscoveryRunner->source($source)->sourceName(),
                'fetched' => $sourceSummary['fetched'],
                'created' => $sourceSummary['created'],
                'duplicates' => $sourceSummary['duplicates'],
                'skipped_not_matching_query' => $sourceSummary['skipped_not_matching_query'],
                'invalid' => $sourceSummary['invalid'],
                'failed' => $sourceSummary['failed'],
                'query_used' => $sourceSummary['query_used'],
                'query_profile_keys' => $sourceSummary['query_profile_keys'],
                'matched_by_query_profiles' => $sourceSummary['matched_by_query_profiles'],
                'created_by_query_profiles' => $sourceSummary['created_by_query_profiles'],
                'created_match_details' => $sourceSummary['created_match_details'],
                'discovery_batch_id' => $sourceSummary['discovery_batch_id'],
            ];
        }

        $jobLeadDiscoveryRunner->recordDiscoveryRun($request->user()->id, $summary['created'], $discoveryBatchId);

        $createdJobLeads = JobLead::query()
            ->where('user_id', $request->user()->id)
            ->where('discovery_batch_id', $discoveryBatchId)
            ->get(['id', 'lead_status', 'location', 'source_name', 'description_text', 'extracted_keywords']);
        $sourceObservability = $discoverySourceObservability->summarizeSources($createdJobLeads, $sourceResults);

        Log::info('job discovery completed', [
            'user_id' => $request->user()->id,
            'search_query' => $searchQuery,
            'query_profile_keys' => array_values(array_unique(array_merge(
                [],
                ...array_map(
                    fn (array $profile): array => [(string) ($profile['key'] ?? '')],
                    $queryProfiles,
                ),
            ))),
            'discovery_batch_id' => $discoveryBatchId,
            'summary' => $summary,
            'source_observability' => $sourceObservability,
            'created_match_details' => collect($sourceResults)
                ->flatMap(fn (array $sourceResult): array => $sourceResult['created_match_details'] ?? [])
                ->values()
                ->all(),
            'created_job_leads' => $createdJobLeads
                ->map(fn (JobLead $jobLead): array => [
                    'id' => $jobLead->id,
                    'location_classification' => $jobLead->locationClassification(),
                    'lead_status' => $jobLead->lead_status,
                    'source_name' => $jobLead->source_name,
                    'matched_query_profile_keys' => collect($sourceResults)
                        ->flatMap(fn (array $sourceResult): array => $sourceResult['created_match_details'] ?? [])
                        ->firstWhere('job_lead_id', (int) $jobLead->id)['query_profile_keys'] ?? [],
                ])
                ->all(),
        ]);

        return redirect()
            ->route('job-leads.index')
            ->with('discovery', $sourceObservability)
            ->with('discovery_batch_id', $discoveryBatchId)
            ->with('discovery_created_count', $summary['created'])
            ->with('discovery_search_query', $searchQuery)
            ->with('success', $this->discoverySuccessMessage($summary));
    }

    public function edit(JobLead $jobLead, Request $request): Response
    {
        $this->authorizeOwner($jobLead, $request);

        return Inertia::render('JobLeads/Edit', [
            'jobLead' => $this->jobLeadData($jobLead),
            'matchAnalysis' => $this->matchAnalysis($jobLead, $request->user()->id),
            'leadStatuses' => JobLead::leadStatuses(),
            'workModes' => JobLead::workModes(),
        ]);
    }

    public function update(UpdateJobLeadRequest $request, JobLead $jobLead): RedirectResponse
    {
        $validatedData = $request->validated();

        $jobLead->update(
            $this->jobLeadUpdatePayload($jobLead, $validatedData),
        );

        if ($request->boolean('stay_on_page')) {
            return back()->with('success', __('app.job_lead_edit.update_success'));
        }

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.job_lead_edit.update_success'));
    }

    public function destroy(JobLead $jobLead, Request $request): RedirectResponse
    {
        $this->authorizeOwner($jobLead, $request);
        $jobLead->delete();

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.job_lead_edit.delete_success'));
    }

    private function authorizeOwner(JobLead $jobLead, Request $request): void
    {
        abort_unless($request->user()->id === $jobLead->user_id, 403);
    }

    /**
     * @param array{
     *     fetched: int,
     *     created: int,
     *     duplicates: int,
     *     skipped_not_matching_query: int,
     *     invalid: int,
     *     failed: int
     * } $summary
     */
    private function discoverySuccessMessage(array $summary): string
    {
        if ($summary['created'] > 0) {
            return $summary['created'] === 1
                ? __('app.job_discovery.new_jobs_found_single')
                : __('app.job_discovery.new_jobs_found_multiple', [
                    'count' => $summary['created'],
                ]);
        }

        return __('app.job_discovery.no_new_jobs_found');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'analysis_readiness' => ['nullable', 'string', Rule::in([
                'all',
                ...JobLead::analysisReadinessOptions(),
            ])],
            'analysis_state' => ['nullable', 'string', Rule::in(JobLead::analysisStates())],
            'discovery_batch' => ['nullable', 'string', 'max:120'],
            'lead_status' => ['nullable', 'string', Rule::in(JobLead::leadStatuses())],
            'location_scope' => ['nullable', 'string', Rule::in(JobLead::locationScopes())],
            'search' => ['nullable', 'string', 'max:255'],
            'show_ignored' => ['nullable', 'boolean'],
            'work_mode' => ['nullable', 'string', Rule::in(JobLead::workModes())],
        ]);

        if (($filters['analysis_readiness'] ?? null) === 'all') {
            $filters['analysis_readiness'] = '';
        }

        $filters['show_ignored'] = $request->boolean('show_ignored');
        $filters['location_scope'] = $filters['location_scope'] ?? JobLead::LOCATION_SCOPE_BRAZIL;

        return $filters;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizedFilters(array $filters, ?UserProfile $userProfile): array
    {
        $isLatestDiscoveryView = ($filters['discovery_batch'] ?? null) === 'latest'
            && filled($userProfile?->last_discovery_batch_id);

        if (! $isLatestDiscoveryView) {
            $filters['is_latest_discovery_view'] = false;

            return $filters;
        }

        return [
            ...$filters,
            'analysis_readiness' => '',
            'analysis_state' => '',
            'lead_status' => '',
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
            'search' => '',
            'show_ignored' => true,
            'work_mode' => '',
            'is_latest_discovery_view' => true,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     * @return \Illuminate\Support\Collection<int, JobLead>
     */
    private function filterJobLeadsByLocationScope($jobLeads, string $locationScope)
    {
        if ($locationScope === JobLead::LOCATION_SCOPE_ALL) {
            return $jobLeads;
        }

        return $jobLeads->filter(
            fn (JobLead $jobLead): bool => $jobLead->locationClassification() !== JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL,
        )->values();
    }

    private function resolvedDiscoveryBatchId(?string $requestedDiscoveryBatch, ?UserProfile $userProfile): ?string
    {
        if (blank($requestedDiscoveryBatch)) {
            return null;
        }

        if ($requestedDiscoveryBatch === 'latest') {
            return $userProfile?->last_discovery_batch_id;
        }

        return $requestedDiscoveryBatch;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function workspaceLogFilters(array $filters): array
    {
        return [
            'analysis_readiness' => $filters['analysis_readiness'] ?? '',
            'analysis_state' => $filters['analysis_state'] ?? '',
            'discovery_batch' => $filters['discovery_batch'] ?? '',
            'lead_status' => $filters['lead_status'] ?? '',
            'location_scope' => $filters['location_scope'] ?? '',
            'search' => $filters['search'] ?? '',
            'show_ignored' => $filters['show_ignored'] ?? false,
            'work_mode' => $filters['work_mode'] ?? '',
            'is_latest_discovery_view' => $filters['is_latest_discovery_view'] ?? false,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     visible_count: int,
     *     hidden_ignored_count: int,
     *     hidden_international_count: int,
     *     total_count: int
     * }
     */
    private function matchedJobsVisibilitySummary(
        int $userId,
        array $filters,
        ?UserProfile $userProfile,
        int $visibleCount,
    ): array {
        $allLocationFilters = $this->filtersIncludingInternational(
            $this->filtersIncludingIgnored($filters),
        );
        $matchedJobs = $this->matchedJobs(
            $this->workspaceJobLeads($userId, $allLocationFilters, $userProfile),
            $userProfile,
        );
        $totalCount = count($matchedJobs);

        return [
            'visible_count' => $visibleCount,
            'hidden_ignored_count' => $this->hiddenIgnoredMatchedCount($matchedJobs, $filters),
            'hidden_international_count' => $this->hiddenInternationalMatchedCount($matchedJobs, $filters),
            'total_count' => $totalCount,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     latest_batch_id: string|null,
     *     latest_batch_total_count: int,
     *     matched_before_default_hiding_count: int,
     *     visible_matched_count: int,
     *     hidden_international_count: int,
     *     hidden_ignored_count: int,
     *     hidden_status_filter_count: int,
     *     hidden_analysis_readiness_filter_count: int,
     *     hidden_analysis_state_filter_count: int,
     *     hidden_work_mode_filter_count: int,
     *     hidden_search_text_filter_count: int,
     *     imported_not_matched_count: int,
     *     resume_ready: bool
     * }|null
     */
    private function latestDiscoveryMatchFunnel(
        int $userId,
        array $filters,
        ?UserProfile $userProfile,
    ): ?array {
        $latestBatchId = $userProfile?->last_discovery_batch_id;

        if (! is_string($latestBatchId) || trim($latestBatchId) === '') {
            return null;
        }

        $latestBatchJobLeads = JobLead::query()
            ->where('user_id', $userId)
            ->where('discovery_batch_id', $latestBatchId)
            ->orderByPriority()
            ->get();

        $resumeReady = $this->resumeReady($userProfile);

        if (! $resumeReady) {
            return [
                'latest_batch_id' => $latestBatchId,
                'latest_batch_total_count' => $latestBatchJobLeads->count(),
                'matched_before_default_hiding_count' => 0,
                'visible_matched_count' => 0,
                'hidden_international_count' => 0,
                'hidden_ignored_count' => 0,
                'hidden_status_filter_count' => 0,
                'hidden_analysis_readiness_filter_count' => 0,
                'hidden_analysis_state_filter_count' => 0,
                'hidden_work_mode_filter_count' => 0,
                'hidden_search_text_filter_count' => 0,
                'imported_not_matched_count' => $latestBatchJobLeads->count(),
                'resume_ready' => false,
            ];
        }

        $matchedLatestBatchJobLeads = $this->matchedJobLeads($latestBatchJobLeads, $userProfile);
        $remainingMatchedJobLeads = $matchedLatestBatchJobLeads;
        $hiddenIgnoredCount = 0;
        $hiddenInternationalCount = 0;
        $hiddenStatusFilterCount = 0;
        $hiddenAnalysisReadinessFilterCount = 0;
        $hiddenAnalysisStateFilterCount = 0;
        $hiddenWorkModeFilterCount = 0;
        $hiddenSearchTextFilterCount = 0;

        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsByIgnoredVisibility($jobLeads, $filters),
            $hiddenIgnoredCount,
        );
        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsByLocationScope($jobLeads, $filters['location_scope']),
            $hiddenInternationalCount,
        );
        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsByLeadStatus($jobLeads, $filters['lead_status'] ?? null),
            $hiddenStatusFilterCount,
        );
        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsByAnalysisReadiness($jobLeads, $filters['analysis_readiness'] ?? null),
            $hiddenAnalysisReadinessFilterCount,
        );
        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsByAnalysisState($jobLeads, $filters['analysis_state'] ?? null),
            $hiddenAnalysisStateFilterCount,
        );
        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsByWorkMode($jobLeads, $filters['work_mode'] ?? null),
            $hiddenWorkModeFilterCount,
        );
        $remainingMatchedJobLeads = $this->consumeFilteredJobLeads(
            $remainingMatchedJobLeads,
            fn ($jobLeads) => $this->filterJobLeadsBySearchText($jobLeads, $filters['search'] ?? null),
            $hiddenSearchTextFilterCount,
        );

        return [
            'latest_batch_id' => $latestBatchId,
            'latest_batch_total_count' => $latestBatchJobLeads->count(),
            'matched_before_default_hiding_count' => $matchedLatestBatchJobLeads->count(),
            'visible_matched_count' => $remainingMatchedJobLeads->count(),
            'hidden_international_count' => $hiddenInternationalCount,
            'hidden_ignored_count' => $hiddenIgnoredCount,
            'hidden_status_filter_count' => $hiddenStatusFilterCount,
            'hidden_analysis_readiness_filter_count' => $hiddenAnalysisReadinessFilterCount,
            'hidden_analysis_state_filter_count' => $hiddenAnalysisStateFilterCount,
            'hidden_work_mode_filter_count' => $hiddenWorkModeFilterCount,
            'hidden_search_text_filter_count' => $hiddenSearchTextFilterCount,
            'imported_not_matched_count' => $latestBatchJobLeads->count() - $matchedLatestBatchJobLeads->count(),
            'resume_ready' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultMatchedJobsFilters(): array
    {
        return [
            'analysis_readiness' => '',
            'analysis_state' => '',
            'discovery_batch' => '',
            'lead_status' => '',
            'location_scope' => JobLead::LOCATION_SCOPE_BRAZIL,
            'search' => '',
            'show_ignored' => false,
            'work_mode' => '',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function filtersIncludingIgnored(array $filters): array
    {
        if (($filters['show_ignored'] ?? false) || ($filters['lead_status'] ?? null) === JobLead::STATUS_IGNORED) {
            return $filters;
        }

        return [
            ...$filters,
            'show_ignored' => true,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function filtersIncludingInternational(array $filters): array
    {
        if (($filters['location_scope'] ?? JobLead::LOCATION_SCOPE_BRAZIL) === JobLead::LOCATION_SCOPE_ALL) {
            return $filters;
        }

        return [
            ...$filters,
            'location_scope' => JobLead::LOCATION_SCOPE_ALL,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Illuminate\Support\Collection<int, JobLead>
     */
    private function workspaceJobLeads(int $userId, array $filters, ?UserProfile $userProfile)
    {
        $jobLeads = JobLead::query()
            ->where('user_id', $userId)
            ->visibleInWorkspace($filters['show_ignored'], $filters['lead_status'] ?? null)
            ->discoveryBatch($this->resolvedDiscoveryBatchId($filters['discovery_batch'] ?? null, $userProfile))
            ->leadStatus($filters['lead_status'] ?? null)
            ->analysisReadiness($filters['analysis_readiness'] ?? null)
            ->workMode($filters['work_mode'] ?? null)
            ->analysisState($filters['analysis_state'] ?? null)
            ->orderByPriority()
            ->search($filters['search'] ?? null)
            ->get();

        return $this->filterJobLeadsByLocationScope($jobLeads, $filters['location_scope']);
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function matchedJobCount($jobLeads, ?UserProfile $userProfile): int
    {
        return count($this->matchedJobs($jobLeads, $userProfile));
    }

    /**
     * @param list<array<string, mixed>> $matchedJobs
     */
    private function hiddenIgnoredMatchedCount(array $matchedJobs, array $filters): int
    {
        if (($filters['show_ignored'] ?? false) || ($filters['lead_status'] ?? null) === JobLead::STATUS_IGNORED) {
            return 0;
        }

        return count(array_filter(
            $matchedJobs,
            fn (array $jobLead): bool => ($jobLead['lead_status'] ?? null) === JobLead::STATUS_IGNORED,
        ));
    }

    /**
     * @param list<array<string, mixed>> $matchedJobs
     */
    private function hiddenInternationalMatchedCount(array $matchedJobs, array $filters): int
    {
        if (($filters['location_scope'] ?? JobLead::LOCATION_SCOPE_BRAZIL) === JobLead::LOCATION_SCOPE_ALL) {
            return 0;
        }

        return count(array_filter(
            $matchedJobs,
            fn (array $jobLead): bool => ($jobLead['location_classification'] ?? null) === JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL,
        ));
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function matchedJobLeads($jobLeads, ?UserProfile $userProfile)
    {
        $matchedJobIds = collect($this->matchedJobs($jobLeads, $userProfile))
            ->pluck('id')
            ->all();

        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => in_array($jobLead->id, $matchedJobIds, true))
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     * @param callable(\Illuminate\Support\Collection<int, JobLead>): \Illuminate\Support\Collection<int, JobLead> $filter
     * @param-out int $hiddenCount
     * @return \Illuminate\Support\Collection<int, JobLead>
     */
    private function consumeFilteredJobLeads($jobLeads, callable $filter, int &$hiddenCount)
    {
        $filteredJobLeads = $filter($jobLeads)->values();
        $hiddenCount = $jobLeads->count() - $filteredJobLeads->count();

        return $filteredJobLeads;
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function filterJobLeadsByIgnoredVisibility($jobLeads, array $filters)
    {
        if (($filters['show_ignored'] ?? false) || ($filters['lead_status'] ?? null) === JobLead::STATUS_IGNORED) {
            return $jobLeads->values();
        }

        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status !== JobLead::STATUS_IGNORED)
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function filterJobLeadsByLeadStatus($jobLeads, ?string $leadStatus)
    {
        if (blank($leadStatus)) {
            return $jobLeads->values();
        }

        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status === $leadStatus)
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function filterJobLeadsByAnalysisReadiness($jobLeads, ?string $analysisReadiness)
    {
        if (blank($analysisReadiness)) {
            return $jobLeads->values();
        }

        if ($analysisReadiness === JobLead::ANALYSIS_READINESS_READY) {
            return $jobLeads
                ->filter(fn (JobLead $jobLead): bool => ! $jobLead->hasLimitedAnalysis())
                ->values();
        }

        if ($analysisReadiness !== JobLead::ANALYSIS_READINESS_NEEDS_DESCRIPTION) {
            return $jobLeads->values();
        }

        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->hasLimitedAnalysis())
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function filterJobLeadsByAnalysisState($jobLeads, ?string $analysisState)
    {
        if (blank($analysisState)) {
            return $jobLeads->values();
        }

        if ($analysisState === JobLead::ANALYSIS_STATE_ANALYZED) {
            return $jobLeads
                ->filter(fn (JobLead $jobLead): bool => ! $jobLead->hasLimitedAnalysis())
                ->values();
        }

        if ($analysisState !== JobLead::ANALYSIS_STATE_MISSING) {
            return $jobLeads->values();
        }

        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->hasLimitedAnalysis())
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function filterJobLeadsByWorkMode($jobLeads, ?string $workMode)
    {
        if (blank($workMode)) {
            return $jobLeads->values();
        }

        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => $jobLead->work_mode === $workMode)
            ->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function filterJobLeadsBySearchText($jobLeads, ?string $search)
    {
        if (blank($search)) {
            return $jobLeads->values();
        }

        $search = mb_strtolower((string) $search);

        return $jobLeads
            ->filter(function (JobLead $jobLead) use ($search): bool {
                return str_contains(mb_strtolower((string) $jobLead->company_name), $search)
                    || str_contains(mb_strtolower((string) $jobLead->job_title), $search);
            })
            ->values();
    }

    /**
     * @return array{active: int, ignored: int, applied: int}
     */
    private function leadStatusCounts(int $userId): array
    {
        $jobLeads = JobLead::query()
            ->where('user_id', $userId)
            ->get(['lead_status']);

        return [
            'active' => $jobLeads
                ->filter(fn (JobLead $jobLead): bool => ! in_array($jobLead->lead_status, [
                    JobLead::STATUS_IGNORED,
                    JobLead::STATUS_APPLIED,
                ], true))
                ->count(),
            'ignored' => $jobLeads
                ->where('lead_status', JobLead::STATUS_IGNORED)
                ->count(),
            'applied' => $jobLeads
                ->where('lead_status', JobLead::STATUS_APPLIED)
                ->count(),
        ];
    }

    private function duplicateJobLeadRedirect(JobLead $jobLead): RedirectResponse
    {
        return redirect()
            ->route('job-leads.edit', $jobLead)
            ->with('error', __('app.job_lead_create.duplicate_error'));
    }

    /**
     * @return array<string, mixed>
     */
    private function urlImportAttributes(ImportJobLeadFromUrlRequest $request): array
    {
        return [
            'source_type' => JobLead::SOURCE_TYPE_MANUAL,
            'source_name' => $this->nullableString($request->string('source_name')->value()),
            'company_name' => $this->nullableString($request->string('company_name')->value()),
            'fallback_company_name' => $this->importCompanyName($request->string('source_url')->value()),
            'job_title' => $this->nullableString($request->string('job_title')->value()),
            'default_job_title' => 'Imported job lead',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bulkImportAttributes(string $sourceUrl): array
    {
        return [
            'source_type' => JobLead::SOURCE_TYPE_BULK,
            'fallback_company_name' => $this->importCompanyName($sourceUrl),
            'default_job_title' => 'Imported job lead',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postImportAttributes(ImportPostJobLeadRequest $request): array
    {
        $sourcePlatform = $request->input('source_platform');
        $sourcePostUrl = $request->input('source_post_url');
        $sourceAuthor = $request->input('source_author');
        $sourceContextText = $request->input('source_context_text');
        $companyName = $request->input('company_name');
        $jobTitle = $request->input('job_title');

        return [
            'source_type' => JobLead::SOURCE_TYPE_EXTENSION,
            'source_platform' => is_string($sourcePlatform) ? $this->nullableString($sourcePlatform) : null,
            'source_post_url' => is_string($sourcePostUrl) ? $this->nullableString($sourcePostUrl) : null,
            'source_author' => is_string($sourceAuthor) ? $this->nullableString($sourceAuthor) : null,
            'source_context_text' => is_string($sourceContextText) ? $this->nullableString($sourceContextText) : null,
            'description_text' => is_string($sourceContextText) ? $this->nullableString($sourceContextText) : null,
            'company_name' => is_string($companyName) ? $this->nullableString($companyName) : null,
            'job_title' => is_string($jobTitle) ? $this->nullableString($jobTitle) : null,
        ];
    }

    /**
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function storeImportAttributes(array $validatedData): array
    {
        return [
            'source_type' => JobLead::SOURCE_TYPE_MANUAL,
            'source_name' => $validatedData['source_name'] ?? null,
            'company_name' => $validatedData['company_name'] ?? null,
            'job_title' => $validatedData['job_title'] ?? null,
            'default_job_title' => 'Imported job',
            'location' => $validatedData['location'] ?? null,
            'work_mode' => $validatedData['work_mode'] ?? null,
            'salary_range' => $validatedData['salary_range'] ?? null,
            'description_excerpt' => $validatedData['description_excerpt'] ?? null,
            'description_text' => $validatedData['description_text'] ?? null,
            'relevance_score' => $validatedData['relevance_score'] ?? null,
            'lead_status' => $validatedData['lead_status'] ?? JobLead::STATUS_SAVED,
            'discovered_at' => $validatedData['discovered_at'] ?? today()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobLeadData(JobLead $jobLead): array
    {
        return [
            'id' => $jobLead->id,
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_type' => $jobLead->source_type,
            'source_platform' => $jobLead->source_platform,
            'source_post_url' => $jobLead->source_post_url,
            'source_author' => $jobLead->source_author,
            'source_context_text' => $jobLead->source_context_text,
            'source_url' => $jobLead->source_url,
            'normalized_source_url' => $jobLead->normalized_source_url,
            'source_host' => $jobLead->source_host,
            'location' => $jobLead->location,
            'location_classification' => $jobLead->locationClassification(),
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => $jobLead->description_text,
            'extracted_keywords' => $jobLead->extracted_keywords ?? [],
            'ats_hints' => $jobLead->ats_hints ?? [],
            'has_limited_analysis' => $jobLead->hasLimitedAnalysis(),
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => $jobLead->discovered_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matchAnalysis(JobLead $jobLead, int $userId): array
    {
        $userProfile = $this->userProfile($userId);

        if ($userProfile === null) {
            return [
                'state' => 'missing_profile',
                'matched_keywords' => [],
                'missing_keywords' => [],
                'match_summary' => __('app.job_lead_edit.match_missing_profile'),
            ];
        }

        if (blank($jobLead->description_text) || ($jobLead->extracted_keywords ?? []) === []) {
            return [
                'state' => 'missing_job_analysis',
                'matched_keywords' => [],
                'missing_keywords' => [],
                'match_summary' => __('app.job_lead_edit.match_missing_job_analysis'),
            ];
        }

        return [
            'state' => 'ready',
            ...$this->analyzeMatch($jobLead, $userProfile),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     * @return list<array<string, mixed>>
     */
    private function matchedJobs($jobLeads, ?UserProfile $userProfile): array
    {
        return $this->jobCards($jobLeads, $userProfile, true, $this->detectedResumeSkills($userProfile));
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     */
    private function leadsMissingAnalysisCount($jobLeads): int
    {
        return $jobLeads
            ->filter(fn (JobLead $jobLead): bool => ($jobLead->extracted_keywords ?? []) === [])
            ->count();
    }

    /**
     * @param \Illuminate\Support\Collection<int, JobLead> $jobLeads
     * @return list<array<string, mixed>>
     */
    private function jobCards($jobLeads, ?UserProfile $userProfile, bool $matchedOnly, array $detectedResumeSkills): array
    {
        $resumeReady = $this->resumeReady($userProfile);

        if ($matchedOnly && ! $resumeReady) {
            return [];
        }

        $rankedJobCards = [];
        $position = 0;

        foreach ($jobLeads as $jobLead) {
            $jobKeywords = $jobLead->extracted_keywords ?? [];
            $analysis = $resumeReady && ($jobLead->extracted_keywords ?? []) !== []
                ? $this->analyzeMatch($jobLead, $userProfile)
                : [
                    'matched_keywords' => [],
                    'missing_keywords' => [],
                    'match_summary' => __('app.matched_jobs.match_summary_pending'),
                ];

            if ($matchedOnly && $analysis['matched_keywords'] === []) {
                continue;
            }

            $preferenceFit = $this->preferenceFit($jobLead, $userProfile);
            $whyThisJob = $this->whyThisJob($jobLead, $analysis, $preferenceFit);
            $resumeSkillOverlapCount = $this->resumeSkillOverlapCount($jobKeywords, $detectedResumeSkills);

            $rankedJobCards[] = [
                'card' => [
                    'id' => $jobLead->id,
                    'company_name' => $jobLead->company_name,
                    'job_title' => $jobLead->job_title,
                    'lead_status' => $jobLead->lead_status,
                    'source_name' => $jobLead->source_name,
                    'source_type' => $jobLead->source_type,
                    'source_platform' => $jobLead->source_platform,
                    'source_post_url' => $jobLead->source_post_url,
                    'source_author' => $jobLead->source_author,
                    'source_url' => $jobLead->source_url,
                    'source_host' => $jobLead->source_host,
                    'location' => $jobLead->location,
                    'location_classification' => $jobLead->locationClassification(),
                    'work_mode' => $jobLead->work_mode,
                    'extracted_keywords' => $jobKeywords,
                    'has_limited_analysis' => $jobLead->hasLimitedAnalysis(),
                    'resume_skills_used' => $detectedResumeSkills,
                    'job_keywords_used' => $jobKeywords,
                    'matched_keywords' => $analysis['matched_keywords'],
                    'missing_keywords' => $analysis['missing_keywords'],
                    'match_summary' => $analysis['match_summary'],
                    'can_explain_match' => $detectedResumeSkills !== [] && $jobKeywords !== [],
                    'ats_hint' => $jobLead->ats_hints[0] ?? null,
                    'preference_fit' => $preferenceFit,
                    'why_this_job' => $whyThisJob,
                ],
                'is_active_lead' => $jobLead->lead_status !== JobLead::STATUS_APPLIED
                    && $jobLead->lead_status !== JobLead::STATUS_IGNORED,
                'has_job_analysis' => $jobKeywords !== [],
                'resume_skill_overlap_count' => $resumeSkillOverlapCount,
                'matched_keyword_count' => count($analysis['matched_keywords']),
                'missing_keyword_count' => count($analysis['missing_keywords']),
                'preference_fit_rank' => $this->preferenceFitRank($preferenceFit),
                'relevance_score' => $jobLead->relevance_score,
                'created_at_timestamp' => $jobLead->created_at?->getTimestamp() ?? 0,
                'position' => $position,
            ];

            $position++;
        }

        return $this->rankedJobCards($rankedJobCards, $matchedOnly);
    }

    /**
     * @param list<array{
     *     card: array<string, mixed>,
     *     is_active_lead: bool,
     *     has_job_analysis: bool,
     *     resume_skill_overlap_count: int,
     *     matched_keyword_count: int,
     *     missing_keyword_count: int,
     *     preference_fit_rank: int,
     *     relevance_score: int|null,
     *     created_at_timestamp: int,
     *     position: int
     * }> $rankedJobCards
     * @return list<array<string, mixed>>
     */
    private function rankedJobCards(array $rankedJobCards, bool $matchedOnly): array
    {
        usort(
            $rankedJobCards,
            fn (array $left, array $right): int => $matchedOnly
                ? $this->compareMatchedJobCards($left, $right)
                : $this->compareWorkspaceJobCards($left, $right),
        );

        return array_map(
            fn (array $rankedJobCard): array => $rankedJobCard['card'],
            $rankedJobCards,
        );
    }

    /**
     * @param array{
     *     is_active_lead: bool,
     *     has_job_analysis: bool,
     *     resume_skill_overlap_count: int,
     *     matched_keyword_count: int,
     *     missing_keyword_count: int,
     *     preference_fit_rank: int,
     *     relevance_score: int|null,
     *     created_at_timestamp: int,
     *     position: int
     * } $left
     * @param array{
     *     is_active_lead: bool,
     *     has_job_analysis: bool,
     *     matched_keyword_count: int,
     *     missing_keyword_count: int,
     *     preference_fit_rank: int,
     *     relevance_score: int|null,
     *     created_at_timestamp: int,
     *     position: int
     * } $right
     */
    private function compareMatchedJobCards(array $left, array $right): int
    {
        return $this->compareDesc((int) $left['has_job_analysis'], (int) $right['has_job_analysis'])
            ?: $this->compareDesc($left['matched_keyword_count'], $right['matched_keyword_count'])
            ?: $this->compareAsc($left['missing_keyword_count'], $right['missing_keyword_count'])
            ?: $this->compareDesc($left['relevance_score'] ?? -1, $right['relevance_score'] ?? -1)
            ?: $this->compareAsc($left['position'], $right['position']);
    }

    /**
     * @param array{
     *     is_active_lead: bool,
     *     has_job_analysis: bool,
     *     matched_keyword_count: int,
     *     missing_keyword_count: int,
     *     preference_fit_rank: int,
     *     relevance_score: int|null,
     *     created_at_timestamp: int,
     *     position: int
     * } $left
     * @param array{
     *     is_active_lead: bool,
     *     has_job_analysis: bool,
     *     matched_keyword_count: int,
     *     missing_keyword_count: int,
     *     preference_fit_rank: int,
     *     relevance_score: int|null,
     *     created_at_timestamp: int,
     *     position: int
     * } $right
     */
    private function compareWorkspaceJobCards(array $left, array $right): int
    {
        return $this->compareDesc((int) $left['is_active_lead'], (int) $right['is_active_lead'])
            ?: $this->compareDesc((int) $left['has_job_analysis'], (int) $right['has_job_analysis'])
            ?: $this->compareDesc($left['resume_skill_overlap_count'], $right['resume_skill_overlap_count'])
            ?: $this->compareDesc($left['matched_keyword_count'], $right['matched_keyword_count'])
            ?: $this->compareAsc($left['missing_keyword_count'], $right['missing_keyword_count'])
            ?: $this->compareDesc($left['preference_fit_rank'], $right['preference_fit_rank'])
            ?: $this->compareDesc($left['relevance_score'] ?? -1, $right['relevance_score'] ?? -1)
            ?: $this->compareDesc($left['created_at_timestamp'], $right['created_at_timestamp'])
            ?: $this->compareAsc($left['position'], $right['position']);
    }

    private function compareDesc(int $left, int $right): int
    {
        return $right <=> $left;
    }

    private function compareAsc(int $left, int $right): int
    {
        return $left <=> $right;
    }

    /**
     * @param list<string> $jobKeywords
     * @param list<string> $detectedResumeSkills
     */
    private function resumeSkillOverlapCount(array $jobKeywords, array $detectedResumeSkills): int
    {
        if ($jobKeywords === [] || $detectedResumeSkills === []) {
            return 0;
        }

        $overlapCount = 0;

        foreach (array_values(array_unique($detectedResumeSkills)) as $resumeSkill) {
            foreach ($jobKeywords as $jobKeyword) {
                if ($this->containsPreferenceMatch($jobKeyword, [$resumeSkill])) {
                    $overlapCount++;

                    continue 2;
                }
            }
        }

        return $overlapCount;
    }

    /**
     * @param array{status: string, matched: list<string>, mismatched: list<string>}|null $preferenceFit
     */
    private function preferenceFitRank(?array $preferenceFit): int
    {
        return match ($preferenceFit['status'] ?? null) {
            'match' => 2,
            'partial' => 1,
            'mismatch' => 0,
            default => -1,
        };
    }

    /**
     * @param array{matched_keywords: list<string>, missing_keywords: list<string>, match_summary: string} $analysis
     * @param array{status: string, matched: list<string>, mismatched: list<string>}|null $preferenceFit
     * @return array{matched_keywords: list<string>, missing_keywords: list<string>, preference_summary: string|null}|null
     */
    private function whyThisJob(JobLead $jobLead, array $analysis, ?array $preferenceFit): ?array
    {
        $matchedKeywords = array_slice($analysis['matched_keywords'], 0, 3);
        $missingKeywords = array_slice($analysis['missing_keywords'], 0, 3);

        $preferenceSummary = in_array($preferenceFit['status'] ?? null, ['match', 'partial'], true)
            ? $preferenceFit['status']
            : null;

        if ($matchedKeywords === [] && $missingKeywords === [] && $preferenceSummary === null) {
            return null;
        }

        return [
            'matched_keywords' => $matchedKeywords,
            'missing_keywords' => $missingKeywords,
            'preference_summary' => $preferenceSummary,
        ];
    }

    /**
     * @return array{matched_keywords: list<string>, missing_keywords: list<string>, match_summary: string}
     */
    private function analyzeMatch(JobLead $jobLead, UserProfile $userProfile): array
    {
        return app(JobLeadMatchAnalyzer::class)->analyze(
            $jobLead->extracted_keywords ?? [],
            $userProfile->base_resume_text,
            $userProfile->core_skills ?? [],
        );
    }

    /**
     * @return array{status: string, matched: list<string>, mismatched: list<string>}|null
     */
    private function preferenceFit(JobLead $jobLead, ?UserProfile $userProfile): ?array
    {
        if ($userProfile === null) {
            return null;
        }

        $targetRoles = $this->normalizedPreferenceValues($userProfile->target_roles ?? []);
        $preferredLocations = $this->normalizedPreferenceValues($userProfile->preferred_locations ?? []);
        $preferredWorkModes = $this->normalizedPreferenceValues($userProfile->preferred_work_modes ?? []);

        if ($targetRoles === [] && $preferredLocations === [] && $preferredWorkModes === []) {
            return null;
        }

        $matched = [];
        $mismatched = [];
        $compared = 0;

        $jobTitle = $this->comparableJobTitle($jobLead);

        if ($jobTitle !== null && $targetRoles !== []) {
            $compared++;

            if ($this->containsPreferenceMatch($jobTitle, $targetRoles)) {
                $matched[] = 'target_role';
            } else {
                $mismatched[] = 'target_role';
            }
        }

        $location = $this->normalizedComparableValue($jobLead->location);

        if ($location !== null && $preferredLocations !== []) {
            $compared++;

            if ($this->containsPreferenceMatch($location, $preferredLocations)) {
                $matched[] = 'location';
            } else {
                $mismatched[] = 'location';
            }
        }

        $workMode = $this->normalizedComparableValue($jobLead->work_mode);

        if ($workMode !== null && $preferredWorkModes !== []) {
            $compared++;

            if (in_array($workMode, $preferredWorkModes, true)) {
                $matched[] = 'work_mode';
            } else {
                $mismatched[] = 'work_mode';
            }
        }

        if ($compared === 0) {
            return null;
        }

        if (count($matched) === $compared) {
            $status = 'match';
        } elseif ($matched === []) {
            $status = 'mismatch';
        } else {
            $status = 'partial';
        }

        return [
            'status' => $status,
            'matched' => $matched,
            'mismatched' => $mismatched,
        ];
    }

    private function userProfile(int $userId): ?UserProfile
    {
        return UserProfile::query()
            ->where('user_id', $userId)
            ->first();
    }

    private function resumeReady(?UserProfile $userProfile): bool
    {
        if ($userProfile === null) {
            return false;
        }

        if (filled($userProfile->base_resume_text)) {
            return true;
        }

        return ($userProfile->core_skills ?? []) !== [];
    }

    private function resumeNeedsTextInput(?UserProfile $userProfile): bool
    {
        if ($userProfile === null) {
            return false;
        }

        if ($this->resumeReady($userProfile)) {
            return false;
        }

        return $userProfile->resume_file_path !== null;
    }

    /**
     * @return array{last_discovered_at_human: string, last_discovered_new_count: int, last_discovery_batch_id: string|null}|null
     */
    private function discoveryStatus(?UserProfile $userProfile): ?array
    {
        if ($userProfile === null || $userProfile->last_discovered_at === null) {
            return null;
        }

        return [
            'last_discovered_at_human' => $userProfile->last_discovered_at
                ->locale(app()->getLocale())
                ->diffForHumans(),
            'last_discovered_new_count' => (int) ($userProfile->last_discovered_new_count ?? 0),
            'last_discovery_batch_id' => $userProfile->last_discovery_batch_id,
        ];
    }

    /**
     * @return list<string>
     */
    private function detectedResumeSkills(?UserProfile $userProfile): array
    {
        if ($userProfile === null) {
            return [];
        }

        return app(ResumeDiscoverySignalBuilder::class)->detectedSkills(
            $userProfile->base_resume_text,
            $userProfile->core_skills ?? [],
        );
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private function normalizedPreferenceValues(array $values): array
    {
        $normalizedValues = [];

        foreach ($values as $value) {
            $normalizedValue = $this->normalizedComparableValue($value);

            if ($normalizedValue === null) {
                continue;
            }

            $normalizedValues[] = $normalizedValue;
        }

        return array_values(array_unique($normalizedValues));
    }

    private function normalizedComparableValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = Str::of($value)
            ->lower()
            ->squish()
            ->value();

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    private function comparableJobTitle(JobLead $jobLead): ?string
    {
        $jobTitle = $this->normalizedComparableValue($jobLead->job_title);

        if ($jobTitle === null) {
            return null;
        }

        if (in_array($jobTitle, [
            $this->normalizedComparableValue($this->fallbackJobTitle()),
            $this->normalizedComparableValue('Imported job lead'),
        ], true)) {
            return null;
        }

        return $jobTitle;
    }

    /**
     * @param list<string> $preferences
     */
    private function containsPreferenceMatch(string $value, array $preferences): bool
    {
        foreach ($preferences as $preference) {
            if (str_contains($value, $preference) || str_contains($preference, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function jobLeadPayload(array $validatedData, ?int $userId = null, bool $syncAnalysis = false): array
    {
        $sourceUrl = $validatedData['source_url'] ?? null;

        $payload = [
            ...$validatedData,
            ...$this->canonicalSourceMetadata(is_string($sourceUrl) ? $sourceUrl : null),
            'company_name' => $this->nullableDescriptionText($validatedData['company_name'] ?? null)
                ?? $this->fallbackCompanyName(is_string($sourceUrl) ? $sourceUrl : null),
            'job_title' => $this->nullableDescriptionText($validatedData['job_title'] ?? null)
                ?? $this->fallbackJobTitle(),
            'lead_status' => $validatedData['lead_status'] ?? JobLead::STATUS_SAVED,
            'discovered_at' => $validatedData['discovered_at'] ?? today()->toDateString(),
        ];

        if ($syncAnalysis) {
            $descriptionText = $this->nullableDescriptionText($validatedData['description_text'] ?? null);
            $analysis = app(JobLeadKeywordExtractor::class)->analyze($descriptionText);

            $payload = [
                ...$payload,
                'description_text' => $descriptionText,
                'extracted_keywords' => $analysis['extracted_keywords'],
                'ats_hints' => $analysis['ats_hints'],
            ];
        }

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function jobLeadUpdatePayload(JobLead $jobLead, array $validatedData): array
    {
        return $this->jobLeadPayload([
            'company_name' => $validatedData['company_name'] ?? $jobLead->company_name,
            'job_title' => $validatedData['job_title'] ?? $jobLead->job_title,
            'source_name' => array_key_exists('source_name', $validatedData)
                ? $validatedData['source_name']
                : $jobLead->source_name,
            'source_url' => $validatedData['source_url'] ?? $jobLead->source_url,
            'location' => array_key_exists('location', $validatedData)
                ? $validatedData['location']
                : $jobLead->location,
            'work_mode' => array_key_exists('work_mode', $validatedData)
                ? $validatedData['work_mode']
                : $jobLead->work_mode,
            'salary_range' => array_key_exists('salary_range', $validatedData)
                ? $validatedData['salary_range']
                : $jobLead->salary_range,
            'description_excerpt' => array_key_exists('description_excerpt', $validatedData)
                ? $validatedData['description_excerpt']
                : $jobLead->description_excerpt,
            'description_text' => array_key_exists('description_text', $validatedData)
                ? $validatedData['description_text']
                : $jobLead->description_text,
            'relevance_score' => array_key_exists('relevance_score', $validatedData)
                ? $validatedData['relevance_score']
                : $jobLead->relevance_score,
            'lead_status' => $validatedData['lead_status'] ?? $jobLead->lead_status,
            'discovered_at' => array_key_exists('discovered_at', $validatedData)
                ? $validatedData['discovered_at']
                : $jobLead->discovered_at?->toDateString(),
        ], syncAnalysis: array_key_exists('description_text', $validatedData));
    }

    /**
     * @return array{normalized_source_url: string|null, source_host: string|null}
     */
    private function canonicalSourceMetadata(?string $sourceUrl): array
    {
        $normalizedSourceUrl = $this->normalizedSourceUrl($sourceUrl);

        if ($normalizedSourceUrl === null) {
            return [
                'normalized_source_url' => null,
                'source_host' => null,
            ];
        }

        return [
            'normalized_source_url' => $normalizedSourceUrl,
            'source_host' => $this->sourceHost($normalizedSourceUrl),
        ];
    }

    private function normalizedSourceUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null || trim($sourceUrl) === '') {
            return null;
        }

        $parts = parse_url($sourceUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ($scheme === '' || $host === '') {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/+#', '/', $path);
        $path = is_string($path) ? $path : '/';

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function sourceHost(string $normalizedSourceUrl): ?string
    {
        $host = parse_url($normalizedSourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host)) ?? strtolower($host);
    }

    /**
     * @return array{extracted_keywords: list<string>, ats_hints: list<string>}
     */
    private function missingJobAnalysisPayload(): array
    {
        return app(JobLeadKeywordExtractor::class)->analyze(null);
    }

    /**
     * @return list<string>
     */
    private function bulkImportUrls(string $sourceUrls): array
    {
        $items = preg_split('/[\s,]+/', trim($sourceUrls)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $item): ?string => $this->nullableString($item),
            $items,
        )));
    }

    private function isImportableUrl(string $sourceUrl): bool
    {
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($sourceUrl, PHP_URL_SCHEME);

        return in_array(strtolower((string) $scheme), ['http', 'https'], true);
    }

    private function nullableString(string $value): ?string
    {
        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return null;
        }

        return $trimmedValue;
    }

    private function nullableDescriptionText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return $this->nullableString($value);
    }

    private function fallbackCompanyName(?string $sourceUrl): string
    {
        if ($sourceUrl === null || $sourceUrl === '') {
            return 'Imported company';
        }

        $host = parse_url($sourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'Imported company';
        }

        $segments = explode('.', preg_replace('/^www\./', '', strtolower($host)) ?? strtolower($host));
        $primarySegment = $segments[0] ?? '';

        if (in_array($primarySegment, ['jobs', 'careers', 'boards', 'apply'], true) && count($segments) >= 2) {
            $primarySegment = $segments[count($segments) - 2];
        }

        $words = preg_split('/[-_]+/', $primarySegment) ?: [];
        $words = array_filter(array_map(
            fn (string $word): ?string => $this->nullableString($word),
            $words,
        ));

        if ($words === []) {
            return 'Imported company';
        }

        return implode(' ', array_map(
            fn (string $word): string => ucfirst(strtolower($word)),
            $words,
        ));
    }

    private function fallbackJobTitle(): string
    {
        return 'Imported job';
    }

    private function importCompanyName(string $sourceUrl): string
    {
        $host = parse_url($sourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'Imported company';
        }

        return $host;
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationData(Application $application): array
    {
        return [
            'id' => $application->id,
            'company_name' => $application->company_name,
            'job_title' => $application->job_title,
            'status' => $application->status,
            'applied_at' => $application->applied_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(int $userId): array
    {
        $counts = array_fill_keys(Application::statuses(), 0);

        $groupedCounts = Application::query()
            ->where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($groupedCounts as $status => $total) {
            $counts[$status] = (int) $total;
        }

        return $counts;
    }
}
