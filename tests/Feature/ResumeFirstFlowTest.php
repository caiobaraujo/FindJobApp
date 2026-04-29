<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a resume first cta on the dashboard when no resume exists', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('hasResumeProfile', false)
            ->where('resumeReady', false)
            ->where('matchedJobsCount', 0)
        );
});

it('renders detected resume skills when available', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue, SQL, and AWS delivery experience.',
        'core_skills' => ['Laravel', 'Vue'],
    ]);

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->where('detectedResumeSkills.0', 'laravel')
            ->where('detectedResumeSkills.1', 'vue')
        );
});

it('exposes deterministic resume discovery signals for development inspection', function (): void {
    $user = User::factory()->create();
    $resumeText = file_get_contents(base_path('tests/Fixtures/resume_discovery_signals_full_stack.txt'));

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => $resumeText,
        'core_skills' => ['PHP', 'Laravel', 'Python', 'Django', 'Vue.js', 'Angular', 'Docker', 'SQL', 'MySQL', 'OpenAI', 'NLP', 'LLMs'],
    ]);

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->where('resumeDiscoverySignals.canonical_skills.0', 'vue')
            ->where('resumeDiscoverySignals.canonical_skills.5', 'laravel')
            ->where('resumeDiscoverySignals.role_families.0', 'frontend_vue')
            ->where('resumeDiscoverySignals.role_families.4', 'backend_python')
            ->where('resumeDiscoverySignals.role_families.9', 'ai_applied')
            ->where('resumeDiscoverySignals.aliases.2', 'vuejs')
            ->where('resumeDiscoverySignals.aliases', fn ($aliases): bool => $aliases->contains('openai'))
            ->where('resumeDiscoverySignals.query_profiles.0.key', 'frontend_vue')
            ->where('resumeDiscoverySignals.query_profiles.2.key', 'backend_python')
            ->where('resumeDiscoverySignals.query_profiles.2.signals', ['backend_python', 'backend', 'python', 'django'])
            ->where('resumeDiscoverySignals.query_profiles.7.key', 'ai_applied')
            ->where('resumeDiscoverySignals.query_profiles.7.signals', ['ai_applied', 'chatbot', 'nlp', 'llm', 'openai'])
        );
});

it('renders an empty detected resume skills state when unavailable', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => null,
        'core_skills' => null,
    ]);

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->where('detectedResumeSkills', [])
        );
});

it('renders matched jobs with matched and missing keywords when resume data exists', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        'core_skills' => ['Laravel', 'Vue', 'SQL'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Northwind',
        'job_title' => 'Senior Laravel Engineer',
        'source_url' => 'https://example.com/jobs/northwind',
        'extracted_keywords' => ['laravel', 'vue', 'aws'],
        'ats_hints' => ['Mirror the strongest stack terms in your resume bullets.'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Hidden Co',
        'job_title' => 'Python Role',
        'source_url' => '',
        'extracted_keywords' => ['python'],
        'ats_hints' => ['This should not match the resume.'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('hasResumeProfile', true)
            ->where('resumeReady', true)
            ->where('detectedResumeSkills.0', 'laravel')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Northwind')
            ->where('matchedJobs.0.can_explain_match', true)
            ->where('matchedJobs.0.resume_skills_used.0', 'laravel')
            ->where('matchedJobs.0.job_keywords_used.0', 'laravel')
            ->where('matchedJobs.0.matched_keywords.0', 'laravel')
            ->where('matchedJobs.0.matched_keywords.1', 'vue')
            ->where('matchedJobs.0.missing_keywords.0', 'aws')
            ->where('matchedJobs.0.why_this_job.matched_keywords', ['laravel', 'vue'])
            ->where('matchedJobs.0.why_this_job.missing_keywords', ['aws'])
            ->where('matchedJobs.0.why_this_job.preference_summary', null)
            ->where('matchedJobs.0.source_url', 'https://example.com/jobs/northwind')
        )
        ->assertDontSee('Hidden Co');
});

it('keeps jobs without a source url from exposing a go to job button payload', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Python developer with API experience.',
    ]);

    JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'No Link Co',
        'job_title' => 'Python Developer',
        'source_url' => '',
        'extracted_keywords' => ['python', 'api'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.source_url', '')
        );
});

it('keeps match explanation data secondary when prerequisites are missing', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => null,
        'core_skills' => ['Laravel'],
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Partial Match Co',
        'job_title' => 'Laravel Engineer',
        'source_url' => 'https://example.com/jobs/partial',
        'extracted_keywords' => [],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('detectedResumeSkills.0', 'laravel')
            ->where('leadsMissingAnalysisCount', 1)
            ->has('matchedJobs', 0)
        );
});

it('uses the latest persisted profile data on the next matched jobs page load', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer',
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Fresh Match Co',
        'job_title' => 'Python Engineer',
        'source_url' => 'https://example.com/jobs/python',
        'extracted_keywords' => ['python'],
    ]);

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 0)
        );

    $this->actingAs($user)
        ->patch(route('resume-profile.update'), [
            'base_resume_text' => 'Python engineer with APIs and automation.',
        ])
        ->assertRedirect(route('resume-profile.show'));

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('resumeReady', true)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Fresh Match Co')
            ->where('matchedJobs.0.matched_keywords.0', 'python')
        );
});

it('does not keep stale match output after a non-text resume upload replaces old resume text', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Laravel engineer with Vue.',
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Old Match Co',
        'job_title' => 'Laravel Engineer',
        'source_url' => 'https://example.com/jobs/laravel',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->patch(route('resume-profile.update'), [
            'base_resume_text' => '',
            'resume_file' => \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('resume-profile.show'));

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('resumeReady', false)
            ->where('resumeNeedsTextInput', true)
            ->has('matchedJobs', 0)
        )
        ->assertDontSee('Old Match Co');
});

it('sends add job calls to a usable intake route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('job-leads.import.entry'))
        ->assertRedirect(route('job-leads.create'));
});
