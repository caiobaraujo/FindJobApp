<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('updates the locale in session and serves translated app props', function (): void {
    $user = User::factory()->create();

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
});
