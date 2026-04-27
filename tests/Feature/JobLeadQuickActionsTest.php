<?php

use App\Models\JobLead;
use App\Models\User;

it('allows the owner to update a job lead status with a quick action', function (string $leadStatus): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->saved()->create([
        'company_name' => 'Northwind',
        'job_title' => 'Senior Product Engineer',
        'source_url' => 'https://example.com/jobs/senior-product-engineer',
    ]);

    $this->actingAs($user)
        ->from(route('matched-jobs.index'))
        ->patch(route('job-leads.update', $jobLead), [
            'lead_status' => $leadStatus,
            'stay_on_page' => true,
        ])
        ->assertRedirect(route('matched-jobs.index'))
        ->assertSessionHas('success', __('app.job_lead_edit.update_success'));

    $this->assertDatabaseHas('job_leads', [
        'id' => $jobLead->id,
        'company_name' => 'Northwind',
        'job_title' => 'Senior Product Engineer',
        'source_url' => 'https://example.com/jobs/senior-product-engineer',
        'lead_status' => $leadStatus,
    ]);
})->with([
    JobLead::STATUS_SAVED,
    JobLead::STATUS_SHORTLISTED,
    JobLead::STATUS_APPLIED,
    JobLead::STATUS_IGNORED,
]);

it('prevents a user from using quick actions on another users job lead', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $jobLead = JobLead::factory()->for($owner)->saved()->create();

    $this->actingAs($intruder)
        ->from(route('matched-jobs.index'))
        ->patch(route('job-leads.update', $jobLead), [
            'lead_status' => JobLead::STATUS_IGNORED,
            'stay_on_page' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('job_leads', [
        'id' => $jobLead->id,
        'lead_status' => JobLead::STATUS_SAVED,
    ]);
});
