<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Http\Requests\BulkImportJobLeadsRequest;
use App\Http\Requests\ImportJobLeadFromUrlRequest;
use App\Http\Requests\StoreJobLeadRequest;
use App\Http\Requests\UpdateJobLeadRequest;
use App\Models\JobLead;
use App\Models\UserProfile;
use App\Services\JobDiscovery\JobLeadDiscoveryRunner;
use App\Services\JobLeadKeywordExtractor;
use App\Services\JobLeadImportService;
use App\Services\JobLeadMatchAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $filters = $this->filters($request);

        $userProfile = $this->userProfile($request->user()->id);
        $matchedOnly = $request->routeIs('matched-jobs.index');
        $detectedResumeSkills = $this->detectedResumeSkills($userProfile);
        $jobLeads = JobLead::query()
            ->where('user_id', $request->user()->id)
            ->visibleInWorkspace($filters['show_ignored'], $filters['lead_status'] ?? null)
            ->leadStatus($filters['lead_status'] ?? null)
            ->analysisReadiness($filters['analysis_readiness'] ?? null)
            ->workMode($filters['work_mode'] ?? null)
            ->analysisState($filters['analysis_state'] ?? null)
            ->orderByPriority()
            ->search($filters['search'] ?? null)
            ->get();

        $matchedJobs = $this->jobCards($jobLeads, $userProfile, $matchedOnly, $detectedResumeSkills);

        return Inertia::render('JobLeads/Index', [
            'analysisStates' => JobLead::analysisStates(),
            'analysisReadinessOptions' => JobLead::analysisReadinessOptions(),
            'discoveryStatus' => $this->discoveryStatus($userProfile),
            'detectedResumeSkills' => $detectedResumeSkills,
            'filters' => [
                'analysis_readiness' => $filters['analysis_readiness'] ?? '',
                'analysis_state' => $filters['analysis_state'] ?? '',
                'lead_status' => $filters['lead_status'] ?? '',
                'search' => $filters['search'] ?? '',
                'show_ignored' => $filters['show_ignored'],
                'work_mode' => $filters['work_mode'] ?? '',
            ],
            'hasResumeProfile' => $userProfile !== null,
            'leadStatuses' => JobLead::leadStatuses(),
            'leadStatusCounts' => $this->leadStatusCounts($request->user()->id),
            'leadsMissingAnalysisCount' => $this->leadsMissingAnalysisCount($jobLeads),
            'matchedOnly' => $matchedOnly,
            'resumeReady' => $this->resumeReady($userProfile),
            'resumeNeedsTextInput' => $this->resumeNeedsTextInput($userProfile),
            'matchedJobs' => $matchedJobs,
            'workModes' => JobLead::workModes(),
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $userProfile = $this->userProfile($user->id);
        $matchedJobsCount = count($this->matchedJobs(
            JobLead::query()->where('user_id', $user->id)->orderByPriority()->get(),
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

    public function discover(Request $request, JobLeadDiscoveryRunner $jobLeadDiscoveryRunner): RedirectResponse
    {
        $summary = [
            'fetched' => 0,
            'created' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'failed' => 0,
        ];
        $sourceResults = [];

        foreach ($jobLeadDiscoveryRunner->supportedSources() as $source) {
            try {
                $sourceSummary = $jobLeadDiscoveryRunner->discoverForUser($request->user()->id, $source);
            } catch (Throwable) {
                $summary['failed']++;
                $sourceResults[] = [
                    'source' => $source,
                    'fetched' => 0,
                    'created' => 0,
                    'duplicates' => 0,
                    'invalid' => 0,
                    'failed' => 1,
                ];

                continue;
            }

            $summary['fetched'] += $sourceSummary['fetched'];
            $summary['created'] += $sourceSummary['created'];
            $summary['duplicates'] += $sourceSummary['duplicates'];
            $summary['invalid'] += $sourceSummary['invalid'];
            $summary['failed'] += $sourceSummary['failed'];
            $sourceResults[] = [
                'source' => $sourceSummary['source'],
                'fetched' => $sourceSummary['fetched'],
                'created' => $sourceSummary['created'],
                'duplicates' => $sourceSummary['duplicates'],
                'invalid' => $sourceSummary['invalid'],
                'failed' => $sourceSummary['failed'],
            ];
        }

        $jobLeadDiscoveryRunner->recordDiscoveryRun($request->user()->id, $summary['created']);

        return redirect()
            ->route('job-leads.index')
            ->with('discovery', $sourceResults)
            ->with('success', __('app.job_discovery.summary', $summary));
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
            'lead_status' => ['nullable', 'string', Rule::in(JobLead::leadStatuses())],
            'search' => ['nullable', 'string', 'max:255'],
            'show_ignored' => ['nullable', 'boolean'],
            'work_mode' => ['nullable', 'string', Rule::in(JobLead::workModes())],
        ]);

        if (($filters['analysis_readiness'] ?? null) === 'all') {
            $filters['analysis_readiness'] = '';
        }

        $filters['show_ignored'] = $request->boolean('show_ignored');

        return $filters;
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
            'fallback_company_name' => $this->importCompanyName($sourceUrl),
            'default_job_title' => 'Imported job lead',
        ];
    }

    /**
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function storeImportAttributes(array $validatedData): array
    {
        return [
            'source_name' => $validatedData['source_name'] ?? null,
            'company_name' => $validatedData['company_name'] ?? null,
            'job_title' => $validatedData['job_title'] ?? null,
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
            'source_url' => $jobLead->source_url,
            'normalized_source_url' => $jobLead->normalized_source_url,
            'source_host' => $jobLead->source_host,
            'location' => $jobLead->location,
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

            $rankedJobCards[] = [
                'card' => [
                    'id' => $jobLead->id,
                    'company_name' => $jobLead->company_name,
                    'job_title' => $jobLead->job_title,
                    'lead_status' => $jobLead->lead_status,
                    'source_name' => $jobLead->source_name,
                    'source_url' => $jobLead->source_url,
                    'source_host' => $jobLead->source_host,
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
                ],
                'is_active_lead' => $jobLead->lead_status !== JobLead::STATUS_APPLIED
                    && $jobLead->lead_status !== JobLead::STATUS_IGNORED,
                'has_job_analysis' => $jobKeywords !== [],
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
            ?: $this->compareDesc($left['matched_keyword_count'], $right['matched_keyword_count'])
            ?: $this->compareAsc($left['missing_keyword_count'], $right['missing_keyword_count'])
            ?: $this->compareDesc($left['relevance_score'] ?? -1, $right['relevance_score'] ?? -1)
            ?: $this->compareDesc($left['preference_fit_rank'], $right['preference_fit_rank'])
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
     * @return array{last_discovered_at_human: string, last_discovered_new_count: int}|null
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

        $skills = [];

        foreach ($userProfile->core_skills ?? [] as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $normalizedSkill = $this->nullableDescriptionText($skill);

            if ($normalizedSkill === null) {
                continue;
            }

            $skills[] = mb_strtolower($normalizedSkill);
        }

        if (filled($userProfile->base_resume_text)) {
            $analysis = app(JobLeadKeywordExtractor::class)->analyze($userProfile->base_resume_text);

            foreach ($analysis['extracted_keywords'] as $keyword) {
                if (! is_string($keyword)) {
                    continue;
                }

                $skills[] = $keyword;
            }
        }

        return array_slice(array_values(array_unique($skills)), 0, 10);
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
