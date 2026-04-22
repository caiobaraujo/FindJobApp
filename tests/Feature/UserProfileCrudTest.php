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

    $this->actingAs($user)
        ->patch(route('resume-profile.update'), [
            'target_role' => 'Staff Product Engineer',
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
});

it('keeps resume profiles isolated per authenticated user', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $owner->id,
        'target_role' => 'Owner Role',
        'core_skills' => ['Laravel'],
        'base_resume_text' => 'Owner resume',
    ]);

    UserProfile::query()->create([
        'user_id' => $otherUser->id,
        'target_role' => 'Other Role',
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
            'core_skills' => 'Python, SQL',
            'base_resume_text' => 'Changed other resume',
        ])
        ->assertRedirect(route('resume-profile.show'));

    expect(UserProfile::query()->where('user_id', $owner->id)->sole()->target_role)->toBe('Owner Role');
});
