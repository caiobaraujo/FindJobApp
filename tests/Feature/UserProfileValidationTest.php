<?php

use App\Models\User;

it('validates resume profile fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('resume-profile.store'), [
            'target_role' => str_repeat('a', 256),
            'core_skills' => str_repeat('b', 5001),
        ])
        ->assertSessionHasErrors([
            'target_role',
            'core_skills',
        ]);
});
