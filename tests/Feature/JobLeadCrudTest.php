<?php

use App\Models\JobLead;
use App\Models\User;

it('allows an authenticated user to manage job leads', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('job-leads.create'))
        ->assertOk();

    $payload = [
        'company_name' => 'Northwind',
        'job_title' => 'Senior Product Engineer',
        'source_name' => 'LinkedIn',
        'source_url' => 'https://example.com/jobs/senior-product-engineer',
        'location' => 'Remote',
        'work_mode' => 'remote',
        'salary_range' => '$120k-$150k',
        'description_excerpt' => 'Strong match for product-minded engineering work.',
        'relevance_score' => 88,
        'lead_status' => 'saved',
        'discovered_at' => '2026-04-22',
    ];

    $this->actingAs($user)
        ->post(route('job-leads.store'), $payload)
        ->assertRedirect(route('job-leads.index'));

    $jobLead = JobLead::query()->firstOrFail();

    expect($jobLead->user_id)->toBe($user->id);

    $this->assertDatabaseHas('job_leads', [
        'id' => $jobLead->id,
        'company_name' => 'Northwind',
        'job_title' => 'Senior Product Engineer',
        'lead_status' => 'saved',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.edit', $jobLead))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('JobLeads/Edit')
            ->where('jobLead.id', $jobLead->id)
        );

    $this->actingAs($user)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => 'Northwind Labs',
            'job_title' => 'Staff Product Engineer',
            'source_name' => 'Indeed',
            'source_url' => 'https://example.com/jobs/staff-product-engineer',
            'location' => 'New York, NY',
            'work_mode' => 'hybrid',
            'salary_range' => '$150k-$180k',
            'description_excerpt' => 'Now shortlisted for deeper review.',
            'relevance_score' => 92,
            'lead_status' => 'shortlisted',
            'discovered_at' => '2026-04-23',
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->assertDatabaseHas('job_leads', [
        'id' => $jobLead->id,
        'company_name' => 'Northwind Labs',
        'job_title' => 'Staff Product Engineer',
        'lead_status' => 'shortlisted',
    ]);

    $this->actingAs($user)
        ->delete(route('job-leads.destroy', $jobLead))
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_lead_edit.delete_success'));

    $this->assertDatabaseMissing('job_leads', [
        'id' => $jobLead->id,
    ]);
});

it('shows only the authenticated users job leads', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Visible Lead Co',
        'job_title' => 'Visible Role',
    ]);

    JobLead::factory()->for($otherUser)->create([
        'company_name' => 'Hidden Lead Co',
        'job_title' => 'Hidden Role',
    ]);

    $this->actingAs($user)
        ->get(route('job-leads.index'))
        ->assertOk()
        ->assertSee('Visible Lead Co')
        ->assertDontSee('Hidden Lead Co');
});
