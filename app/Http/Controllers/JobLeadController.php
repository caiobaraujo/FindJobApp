<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobLeadRequest;
use App\Http\Requests\UpdateJobLeadRequest;
use App\Models\JobLead;
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
        ]);

        $jobLeads = JobLead::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->when(filled($filters['lead_status'] ?? null), function ($query) use ($filters): void {
                $query->where('lead_status', $filters['lead_status']);
            })
            ->search($filters['search'] ?? null)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (JobLead $jobLead): array => $this->jobLeadData($jobLead));

        return Inertia::render('JobLeads/Index', [
            'filters' => [
                'lead_status' => $filters['lead_status'] ?? '',
                'search' => $filters['search'] ?? '',
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
        JobLead::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('job-leads.index')
            ->with('success', 'Job lead created successfully.');
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
        $jobLead->update($request->validated());

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
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => $jobLead->discovered_at?->toDateString(),
        ];
    }
}
