<?php

use App\Models\JobLead;
use App\Models\User;

it('prevents a user from editing another users job lead', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $jobLead = JobLead::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('job-leads.edit', $jobLead))
        ->assertForbidden();
});

it('prevents a user from updating another users job lead', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $jobLead = JobLead::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->patch(route('job-leads.update', $jobLead), [
            'company_name' => 'Hacked Lead',
            'job_title' => 'Hacked Role',
            'source_name' => 'Hacked Source',
            'source_url' => 'https://example.com/hacked',
            'location' => 'Hidden',
            'work_mode' => 'remote',
            'salary_range' => '$1',
            'description_excerpt' => 'Should not update.',
            'relevance_score' => 1,
            'lead_status' => 'ignored',
            'discovered_at' => '2026-04-22',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('job_leads', [
        'id' => $jobLead->id,
        'company_name' => 'Hacked Lead',
    ]);
});

it('prevents a user from deleting another users job lead', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $jobLead = JobLead::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('job-leads.destroy', $jobLead))
        ->assertForbidden();

    $this->assertDatabaseHas('job_leads', [
        'id' => $jobLead->id,
    ]);
});
