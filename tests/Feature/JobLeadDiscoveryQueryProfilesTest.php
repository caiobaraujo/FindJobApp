<?php

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('uses the vue frontend query profile during user discovery when the job does not say frontend', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Vue.js engineer with JavaScript product experience.',
        'core_skills' => ['Vue.js'],
        'auto_discover_jobs' => false,
    ]);

    config()->set('job_discovery.supported_sources', ['larajobs']);

    Http::fake([
        'https://larajobs.com/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/larajobs_query_profile_listing.html')),
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('job-leads.discover'), [
            'search_query' => 'frontend',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('flash.discovery.0.source', 'larajobs')
            ->where('flash.discovery.0.created', 1)
            ->where('flash.discovery.0.created_by_query_profiles', 1)
            ->where('flash.discovery.0.query_profile_keys', ['frontend_vue'])
        );

    $lead = JobLead::query()->where('user_id', $user->id)->sole();

    expect($lead->job_title)->toBe('Vue.js Product Engineer');
});

it('uses backend and fullstack query profiles in user discovery with existing sources', function (): void {
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Full stack engineer working with PHP, Laravel, Python, Django, APIs and platform systems.',
        'core_skills' => ['Full Stack', 'PHP', 'Laravel', 'Python', 'Django'],
        'auto_discover_jobs' => false,
    ]);

    config()->set('job_discovery.supported_sources', ['larajobs', 'company-career-pages']);
    config()->set('job_discovery.company_career_targets', [
        [
            'name' => 'DataForge',
            'website_url' => 'https://dataforge.example.com',
            'region' => 'Belo Horizonte',
            'career_urls' => [
                'https://dataforge.example.com/carreiras',
            ],
        ],
    ]);

    Http::fake([
        'https://larajobs.com/' => Http::response(
            file_get_contents(base_path('tests/Fixtures/larajobs_query_profile_listing.html')),
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://dataforge.example.com/carreiras' => Http::response(
            file_get_contents(base_path('tests/Fixtures/company_career_page_python_django.html')),
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->actingAs($user)
        ->post(route('job-leads.discover'), [
            'search_query' => 'backend',
        ])
        ->assertRedirect(route('job-leads.index'))
        ->assertSessionHas('success', __('app.job_discovery.new_jobs_found_multiple', [
            'count' => 3,
        ]));

    expect(JobLead::query()->where('user_id', $user->id)->count())->toBe(3)
        ->and(JobLead::query()->where('user_id', $user->id)->pluck('source_url')->all())
        ->toBe([
            'https://larajobs.com/jobs/platform-works-laravel-product-engineer',
            'https://larajobs.com/jobs/stack-studio-full-stack-engineer',
            'https://dataforge.example.com/carreiras/python-django-platform',
        ])->not->toContain('https://larajobs.com/jobs/acme-vue-product-engineer');

    $discovery = session('discovery');

    expect($discovery)->toHaveCount(2)
        ->and($discovery[0]['source'])->toBe('larajobs')
        ->and($discovery[0]['created_by_query_profiles'])->toBe(2)
        ->and($discovery[0]['query_profile_keys'])->toBe(['backend_php', 'fullstack'])
        ->and($discovery[1]['source'])->toBe('company-career-pages')
        ->and($discovery[1]['created_by_query_profiles'])->toBe(1)
        ->and($discovery[1]['query_profile_keys'])->toBe(['backend_python']);
});
