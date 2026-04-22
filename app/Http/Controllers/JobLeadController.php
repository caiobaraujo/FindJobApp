<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportJobLeadFromUrlRequest;
use App\Http\Requests\StoreJobLeadRequest;
use App\Http\Requests\UpdateJobLeadRequest;
use App\Models\JobLead;
use App\Services\JobLeadKeywordExtractor;
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
            'lead_status' => ['nullable', 'string', Rule::in(JobLead::leadStatuses())],
            'search' => ['nullable', 'string', 'max:255'],
            'minimum_relevance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $jobLeads = JobLead::query()
            ->where('user_id', $request->user()->id)
            ->orderByPriority()
            ->when(filled($filters['lead_status'] ?? null), function ($query) use ($filters): void {
                $query->where('lead_status', $filters['lead_status']);
            })
            ->search($filters['search'] ?? null)
            ->minimumRelevanceScore($filters['minimum_relevance_score'] ?? null)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (JobLead $jobLead): array => $this->jobLeadData($jobLead));

        return Inertia::render('JobLeads/Index', [
            'filters' => [
                'lead_status' => $filters['lead_status'] ?? '',
                'search' => $filters['search'] ?? '',
                'minimum_relevance_score' => $filters['minimum_relevance_score'] ?? '',
            ],
            'jobLeads' => $jobLeads,
            'leadStatuses' => JobLead::leadStatuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('JobLeads/Create', [
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
            ->with('success', 'Job lead created successfully.');
    }

    public function importFromUrl(ImportJobLeadFromUrlRequest $request): RedirectResponse
    {
        JobLead::query()->create($this->importedJobLeadData($request));

        return redirect()
            ->route('job-leads.index')
            ->with('success', 'Job lead imported successfully.');
    }

    public function edit(JobLead $jobLead, Request $request): Response
    {
        $this->authorizeOwner($jobLead, $request);

        return Inertia::render('JobLeads/Edit', [
            'jobLead' => $this->jobLeadData($jobLead),
            'leadStatuses' => JobLead::leadStatuses(),
            'workModes' => JobLead::workModes(),
        ]);
    }

    public function update(UpdateJobLeadRequest $request, JobLead $jobLead): RedirectResponse
    {
        $jobLead->update($this->jobLeadPayload($request->validated()));

        return redirect()
            ->route('job-leads.index')
            ->with('success', 'Job lead updated successfully.');
    }

    public function destroy(JobLead $jobLead, Request $request): RedirectResponse
    {
        $this->authorizeOwner($jobLead, $request);
        $jobLead->delete();

        return redirect()
            ->route('job-leads.index')
            ->with('success', 'Job lead deleted successfully.');
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
}
