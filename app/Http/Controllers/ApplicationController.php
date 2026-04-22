<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $applications = $user
            ->applications()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Application $application): array => $this->applicationData($application))
            ->all();

        return Inertia::render('Dashboard', [
            'applications' => $applications,
            'statusCounts' => $this->statusCounts($user->id),
            'totalApplications' => $user->applications()->count(),
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Application::class);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(Application::statuses())],
            'search' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'string', Rule::in(['list', 'pipeline'])],
        ]);

        $query = $request
            ->user()
            ->applications()
            ->latest()
            ->when(filled($filters['status'] ?? null), function ($query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->search($filters['search'] ?? null);

        $applications = (clone $query)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Application $application): array => $this->applicationData($application));

        $pipelineApplications = (clone $query)->get();

        return Inertia::render('Applications/Index', [
            'applications' => $applications,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'search' => $filters['search'] ?? '',
                'view' => $filters['view'] ?? 'list',
            ],
            'pipelineColumns' => $this->pipelineColumns($pipelineApplications),
            'statuses' => Application::statuses(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Application::class);

        return Inertia::render('Applications/Create', [
            'statuses' => Application::statuses(),
        ]);
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $request->user()->applications()->create($request->validated());

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application created successfully.');
    }

    public function edit(Application $application): Response
    {
        $this->authorize('update', $application);

        return Inertia::render('Applications/Edit', [
            'application' => $this->applicationFormData($application),
            'statuses' => Application::statuses(),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        $application->update($request->validated());

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application updated successfully.');
    }

    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);
        $application->delete();

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application deleted successfully.');
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
            'source_url' => $application->source_url,
            'status' => $application->status,
            'applied_at' => $application->applied_at?->toDateString(),
            'notes' => $application->notes,
            'created_at' => $application->created_at->toDateString(),
            'updated_at' => $application->updated_at->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationFormData(Application $application): array
    {
        return [
            'id' => $application->id,
            'company_name' => $application->company_name,
            'job_title' => $application->job_title,
            'source_url' => $application->source_url,
            'status' => $application->status,
            'applied_at' => $application->applied_at?->toDateString(),
            'notes' => $application->notes,
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
     * @param  Collection<int, Application>  $applications
     * @return array<int, array<string, mixed>>
     */
    private function pipelineColumns(Collection $applications): array
    {
        $groupedApplications = $applications->groupBy('status');

        return collect(Application::statuses())
            ->map(function (string $status) use ($groupedApplications): array {
                $items = $groupedApplications
                    ->get($status, collect())
                    ->map(fn (Application $application): array => $this->applicationData($application))
                    ->values()
                    ->all();

                return [
                    'key' => $status,
                    'title' => ucfirst($status),
                    'count' => count($items),
                    'applications' => $items,
                ];
            })
            ->all();
    }
}
