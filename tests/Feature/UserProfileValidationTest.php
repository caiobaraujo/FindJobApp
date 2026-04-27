<?php

use App\Models\User;

it('validates resume profile fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('resume-profile.store'), [
            'target_role' => str_repeat('a', 256),
            'core_skills' => str_repeat('b', 5001),
            'preferred_work_modes' => ['remote', 'invalid-mode'],
            'auto_discover_jobs' => 'not-a-boolean',
        ])
        ->assertSessionHasErrors([
            'target_role',
            'core_skills',
            'preferred_work_modes.1',
            'auto_discover_jobs',
        ]);
});
