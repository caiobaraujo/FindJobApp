<?php

namespace App\Services;

use App\Models\JobLead;
use App\Models\ResumeVariant;
use App\Models\UserProfile;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResumeVariantGenerator
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function generate(
        int $userId,
        JobLead $jobLead,
        UserProfile $userProfile,
        string $mode,
    ): ResumeVariant {
        $this->validateInputs($jobLead, $userProfile, $mode);

        $response = $this->http
            ->withToken((string) config('services.openai.api_key'))
            ->acceptJson()
            ->asJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.resume_variant_model', 'gpt-5-mini'),
                'instructions' => $this->instructionsForMode($mode),
                'input' => $this->inputForGeneration($jobLead, $userProfile, $mode),
            ]);

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'mode' => 'Resume generation is unavailable right now.',
            ]);
        }

        $generatedText = Str::of((string) $response->json('output_text'))
            ->replace("\r\n", "\n")
            ->trim()
            ->value();

        if ($generatedText === '') {
            throw ValidationException::withMessages([
                'mode' => 'Resume generation returned an empty result.',
            ]);
        }

        return ResumeVariant::query()->create([
            'user_id' => $userId,
            'job_lead_id' => $jobLead->id,
            'mode' => $mode,
            'generated_text' => $generatedText,
        ]);
    }

    private function validateInputs(JobLead $jobLead, UserProfile $userProfile, string $mode): void
    {
        if (! in_array($mode, ResumeVariant::modes(), true)) {
            throw ValidationException::withMessages([
                'mode' => 'Select a valid resume mode.',
            ]);
        }

        if (blank($userProfile->base_resume_text)) {
            throw ValidationException::withMessages([
                'mode' => 'Add your base resume before generating a tailored version.',
            ]);
        }

        if (blank($jobLead->description_text)) {
            throw ValidationException::withMessages([
                'mode' => 'Add the full job description before generating a tailored resume.',
            ]);
        }

        if (blank(config('services.openai.api_key'))) {
            throw ValidationException::withMessages([
                'mode' => 'Resume generation is not configured.',
            ]);
        }
    }

    private function instructionsForMode(string $mode): string
    {
        $modeRules = match ($mode) {
            ResumeVariant::MODE_FAITHFUL => implode("\n", [
                '- Use only skills and technologies already present in the base resume or core skills.',
                '- Do not add new technologies from the job if they are not already in the resume.',
                '- Keep the language confident but strictly faithful to the user background.',
            ]),
            ResumeVariant::MODE_ATS_BOOST => implode("\n", [
                '- Include important job technologies even when they are not in the base resume.',
                '- For missing technologies, use safe phrases such as "familiarity with", "exposure to", or "experience with similar technologies".',
                '- Do not claim direct professional experience for missing technologies.',
            ]),
            ResumeVariant::MODE_ATS_SAFE => implode("\n", [
                '- Include important job technologies even when they are not in the base resume.',
                '- For missing technologies, use neutral phrases such as "interest in" or "aligned with".',
                '- Do not imply professional experience for missing technologies.',
            ]),
        };

        return implode("\n", [
            'You rewrite resumes for ATS screening.',
            'Output plain text only. Do not use markdown fences.',
            'Use simple ATS-friendly sections and concise bullets.',
            'Never invent jobs, companies, years of experience, degrees, or certifications.',
            'Never claim direct experience with a technology unless it is already present in the base resume or core skills.',
            'Preserve the candidate background while improving keyword clarity for the selected role.',
            $modeRules,
        ]);
    }

    private function inputForGeneration(JobLead $jobLead, UserProfile $userProfile, string $mode): string
    {
        $keywords = Arr::wrap($jobLead->extracted_keywords);
        $coreSkills = Arr::wrap($userProfile->core_skills);

        return implode("\n\n", [
            'Selected mode: '.$mode,
            'Job lead title: '.($jobLead->job_title ?? 'Not provided'),
            'Company: '.($jobLead->company_name ?? 'Not provided'),
            'Job keywords: '.($keywords === [] ? 'None provided' : implode(', ', $keywords)),
            'Core skills: '.($coreSkills === [] ? 'None provided' : implode(', ', $coreSkills)),
            'Base resume:',
            trim((string) $userProfile->base_resume_text),
            'Job description:',
            trim((string) $jobLead->description_text),
            implode("\n", [
                'Write a tailored resume in plain text with these sections when supported by the base resume:',
                'Summary',
                'Core Skills',
                'Professional Experience Highlights',
                'Education or Certifications only if already present in the base resume',
            ]),
        ]);
    }
}
