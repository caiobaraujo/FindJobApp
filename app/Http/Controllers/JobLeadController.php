<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Http\Requests\ImportJobLeadFromUrlRequest;
use App\Http\Requests\StoreJobLeadRequest;
use App\Http\Requests\UpdateJobLeadRequest;
use App\Models\JobLead;
use App\Models\UserProfile;
use App\Services\JobLeadKeywordExtractor;
use App\Services\JobLeadMatchAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobLeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $userProfile = $this->userProfile($request->user()->id);
        $matchedOnly = $request->routeIs('matched-jobs.index');
        $detectedResumeSkills = $this->detectedResumeSkills($userProfile);
        $jobLeads = JobLead::query()
            ->where('user_id', $request->user()->id)
            ->orderByPriority()
            ->search($filters['search'] ?? null)
            ->get();

        $matchedJobs = $this->jobCards($jobLeads, $userProfile, $matchedOnly, $detectedResumeSkills);

        return Inertia::render('JobLeads/Index', [
            ...$this->sharedPageProps(),
            'filters' => [
                'search' => $filters['search'] ?? '',
            ],
            'detectedResumeSkills' => $detectedResumeSkills,
            'hasResumeProfile' => $userProfile !== null,
            'matchedOnly' => $matchedOnly,
            'resumeReady' => $this->resumeReady($userProfile),
            'resumeNeedsTextInput' => $this->resumeNeedsTextInput($userProfile),
            'matchedJobs' => $matchedJobs,
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
            ...$this->sharedPageProps(),
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
            ...$this->sharedPageProps(),
            'leadStatuses' => JobLead::leadStatuses(),
            'workModes' => JobLead::workModes(),
        ]);
    }

    public function store(StoreJobLeadRequest $request): RedirectResponse
    {
        JobLead::query()->create(
            $this->jobLeadPayload(
                $request->validated(),
                $request->user()->id,
            ),
        );

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.job_lead_edit.create_success'));
    }

    public function importFromUrl(ImportJobLeadFromUrlRequest $request): RedirectResponse
    {
        JobLead::query()->create($this->importedJobLeadData($request));

        return redirect()
            ->route('job-leads.index')
            ->with('success', __('app.matched_jobs.import_success'));
    }

    public function edit(JobLead $jobLead, Request $request): Response
    {
        $this->authorizeOwner($jobLead, $request);

        return Inertia::render('JobLeads/Edit', [
            ...$this->sharedPageProps(),
            'jobLead' => $this->jobLeadData($jobLead),
            'matchAnalysis' => $this->matchAnalysis($jobLead, $request->user()->id),
            'leadStatuses' => JobLead::leadStatuses(),
            'workModes' => JobLead::workModes(),
        ]);
    }

    public function update(UpdateJobLeadRequest $request, JobLead $jobLead): RedirectResponse
    {
        $jobLead->update($this->jobLeadPayload($request->validated()));

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
    private function importedJobLeadData(ImportJobLeadFromUrlRequest $request): array
    {
        $sourceUrl = $request->string('source_url')->value();

        return array_filter([
            'user_id' => $request->user()->id,
            'source_url' => $sourceUrl,
            'source_name' => $this->nullableString($request->string('source_name')->value()),
            'company_name' => $this->nullableString($request->string('company_name')->value())
                ?? $this->importCompanyName($sourceUrl),
            'job_title' => $this->nullableString($request->string('job_title')->value())
                ?? 'Imported job lead',
            'lead_status' => JobLead::STATUS_SAVED,
            'discovered_at' => today()->toDateString(),
        ], fn (mixed $value): bool => $value !== null);
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
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => $jobLead->description_text,
            'extracted_keywords' => $jobLead->extracted_keywords ?? [],
            'ats_hints' => $jobLead->ats_hints ?? [],
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
     * @return list<array<string, mixed>>
     */
    private function jobCards($jobLeads, ?UserProfile $userProfile, bool $matchedOnly, array $detectedResumeSkills): array
    {
        $resumeReady = $this->resumeReady($userProfile);

        if ($matchedOnly && ! $resumeReady) {
            return [];
        }

        $jobCards = [];

        foreach ($jobLeads as $jobLead) {
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

            $jobCards[] = [
                'id' => $jobLead->id,
                'company_name' => $jobLead->company_name,
                'job_title' => $jobLead->job_title,
                'source_url' => $jobLead->source_url,
                'extracted_keywords' => $jobLead->extracted_keywords ?? [],
                'resume_skills_used' => $detectedResumeSkills,
                'job_keywords_used' => $jobLead->extracted_keywords ?? [],
                'matched_keywords' => $analysis['matched_keywords'],
                'missing_keywords' => $analysis['missing_keywords'],
                'match_summary' => $analysis['match_summary'],
                'can_explain_match' => $detectedResumeSkills !== [] && ($jobLead->extracted_keywords ?? []) !== [],
                'ats_hint' => $jobLead->ats_hints[0] ?? null,
            ];
        }

        return $jobCards;
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
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function jobLeadPayload(array $validatedData, ?int $userId = null): array
    {
        $descriptionText = $this->nullableDescriptionText($validatedData['description_text'] ?? null);
        $analysis = app(JobLeadKeywordExtractor::class)->analyze($descriptionText);

        return array_filter([
            ...$validatedData,
            'user_id' => $userId,
            'description_text' => $descriptionText,
            'extracted_keywords' => $analysis['extracted_keywords'],
            'ats_hints' => $analysis['ats_hints'],
        ], fn (mixed $value): bool => $value !== null);
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

    /**
     * @return array<string, mixed>
     */
    private function sharedPageProps(): array
    {
        return [
            'availableLocales' => ['pt', 'en', 'es'],
            'locale' => app()->getLocale(),
            'translations' => trans('app'),
        ];
    }
}
