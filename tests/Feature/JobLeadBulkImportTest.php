<?php

use App\Models\JobLead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('bulk creates multiple valid job urls', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => "https://example.com/jobs/one\nhttps://example.com/jobs/two",
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_lead_bulk_import.summary', [
            'created' => 2,
            'duplicates' => 0,
            'invalid' => 0,
        ]));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('skips invalid urls without blocking valid bulk imports', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => "https://example.com/jobs/one not-a-url ftp://example.com/jobs/two",
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_lead_bulk_import.summary', [
            'created' => 1,
            'duplicates' => 0,
            'invalid' => 2,
        ]));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('skips same user duplicates in bulk import using normalized source urls', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->saved()->create([
        'source_url' => 'https://example.com/jobs/one',
        'normalized_source_url' => 'https://example.com/jobs/one',
        'source_host' => 'example.com',
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => "https://example.com/jobs/one/?ref=feed#apply https://example.com/jobs/two",
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_lead_bulk_import.summary', [
            'created' => 1,
            'duplicates' => 1,
            'invalid' => 0,
        ]));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('allows different users to bulk import the same source url independently', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => 'https://example.com/jobs/shared-role',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($secondUser)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => 'https://example.com/jobs/shared-role',
        ])
        ->assertRedirect(route('job-leads.index'));

    expect(JobLead::query()->count())->toBe(2);
});

it('bulk imported leads are saved active visible and limited analysis', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => 'https://example.com/jobs/bulk-role',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->lead_status)->toBe(JobLead::STATUS_SAVED)
        ->and($jobLead->source_type)->toBe(JobLead::SOURCE_TYPE_BULK)
        ->and($jobLead->discovery_batch_id)->toBeNull()
        ->and($jobLead->description_text)->toBeNull()
        ->and($jobLead->extracted_keywords)->toBe([])
        ->and($jobLead->ats_hints)->toBe([
            'Paste the full job description to unlock ATS keyword analysis.',
        ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.source_url', 'https://example.com/jobs/bulk-role')
            ->where('matchedJobs.0.lead_status', JobLead::STATUS_SAVED)
            ->where('matchedJobs.0.has_limited_analysis', true)
        );
});

it('re analyzes a bulk imported url only lead when a meaningful description is added', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => 'https://example.com/jobs/enrich-me',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();
    $descriptionText = 'We need a Laravel engineer with Vue, SQL, and API testing experience.';

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => $descriptionText,
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead->refresh();

    expect($jobLead->description_text)->toBe($descriptionText)
        ->and($jobLead->extracted_keywords)->toContain('laravel')
        ->and($jobLead->extracted_keywords)->toContain('vue')
        ->and($jobLead->ats_hints)->not->toBeEmpty();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.has_limited_analysis', false)
        );
});

it('replaces extracted keywords when a job description is replaced', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => 'https://example.com/jobs/replace-description',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => 'Laravel engineer with Vue and SQL ownership.',
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => 'Python backend role with FastAPI, PostgreSQL, and automation experience.',
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead->refresh();

    expect($jobLead->extracted_keywords)->toContain('python')
        ->and($jobLead->extracted_keywords)->toContain('postgresql')
        ->and($jobLead->extracted_keywords)->not->toContain('laravel')
        ->and($jobLead->extracted_keywords)->not->toContain('vue');
});

it('returns a bulk imported lead to limited analysis when the description is cleared', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.bulk-import'), [
            'source_urls' => 'https://example.com/jobs/clear-description',
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => 'Laravel engineer with Vue and SQL ownership.',
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => $jobLead->company_name,
            'job_title' => $jobLead->job_title,
            'source_name' => $jobLead->source_name,
            'source_url' => $jobLead->source_url,
            'location' => $jobLead->location,
            'work_mode' => $jobLead->work_mode,
            'salary_range' => $jobLead->salary_range,
            'description_excerpt' => $jobLead->description_excerpt,
            'description_text' => '',
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertRedirect(route('job-leads.index'));

    $jobLead->refresh();

    expect($jobLead->description_text)->toBeNull()
        ->and($jobLead->extracted_keywords)->toBe([])
        ->and($jobLead->ats_hints)->toBe([
            'Paste the full job description to unlock ATS keyword analysis.',
        ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('matchedJobs.0.has_limited_analysis', true)
        );
});

it('does not allow guests to bulk import job urls', function (): void {
    $this->post(route('job-leads.bulk-import'), [
        'source_urls' => 'https://example.com/jobs/guest-blocked',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('job_leads', 0);
});
