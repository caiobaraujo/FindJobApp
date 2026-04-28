<?php

use App\Models\JobLead;
use App\Models\User;

it('does not expose the csrf token endpoint to guests', function (): void {
    $this->getJson(route('csrf-token.show'))
        ->assertUnauthorized();
});

it('returns a csrf token for authenticated extension clients', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('csrf-token.show'))
        ->assertOk()
        ->assertJsonStructure(['token']);
});

it('does not allow guests to import post based job leads', function (): void {
    $this->post(route('job-leads.import-post'), [
        'source_platform' => 'linkedin',
        'source_post_url' => 'https://www.linkedin.com/posts/guest-blocked',
        'source_context_text' => 'Hiring Laravel developers.',
    ])
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('job_leads', 0);
});

it('allows an authenticated user to import an extension style post lead', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'https://www.linkedin.com/posts/extension-post-123',
            'source_author' => 'Hiring Manager',
            'source_context_text' => 'We are hiring a Laravel and Vue engineer for our remote team.',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.matched_jobs.import_success'));

    $jobLead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($jobLead->source_type)->toBe(JobLead::SOURCE_TYPE_EXTENSION)
        ->and($jobLead->source_platform)->toBe('linkedin')
        ->and($jobLead->source_post_url)->toBe('https://www.linkedin.com/posts/extension-post-123')
        ->and($jobLead->source_author)->toBe('Hiring Manager')
        ->and($jobLead->source_context_text)->toContain('Laravel and Vue engineer')
        ->and($jobLead->description_text)->toContain('Laravel and Vue engineer')
        ->and($jobLead->discovery_batch_id)->toBeNull()
        ->and($jobLead->source_url)->toBeNull()
        ->and($jobLead->company_name)->toBeNull()
        ->and($jobLead->job_title)->toBeNull()
        ->and($jobLead->extracted_keywords)->toContain('laravel')
        ->and($jobLead->extracted_keywords)->toContain('vue');
});

it('returns json for extension clients that request it explicitly', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'https://www.linkedin.com/posts/json-extension-post',
            'source_context_text' => 'Hiring a Laravel engineer through a public social post.',
        ])
        ->assertCreated()
        ->assertJson([
            'status' => 'created',
            'message' => __('app.matched_jobs.import_success'),
        ]);
});

it('dedupes extension imports by source post url when source url is absent', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'https://www.linkedin.com/posts/duplicate-post-route',
            'source_context_text' => 'Hiring Laravel developers.',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($user)
        ->from(route('job-leads.index'))
        ->post(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'https://www.linkedin.com/posts/duplicate-post-route?tracking=feed',
            'source_context_text' => 'Hiring Laravel developers again.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', __('app.job_lead_create.duplicate_error'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('dedupes extension imports by source url when a direct job url is present', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'https://www.linkedin.com/posts/direct-job-one',
            'source_url' => 'https://careers.example.com/jobs/platform-engineer?ref=social',
            'source_context_text' => 'Platform engineer opening with Laravel.',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($user)
        ->from(route('job-leads.index'))
        ->post(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'https://www.linkedin.com/posts/direct-job-two',
            'source_url' => 'https://careers.example.com/jobs/platform-engineer?utm_source=feed#apply',
            'source_context_text' => 'Same role from another post.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', __('app.job_lead_create.duplicate_error'));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('allows another user to import the same post independently', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $payload = [
        'source_platform' => 'linkedin',
        'source_post_url' => 'https://www.linkedin.com/posts/shared-post',
        'source_context_text' => 'Shared hiring post for a PHP platform role.',
    ];

    $this->actingAs($firstUser)
        ->post(route('job-leads.import-post'), $payload)
        ->assertRedirect(route('job-leads.index'));

    $this->actingAs($secondUser)
        ->post(route('job-leads.import-post'), $payload)
        ->assertRedirect(route('job-leads.index'));

    expect(JobLead::query()->count())->toBe(2);
});

it('validates required post text and urls for extension imports', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('job-leads.index'))
        ->post(route('job-leads.import-post'), [
            'source_platform' => 'linkedin',
            'source_post_url' => 'not-a-url',
            'source_url' => 'also-not-a-url',
            'source_context_text' => '',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHasErrors([
            'source_post_url',
            'source_url',
            'source_context_text',
        ]);
});
