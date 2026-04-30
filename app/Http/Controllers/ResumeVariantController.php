<?php

namespace App\Http\Controllers;

use App\Models\JobLead;
use App\Models\ResumeVariant;
use App\Models\UserProfile;
use App\Services\ResumeVariantGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResumeVariantController extends Controller
{
    public function store(
        Request $request,
        JobLead $jobLead,
        ResumeVariantGenerator $resumeVariantGenerator,
    ): RedirectResponse {
        abort_unless($request->user()->id === $jobLead->user_id, 403);

        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(ResumeVariant::modes())],
        ]);

        $userProfile = UserProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if($userProfile === null, 404);

        $resumeVariantGenerator->generate(
            $request->user()->id,
            $jobLead,
            $userProfile,
            (string) $validated['mode'],
        );

        return redirect()
            ->route('job-leads.edit', $jobLead)
            ->with('success', 'Tailored resume generated.');
    }
}
