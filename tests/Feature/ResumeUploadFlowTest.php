<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('allows an authenticated user to upload a valid resume file', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    $resumeFile = UploadedFile::fake()->createWithContent('resume.txt', 'Laravel Vue SQL AWS');

    $this->actingAs($user)
        ->post(route('resume-profile.store'), [
            'resume_file' => $resumeFile,
        ])
        ->assertRedirect(route('resume-profile.show'));

    Storage::disk('local')->assertExists("resume-uploads/{$user->id}/current.txt");

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->base_resume_text)->toBe('Laravel Vue SQL AWS');
});

it('rejects an invalid resume file type', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    $invalidResumeFile = UploadedFile::fake()->image('resume.png');

    $this->actingAs($user)
        ->post(route('resume-profile.store'), [
            'resume_file' => $invalidResumeFile,
        ])
        ->assertSessionHasErrors(['resume_file']);
});

it('renders the create resume fallback page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('resume-profile.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/CreateResume')
        );
});
