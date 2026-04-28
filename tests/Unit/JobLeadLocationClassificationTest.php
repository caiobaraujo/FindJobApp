<?php

use App\Models\JobLead;

it('classifies explicit brazil signals as brazil', function (): void {
    $jobLead = new JobLead([
        'location' => 'Hybrid - Belo Horizonte, Brazil',
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_BRAZIL);
});

it('classifies explicit international signals as international', function (): void {
    $jobLead = new JobLead([
        'location' => 'Remote - Worldwide',
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('treats latam as international unless brazil is explicit', function (): void {
    $jobLead = new JobLead([
        'location' => 'Remote - LATAM',
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('does not invent location classification when there is not enough data', function (): void {
    $jobLead = new JobLead([
        'location' => 'Remote',
        'source_context_text' => null,
        'description_excerpt' => null,
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_UNKNOWN);
});

it('prefers explicit brazil signals over international text in broader source context', function (): void {
    $jobLead = new JobLead([
        'location' => 'Remote Brazil',
        'source_context_text' => 'Open to LATAM candidates.',
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_BRAZIL);
});

it('classifies we work remotely leads with no location as international by source fallback', function (): void {
    $jobLead = new JobLead([
        'source_name' => 'We Work Remotely',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('classifies remotive leads with no location as international by source fallback', function (): void {
    $jobLead = new JobLead([
        'source_name' => 'Remotive',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('classifies python job board leads with no brazil signal as international by source fallback', function (): void {
    $jobLead = new JobLead([
        'source_name' => 'Python Job Board',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('classifies django community jobs leads with no brazil signal as international by source fallback', function (): void {
    $jobLead = new JobLead([
        'source_name' => 'Django Community Jobs',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('classifies larajobs leads with no brazil signal as international by source fallback', function (): void {
    $jobLead = new JobLead([
        'source_name' => 'LaraJobs',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => null,
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL);
});

it('keeps explicit brazil text ahead of an international source fallback', function (): void {
    $jobLead = new JobLead([
        'source_name' => 'Remotive',
        'source_type' => JobLead::SOURCE_TYPE_JOB_BOARD,
        'location' => 'Remote Brazil',
    ]);

    expect($jobLead->locationClassification())->toBe(JobLead::LOCATION_CLASSIFICATION_BRAZIL);
});
