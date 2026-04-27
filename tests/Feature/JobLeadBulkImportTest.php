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

it('does not allow guests to bulk import job urls', function (): void {
    $this->post(route('job-leads.bulk-import'), [
        'source_urls' => 'https://example.com/jobs/guest-blocked',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('job_leads', 0);
});
