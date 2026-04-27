<?php

use App\Models\JobLead;
use Tests\TestCase;

uses(TestCase::class);

it('creates saved job leads by default', function (): void {
    $jobLead = JobLead::factory()->make([
        'user_id' => 1,
    ]);

    expect($jobLead->lead_status)->toBe(JobLead::STATUS_SAVED);
});

it('supports explicit lead status states', function (): void {
    expect(JobLead::factory()->saved()->make(['user_id' => 1])->lead_status)->toBe(JobLead::STATUS_SAVED)
        ->and(JobLead::factory()->shortlisted()->make(['user_id' => 1])->lead_status)->toBe(JobLead::STATUS_SHORTLISTED)
        ->and(JobLead::factory()->applied()->make(['user_id' => 1])->lead_status)->toBe(JobLead::STATUS_APPLIED)
        ->and(JobLead::factory()->ignored()->make(['user_id' => 1])->lead_status)->toBe(JobLead::STATUS_IGNORED);
});
