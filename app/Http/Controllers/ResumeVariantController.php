<?php

namespace App\Http\Controllers;

use App\Models\JobLead;
use App\Models\ResumeVariant;
use App\Models\UserProfile;
use App\Services\ResumeVariantGenerator;
use App\Services\ResumeVariantSectionExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $resumeVariant = $resumeVariantGenerator->generate(
            $request->user()->id,
            $jobLead,
            $userProfile,
            (string) $validated['mode'],
        );

        $errorMessages = [
            __('app.resume_variants.unavailable'),
            __('app.resume_variants.unavailable_model'),
            __('app.resume_variants.generation_failed'),
        ];

        $flashKey = in_array($resumeVariant->generated_text, $errorMessages, true)
            ? 'error'
            : 'success';

        return redirect()
            ->route('job-leads.edit', $jobLead)
            ->with($flashKey, $flashKey === 'error'
                ? $resumeVariant->generated_text
                : __('app.resume_variants.generated_success'));
    }

    public function download(
        Request $request,
        ResumeVariant $resumeVariant,
        ResumeVariantSectionExtractor $resumeVariantSectionExtractor,
    ): HttpResponse {
        abort_unless($request->user()->id === $resumeVariant->user_id, 403);
        abort_if($resumeVariant->isErrorState(), 404);

        $resumeVariant->loadMissing(['jobLead', 'user']);

        $pdf = Pdf::loadView('resume-variants.pdf', [
            'candidateName' => $this->candidateName($resumeVariant),
            'companyName' => $resumeVariant->jobLead?->company_name,
            'jobTitle' => $resumeVariant->jobLead?->job_title,
            'modeLabel' => $this->modeLabel($resumeVariant->mode),
            'sections' => $resumeVariantSectionExtractor->extract((string) $resumeVariant->generated_text),
        ]);

        $pdf->setPaper('a4');

        return $pdf->download($this->downloadFilename($resumeVariant));
    }

    private function candidateName(ResumeVariant $resumeVariant): ?string
    {
        $name = trim((string) ($resumeVariant->user?->name ?? ''));

        return $name === '' ? null : $name;
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            ResumeVariant::MODE_FAITHFUL => __('app.resume_variants.modes.faithful.label'),
            ResumeVariant::MODE_ATS_BOOST => __('app.resume_variants.modes.ats_boost.label'),
            ResumeVariant::MODE_ATS_SAFE => __('app.resume_variants.modes.ats_safe.label'),
            default => $mode,
        };
    }

    private function downloadFilename(ResumeVariant $resumeVariant): string
    {
        $jobTitle = Str::slug((string) ($resumeVariant->jobLead?->job_title ?? 'resume-variant'));
        $companyName = Str::slug((string) ($resumeVariant->jobLead?->company_name ?? 'company'));

        return sprintf('%s-%s-%s.pdf', $jobTitle, $companyName, $resumeVariant->mode);
    }
}
