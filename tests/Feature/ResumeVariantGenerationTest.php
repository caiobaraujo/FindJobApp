<?php

use App\Models\JobLead;
use App\Models\ResumeVariant;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

dataset('resume variant modes', [
    'faithful' => [
        'mode' => ResumeVariant::MODE_FAITHFUL,
        'instruction' => 'Use only skills and technologies already present in the base resume or core skills.',
        'response' => "Summary\nPHP and Laravel engineer focused on backend delivery.\n\nCore Skills\n- PHP\n- Laravel\n- MySQL",
    ],
    'ats_boost' => [
        'mode' => ResumeVariant::MODE_ATS_BOOST,
        'instruction' => 'Include important job technologies even when they are not in the base resume.',
        'response' => "Summary\nBackend engineer with PHP and Laravel plus familiarity with Python and Airflow.\n\nCore Skills\n- PHP\n- Laravel\n- familiarity with Python\n- exposure to Airflow",
    ],
    'ats_safe' => [
        'mode' => ResumeVariant::MODE_ATS_SAFE,
        'instruction' => 'Include important job technologies even when they are not in the base resume.',
        'response' => "Summary\nBackend engineer with PHP and Laravel, aligned with Python-heavy data teams.\n\nCore Skills\n- PHP\n- Laravel\n- interest in Python\n- aligned with Airflow environments",
    ],
]);

it('generates a tailored resume variant for a job lead and respects the selected mode', function (
    string $mode,
    string $instruction,
    string $response,
): void {
    config()->set('services.openai.api_key', 'test-openai-key');
    config()->set('services.openai.resume_variant_model', 'gpt-5-mini');

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output_text' => $response,
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
        ->assertSessionHas('success', 'Tailored resume generated.');

    $resumeVariant = ResumeVariant::query()->sole();

    expect($resumeVariant->user_id)->toBe($user->id)
        ->and($resumeVariant->job_lead_id)->toBe($jobLead->id)
        ->and($resumeVariant->mode)->toBe($mode)
        ->and($resumeVariant->generated_text)->toBe($response);

    Http::assertSent(function (Request $request) use ($instruction, $mode, $jobLead): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.openai.com/v1/responses'
            && ($payload['model'] ?? null) === 'gpt-5-mini'
            && str_contains((string) ($payload['instructions'] ?? ''), $instruction)
            && str_contains((string) ($payload['input'] ?? ''), 'Selected mode: '.$mode)
            && str_contains((string) ($payload['input'] ?? ''), 'Job lead title: '.$jobLead->job_title)
            && str_contains((string) ($payload['input'] ?? ''), 'Base resume:');
    });
})->with('resume variant modes');
