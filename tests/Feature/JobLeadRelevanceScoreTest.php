<?php

use App\Models\JobLead;
use App\Models\User;

it('allows a user to create a lead with a valid relevance score', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'company_name' => 'Northwind',
            'job_title' => 'Platform Engineer',
            'source_name' => 'LinkedIn',
            'source_url' => 'https://example.com/jobs/platform-engineer',
            'lead_status' => 'saved',
            'relevance_score' => 91,
        ])
        ->assertRedirect(route('job-leads.index'));

    $this->assertDatabaseHas('job_leads', [
        'user_id' => $user->id,
        'company_name' => 'Northwind',
        'job_title' => 'Platform Engineer',
        'relevance_score' => 91,
    ]);
});

it('rejects invalid relevance scores', function (): void {
    $user = User::factory()->create();
    $jobLead = JobLead::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('job-leads.store'), [
            'company_name' => 'Acme',
            'job_title' => 'Engineer',
            'source_url' => 'https://example.com/jobs/engineer',
            'lead_status' => 'saved',
            'relevance_score' => 101,
        ])
        ->assertSessionHasErrors(['relevance_score']);

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
            'lead_status' => $jobLead->lead_status,
            'discovered_at' => optional($jobLead->discovered_at)->toDateString(),
            'relevance_score' => -1,
        ])
        ->assertSessionHasErrors(['relevance_score']);
});

it('orders job leads by highest relevance score first', function (): void {
    $user = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Lower Score Co',
        'job_title' => 'Backend Engineer',
        'relevance_score' => 40,
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Highest Score Co',
        'job_title' => 'Staff Engineer',
        'relevance_score' => 95,
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Middle Score Co',
        'job_title' => 'Product Engineer',
        'relevance_score' => 75,
    ]);

    $response = $this->actingAs($user)->get(route('job-leads.index'));

    $response->assertOk();

    $content = $response->getContent();

    expect(strpos($content, 'Highest Score Co'))->toBeLessThan(strpos($content, 'Middle Score Co'));
    expect(strpos($content, 'Middle Score Co'))->toBeLessThan(strpos($content, 'Lower Score Co'));
});

it('orders null relevance scores after scored leads and keeps user isolation', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Scored Lead Co',
        'job_title' => 'Growth Engineer',
        'relevance_score' => 82,
    ]);

    JobLead::factory()->for($user)->create([
        'company_name' => 'Unscored Lead Co',
        'job_title' => 'Operations Analyst',
        'relevance_score' => null,
    ]);

    JobLead::factory()->for($otherUser)->create([
        'company_name' => 'Hidden Lead Co',
        'job_title' => 'Hidden Role',
        'relevance_score' => 99,
    ]);

    $response = $this->actingAs($user)->get(route('job-leads.index'));

    $response
        ->assertOk()
        ->assertSee('Scored Lead Co')
        ->assertSee('Unscored Lead Co')
        ->assertDontSee('Hidden Lead Co');

    $content = $response->getContent();

    expect(strpos($content, 'Scored Lead Co'))->toBeLessThan(strpos($content, 'Unscored Lead Co'));
});
