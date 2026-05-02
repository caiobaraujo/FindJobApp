<?php

use App\Models\JobLead;
use App\Models\ResumeVariant;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

dataset('resume variant modes', [
    'faithful' => [
        'mode' => ResumeVariant::MODE_FAITHFUL,
        'instruction' => 'FAITHFUL mode:',
        'response' => implode("\n", [
            'Summary',
            'Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience. Focused on backend delivery and maintainable implementation.',
            '',
            'Core Skills',
            '- PHP',
            '- Laravel',
            '- MySQL',
            '- Docker',
            '- APIs',
            '',
            'Professional Experience',
            '- Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience.',
            '- Builds maintainable backend solutions with collaborative delivery.',
            '',
            'Target Role Alignment',
            '- Faithful fit for backend roles that value PHP, Laravel, MySQL, APIs, and Docker.',
            '- Aligns with the role through existing backend delivery experience.',
        ]),
    ],
    'ats_boost' => [
        'mode' => ResumeVariant::MODE_ATS_BOOST,
        'instruction' => 'ATS_BOOST mode:',
        'response' => implode("\n", [
            'Summary',
            'Backend engineer with PHP, Laravel, MySQL, APIs, and Docker, plus familiarity with Python, Airflow, SQL, dbt, and cloud analytics workflows.',
            '',
            'Core Skills',
            '- PHP',
            '- Laravel',
            '- MySQL',
            '- Docker',
            '- APIs',
            '- familiarity with Python',
            '- exposure to Airflow',
            '- experience with similar technologies in SQL and dbt environments',
            '',
            'Professional Experience',
            '- Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience.',
            '- Supported delivery using maintainable implementation patterns and collaborative execution.',
            '',
            'Target Role Alignment',
            '- Familiarity with Python, Airflow, SQL, dbt, and cloud-based analytics engineering supports the target role.',
            '- Exposure to similar data workflow technologies helps align this background with the role requirements.',
            '- The resume stays defensible by avoiding direct claims for technologies not in the current resume.',
        ]),
    ],
    'ats_safe' => [
        'mode' => ResumeVariant::MODE_ATS_SAFE,
        'instruction' => 'ATS_SAFE mode:',
        'response' => implode("\n", [
            'Summary',
            'Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience, aligned with Python, Airflow, SQL, dbt, and cloud-oriented analytics work.',
            '',
            'Core Skills',
            '- PHP',
            '- Laravel',
            '- MySQL',
            '- Docker',
            '- APIs',
            '- interest in Python',
            '- aligned with Airflow',
            '- motivated to work with SQL and dbt',
            '',
            'Professional Experience',
            '- Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience.',
            '- Focused on maintainable delivery and practical implementation.',
            '',
            'Target Role Alignment',
            '- Interest in Python, Airflow, SQL, and dbt aligns with this analytics-focused role.',
            '- Motivated to work with cloud data workflows while staying grounded in existing backend experience.',
            '- Language stays neutral and avoids implying direct experience with new technologies.',
        ]),
    ],
]);

it('generates a tailored resume variant for a job lead and respects the selected mode', function (
    string $mode,
    string $instruction,
    string $response,
): void {
    config()->set('services.gemini.key', 'test-gemini-key');
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => $response,
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience.',
        'core_skills' => ['PHP', 'Laravel', 'MySQL', 'Docker'],
        'auto_discover_jobs' => false,
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Asaas',
        'job_title' => 'Analytics Engineer',
        'source_url' => 'https://asaas.gupy.io/jobs/11223001',
        'description_text' => 'Need Python, Airflow, SQL, dbt, and cloud experience for analytics engineering.',
        'extracted_keywords' => ['python', 'airflow', 'sql', 'dbt', 'cloud'],
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.resume-variants.store', $jobLead), [
            'mode' => $mode,
        ])
        ->assertRedirect(route('job-leads.edit', $jobLead))
        ->assertSessionHas('success', __('app.resume_variants.generated_success'));

    $resumeVariant = ResumeVariant::query()->sole();

    expect($resumeVariant->user_id)->toBe($user->id)
        ->and($resumeVariant->job_lead_id)->toBe($jobLead->id)
        ->and($resumeVariant->mode)->toBe($mode)
        ->and($resumeVariant->generated_text)->toBe($response)
        ->and($resumeVariant->generated_text)->toContain('Summary')
        ->and($resumeVariant->generated_text)->toContain('Core Skills')
        ->and($resumeVariant->generated_text)->toContain('Professional Experience')
        ->and($resumeVariant->generated_text)->toContain('Target Role Alignment');

    Http::assertSent(function (Request $request) use ($instruction, $mode, $jobLead): bool {
        $payload = $request->data();
        $prompt = (string) data_get($payload, 'contents.0.parts.0.text', '');

        return str_contains($request->url(), 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=test-gemini-key')
            && str_contains($prompt, $instruction)
            && str_contains($prompt, 'Use exactly these section headings in this order: Summary, Core Skills, Professional Experience, Target Role Alignment.')
            && str_contains($prompt, 'Selected mode: '.$mode)
            && str_contains($prompt, 'Job lead title: '.$jobLead->job_title)
            && str_contains($prompt, 'Job keywords:')
            && str_contains($prompt, 'Core skills:')
            && str_contains($prompt, 'Base resume:')
            && str_contains($prompt, 'Job description:');
    });
})->with('resume variant modes');

it('returns a safe message when Gemini is not configured', function (): void {
    config()->set('services.gemini.key', '');
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    Http::fake();

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Backend engineer with PHP and Laravel experience.',
        'core_skills' => ['PHP', 'Laravel'],
        'auto_discover_jobs' => false,
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Asaas',
        'job_title' => 'Analytics Engineer',
        'source_url' => 'https://asaas.gupy.io/jobs/11223001',
        'description_text' => 'Need Python, Airflow, SQL, dbt, and cloud experience for analytics engineering.',
        'extracted_keywords' => ['python', 'airflow', 'sql', 'dbt', 'cloud'],
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.resume-variants.store', $jobLead), [
            'mode' => ResumeVariant::MODE_FAITHFUL,
        ])
        ->assertRedirect(route('job-leads.edit', $jobLead))
        ->assertSessionHas('error', __('app.resume_variants.unavailable'));

    $resumeVariant = ResumeVariant::query()->sole();

    expect($resumeVariant->generated_text)->toBe(__('app.resume_variants.unavailable'));

    Http::assertNothingSent();
});

it('returns a safe message when the Gemini model is not configured', function (): void {
    config()->set('services.gemini.key', 'test-gemini-key');
    config()->set('services.gemini.model', '');
    Http::fake();

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Backend engineer with PHP and Laravel experience.',
        'core_skills' => ['PHP', 'Laravel'],
        'auto_discover_jobs' => false,
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Asaas',
        'job_title' => 'Analytics Engineer',
        'source_url' => 'https://asaas.gupy.io/jobs/11223001',
        'description_text' => 'Need Python, Airflow, SQL, dbt, and cloud experience for analytics engineering.',
        'extracted_keywords' => ['python', 'airflow', 'sql', 'dbt', 'cloud'],
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.resume-variants.store', $jobLead), [
            'mode' => ResumeVariant::MODE_FAITHFUL,
        ])
        ->assertRedirect(route('job-leads.edit', $jobLead))
        ->assertSessionHas('error', __('app.resume_variants.unavailable_model'));

    $resumeVariant = ResumeVariant::query()->sole();

    expect($resumeVariant->generated_text)->toBe(__('app.resume_variants.unavailable_model'));

    Http::assertNothingSent();
});

it('returns a safe message and logs a warning when Gemini fails', function (): void {
    config()->set('services.gemini.key', 'test-gemini-key');
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    Log::spy();

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'Quota exceeded.',
            ],
        ], 429),
    ]);

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Backend engineer with PHP and Laravel experience.',
        'core_skills' => ['PHP', 'Laravel'],
        'auto_discover_jobs' => false,
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Asaas',
        'job_title' => 'Analytics Engineer',
        'source_url' => 'https://asaas.gupy.io/jobs/11223001',
        'description_text' => 'Need Python, Airflow, SQL, dbt, and cloud experience for analytics engineering.',
        'extracted_keywords' => ['python', 'airflow', 'sql', 'dbt', 'cloud'],
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.resume-variants.store', $jobLead), [
            'mode' => ResumeVariant::MODE_FAITHFUL,
        ])
        ->assertRedirect(route('job-leads.edit', $jobLead))
        ->assertSessionHas('error', __('app.resume_variants.generation_failed'));

    $resumeVariant = ResumeVariant::query()->sole();

    expect($resumeVariant->generated_text)->toBe(__('app.resume_variants.generation_failed'));

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Gemini resume generation failed.'
                && ($context['status'] ?? null) === 429
                && ($context['error_message'] ?? null) === 'Quota exceeded.';
        });
});

it('exposes localized resume variant modes on the job lead edit page', function (): void {
    $user = User::factory()->create();

    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Asaas',
        'job_title' => 'Analytics Engineer',
        'source_url' => 'https://asaas.gupy.io/jobs/11223001',
        'description_text' => 'Need Python, Airflow, SQL, dbt, and cloud experience for analytics engineering.',
        'extracted_keywords' => ['python', 'airflow', 'sql', 'dbt', 'cloud'],
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Edit')
            ->where('resumeVariantModes.0.label', __('app.resume_variants.modes.faithful.label'))
            ->where('resumeVariantModes.1.label', __('app.resume_variants.modes.ats_boost.label'))
            ->where('resumeVariantModes.2.label', __('app.resume_variants.modes.ats_safe.label'))
        );
});

it('keeps the generated output distinct across faithful ats boost and ats safe modes', function (): void {
    config()->set('services.gemini.key', 'test-gemini-key');
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');

    Http::fake(function (Request $request) {
        $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

        return match (true) {
            str_contains($prompt, 'Selected mode: faithful') => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "Summary\nFaithful summary\n\nCore Skills\n- PHP\n\nProfessional Experience\n- Faithful experience\n\nTarget Role Alignment\n- Faithful alignment"],
                            ],
                        ],
                    ],
                ],
            ], 200),
            str_contains($prompt, 'Selected mode: ats_boost') => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "Summary\nATS boost summary with familiarity with Python.\n\nCore Skills\n- PHP\n- familiarity with Python\n\nProfessional Experience\n- ATS boost experience\n\nTarget Role Alignment\n- Familiarity with Python and similar technologies."],
                            ],
                        ],
                    ],
                ],
            ], 200),
            str_contains($prompt, 'Selected mode: ats_safe') => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "Summary\nATS safe summary aligned with Python.\n\nCore Skills\n- PHP\n- interest in Python\n\nProfessional Experience\n- ATS safe experience\n\nTarget Role Alignment\n- Interest in Python and alignment with the role."],
                            ],
                        ],
                    ],
                ],
            ], 200),
            default => Http::response([
                'candidates' => [],
            ], 200),
        };
    });

    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Backend engineer with PHP, Laravel, MySQL, APIs, and Docker experience.',
        'core_skills' => ['PHP', 'Laravel', 'MySQL', 'Docker'],
        'auto_discover_jobs' => false,
    ]);

    $jobLead = JobLead::factory()->for($user)->create([
        'company_name' => 'Asaas',
        'job_title' => 'Analytics Engineer',
        'source_url' => 'https://asaas.gupy.io/jobs/11223001',
        'description_text' => 'Need Python, Airflow, SQL, dbt, and cloud experience for analytics engineering.',
        'extracted_keywords' => ['python', 'airflow', 'sql', 'dbt', 'cloud'],
    ]);

    foreach (ResumeVariant::modes() as $mode) {
        $this->actingAs($user)
            ->post(route('job-leads.resume-variants.store', $jobLead), [
                'mode' => $mode,
            ])
            ->assertRedirect(route('job-leads.edit', $jobLead));
    }

    $generatedTexts = ResumeVariant::query()->pluck('generated_text')->all();

    expect($generatedTexts)->toHaveCount(3)
        ->and(count(array_unique($generatedTexts)))->toBe(3);
});
