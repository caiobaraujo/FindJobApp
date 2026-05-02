<?php

namespace App\Services;

use App\Models\JobLead;
use App\Models\ResumeVariant;
use App\Models\UserProfile;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
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

        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if (blank($apiKey)) {
            return $this->createVariant($userId, $jobLead, $mode, __('app.resume_variants.unavailable'));
        }

        if (blank($model)) {
            return $this->createVariant($userId, $jobLead, $mode, __('app.resume_variants.unavailable_model'));
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->post(
                    sprintf(
                        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                        rawurlencode($model),
                        rawurlencode($apiKey),
                    ),
                    $this->requestPayload($jobLead, $userProfile, $mode),
                );

            if (! $response->successful()) {
                $this->logGeminiFailure($response->status(), data_get($response->json(), 'error.message'));

                return $this->createVariant($userId, $jobLead, $mode, __('app.resume_variants.generation_failed'));
            }

            $generatedText = Str::of((string) data_get($response->json(), 'candidates.0.content.parts.0.text'))
                ->replace("\r\n", "\n")
                ->trim()
                ->value();
        } catch (\Throwable $throwable) {
            $this->logGeminiFailure(null, $throwable->getMessage());

            return $this->createVariant($userId, $jobLead, $mode, __('app.resume_variants.generation_failed'));
        }

        if ($generatedText === '') {
            $this->logGeminiFailure($response->status() ?? null, data_get($response->json(), 'error.message'));

            return $this->createVariant($userId, $jobLead, $mode, __('app.resume_variants.generation_failed'));
        }

        return $this->createVariant($userId, $jobLead, $mode, $generatedText);
    }

    private function logGeminiFailure(?int $status, ?string $errorMessage): void
    {
        Log::warning('Gemini resume generation failed.', array_filter([
            'status' => $status,
            'error_message' => blank($errorMessage) ? null : Str::of($errorMessage)->limit(200)->value(),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function createVariant(
        int $userId,
        JobLead $jobLead,
        string $mode,
        string $generatedText,
    ): ResumeVariant {
        return ResumeVariant::query()->create([
            'user_id' => $userId,
            'job_lead_id' => $jobLead->id,
            'mode' => $mode,
            'generated_text' => $generatedText,
        ]);
    }

    /**
     * @return array{contents: array<int, array{parts: array<int, array{text: string}>}>}
     */
    private function requestPayload(JobLead $jobLead, UserProfile $userProfile, string $mode): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => implode("\n\n", [
                                $this->instructionsForMode($mode),
                                $this->inputForGeneration($jobLead, $userProfile, $mode),
                            ]),
                        ],
                    ],
                ],
            ],
        ];
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
    }

    private function instructionsForMode(string $mode): string
    {
        return implode("\n", [
            'You rewrite resumes for ATS screening.',
            'Output plain text only. Do not use markdown fences, code fences, markdown tables, emojis, or icons.',
            'Use exactly these section headings in this order: Summary, Core Skills, Professional Experience, Target Role Alignment.',
            'Keep the output realistic, concise, and defensible.',
            'Never invent jobs, companies, years of experience, degrees, or certifications.',
            'Never claim direct experience with a technology unless it is already present in the base resume or core skills.',
            'Keep the candidate background recognizable while improving keyword clarity for the selected role.',
            'SUMMARY rules: write 2 to 4 lines as a short paragraph, mention the main stack from the resume, and include job keywords safely when allowed by mode.',
            'CORE SKILLS rules: use bullet points, keep real skills first, then add job keywords allowed by the selected mode.',
            'PROFESSIONAL EXPERIENCE rules: lightly rewrite the resume experience text into ATS-friendly bullets or short entries, but do not invent roles, employers, dates, or credentials.',
            'TARGET ROLE ALIGNMENT rules: make this the key section and vary it strongly by mode.',
            $this->modeRules($mode),
        ]);
    }

    private function inputForGeneration(JobLead $jobLead, UserProfile $userProfile, string $mode): string
    {
        $keywords = $this->normalizedList($jobLead->extracted_keywords ?? []);
        $coreSkills = $this->normalizedList($userProfile->core_skills ?? []);

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
            'Do not add an education section unless the base resume already includes education or certifications and the job relevance is clear.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalizedList(array $items): array
    {
        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $items,
        )), static fn (string $item): bool => $item !== ''));
    }

    private function modeRules(string $mode): string
    {
        return match ($mode) {
            ResumeVariant::MODE_FAITHFUL => implode("\n", [
                'FAITHFUL mode:',
                '- Use only skills, tools, and domains already present in the base resume or core skills.',
                '- Do not add new technologies from the job description.',
                '- Target Role Alignment should explain fit using existing background only.',
                '- Keep the resume honest and conservative.',
            ]),
            ResumeVariant::MODE_ATS_BOOST => implode("\n", [
                'ATS_BOOST mode:',
                '- Include all key job technologies in the alignment section.',
                '- Use safe language such as "familiarity with", "exposure to", or "experience with similar technologies" for anything not in the resume.',
                '- Never claim direct professional experience for technologies not present in the resume.',
                '- Prioritize keyword presence while staying defensible.',
            ]),
            ResumeVariant::MODE_ATS_SAFE => implode("\n", [
                'ATS_SAFE mode:',
                '- Include key job technologies in the alignment section.',
                '- Use neutral language such as "interest in", "aligned with", or "motivated to work with" for anything not in the resume.',
                '- Do not imply professional experience for technologies not present in the resume.',
                '- Prioritize clarity and gentle keyword coverage.',
            ]),
        };
    }
}
