<?php

use App\Models\JobLead;
use App\Models\User;

it('validates required job lead fields on create', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'company_name' => '',
            'job_title' => '',
            'source_url' => '',
            'lead_status' => '',
        ])
        ->assertSessionHasErrors([
            'company_name',
            'job_title',
            'source_url',
            'lead_status',
        ]);
});

it('validates lead status and work mode values on create', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'company_name' => 'Acme',
            'job_title' => 'Engineer',
            'source_url' => 'https://example.com/job',
            'lead_status' => 'invalid-status',
            'work_mode' => 'invalid-mode',
        ])
        ->assertSessionHasErrors([
            'lead_status',
            'work_mode',
        ]);
});

it('validates lead status on update', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create();

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
            'relevance_score' => $jobLead->relevance_score,
            'lead_status' => 'invalid-status',
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
        ])
        ->assertSessionHasErrors(['lead_status']);
});
