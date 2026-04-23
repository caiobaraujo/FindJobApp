<?php

use App\Models\User;
use App\Models\JobLead;
use Inertia\Testing\AssertableInertia as Assert;

it('updates the locale in session and serves translated main page props', function (): void {
    $user = User::factory()->create();
    JobLead::factory()->create([
        'user_id' => $user->id,
        'company_name' => 'Acme',
        'job_title' => 'Platform Engineer',
        'source_url' => 'https://example.com/jobs/1',
        'extracted_keywords' => ['laravel'],
    ]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.switch'), [
            'locale' => 'pt',
        ])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas('locale', 'pt')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('locale', 'pt')
            ->where('translations.nav.dashboard', 'Painel')
            ->where('translations.nav.resume', 'Currículo')
            ->where('translations.dashboard.start_title', 'Comece aqui')
            ->where('translations.buttons.view_matched_jobs', 'Ver vagas compatíveis')
        );

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('locale', 'pt')
            ->where('translations.matched_jobs.title', 'Vagas compatíveis')
            ->where('translations.matched_jobs.search', 'Buscar')
        );

    $this->actingAs($user)
        ->get(route('applications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Index')
            ->where('locale', 'pt')
            ->where('translations.applications.title', 'Candidaturas')
            ->where('translations.applications.filter_title', 'Filtrar pipeline')
        );
});

it('includes locale data for authenticated navigation pages', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->where('locale', app()->getLocale())
            ->where('availableLocales.0', 'pt')
            ->where('availableLocales.1', 'en')
            ->where('availableLocales.2', 'es')
        );

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('locale', app()->getLocale())
            ->where('translations.profile.title', 'Profile')
        );
});

it('keeps locale switching working after header layout changes', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('resume-profile.show'))
        ->post(route('locale.switch'), [
            'locale' => 'es',
        ])
        ->assertRedirect(route('resume-profile.show'));

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertSessionHas('locale', 'es')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->where('locale', 'es')
            ->where('translations.nav.resume', 'Currículum')
        );

    $this->actingAs($user)
        ->get(route('applications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Create')
            ->where('locale', 'es')
            ->where('translations.applications.title', 'Postulaciones')
            ->where('translations.buttons.create_application', 'Crear postulación')
        );
});
