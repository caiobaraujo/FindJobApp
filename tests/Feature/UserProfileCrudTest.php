<?php

use App\Models\User;
use App\Models\UserProfile;

it('allows a user to create and update a resume profile', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('resume-profile.store'), [
            'target_role' => 'Senior Product Engineer',
            'target_roles' => "Product Engineer\nPlatform Engineer",
            'preferred_locations' => "Remote\nSao Paulo, Brazil",
            'preferred_work_modes' => ['remote', 'hybrid'],
            'auto_discover_jobs' => '1',
            'professional_summary' => 'Builder focused on product-minded engineering work.',
            'core_skills' => 'Laravel, Vue, SQL, AWS',
            'work_experience_text' => 'Led delivery across platform and product squads.',
            'education_text' => 'BSc in Computer Science',
            'certifications_text' => 'AWS Solutions Architect',
            'languages_text' => 'English, Portuguese',
            'base_resume_text' => 'Laravel engineer with Vue and SQL experience.',
        ])
        ->assertRedirect(route('resume-profile.show'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->core_skills)->toBe(['Laravel', 'Vue', 'SQL', 'AWS']);
    expect($userProfile->target_roles)->toBe(['Product Engineer', 'Platform Engineer']);
    expect($userProfile->preferred_locations)->toBe(['Remote', 'Sao Paulo, Brazil']);
    expect($userProfile->preferred_work_modes)->toBe(['remote', 'hybrid']);
    expect($userProfile->auto_discover_jobs)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('resume-profile.update'), [
            'target_role' => 'Staff Product Engineer',
            'target_roles' => 'Staff Product Engineer, Engineering Manager',
            'preferred_locations' => "Lisbon, Portugal\nRemote",
            'preferred_work_modes' => ['remote'],
            'auto_discover_jobs' => false,
            'professional_summary' => 'Updated summary',
            'core_skills' => "Laravel\nVue\nPostgreSQL",
            'work_experience_text' => 'Updated experience',
            'education_text' => 'Updated education',
            'certifications_text' => 'Updated certifications',
            'languages_text' => 'English',
            'base_resume_text' => 'Updated resume text',
        ])
        ->assertRedirect(route('resume-profile.show'));

    $userProfile->refresh();

    expect($userProfile->target_role)->toBe('Staff Product Engineer');
    expect($userProfile->core_skills)->toBe(['Laravel', 'Vue', 'PostgreSQL']);
    expect($userProfile->target_roles)->toBe(['Staff Product Engineer', 'Engineering Manager']);
    expect($userProfile->preferred_locations)->toBe(['Lisbon, Portugal', 'Remote']);
    expect($userProfile->preferred_work_modes)->toBe(['remote']);
    expect($userProfile->auto_discover_jobs)->toBeFalse();

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertSee('Staff Product Engineer')
        ->assertSee('Engineering Manager')
        ->assertSee('Remote')
        ->assertSee(__('app.resume.auto_discover_jobs'));
});

it('keeps resume profiles isolated per authenticated user', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $owner->id,
        'target_role' => 'Owner Role',
        'target_roles' => ['Owner Target'],
        'preferred_locations' => ['Owner City'],
        'preferred_work_modes' => ['onsite'],
        'auto_discover_jobs' => true,
        'core_skills' => ['Laravel'],
        'base_resume_text' => 'Owner resume',
    ]);

    UserProfile::query()->create([
        'user_id' => $otherUser->id,
        'target_role' => 'Other Role',
        'target_roles' => ['Other Target'],
        'preferred_locations' => ['Remote'],
        'preferred_work_modes' => ['remote'],
        'auto_discover_jobs' => false,
        'core_skills' => ['Python'],
        'base_resume_text' => 'Other resume',
    ]);

    $this->actingAs($otherUser)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertSee('Other Role')
        ->assertDontSee('Owner Role');

    $this->actingAs($otherUser)
        ->patch(route('resume-profile.update'), [
            'target_role' => 'Changed Other Role',
            'target_roles' => 'Changed Target',
            'preferred_locations' => 'Berlin',
            'preferred_work_modes' => ['hybrid'],
            'auto_discover_jobs' => true,
            'core_skills' => 'Python, SQL',
            'base_resume_text' => 'Changed other resume',
        ])
        ->assertRedirect(route('resume-profile.show'));

    expect(UserProfile::query()->where('user_id', $owner->id)->sole()->target_role)->toBe('Owner Role');
    expect(UserProfile::query()->where('user_id', $owner->id)->sole()->target_roles)->toBe(['Owner Target']);
    expect(UserProfile::query()->where('user_id', $owner->id)->sole()->preferred_locations)->toBe(['Owner City']);
    expect(UserProfile::query()->where('user_id', $owner->id)->sole()->preferred_work_modes)->toBe(['onsite']);
    expect(UserProfile::query()->where('user_id', $owner->id)->sole()->auto_discover_jobs)->toBeTrue();
});
