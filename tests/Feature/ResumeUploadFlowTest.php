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
        ->assertRedirect(route('resume-profile.show'))
        ->assertSessionHas('success', __('app.resume.save_success'));

    Storage::disk('local')->assertExists("resume-uploads/{$user->id}/current.txt");

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->base_resume_text)->toBe('Laravel Vue SQL AWS')
        ->and($userProfile->resume_file_path)->toBe("resume-uploads/{$user->id}/current.txt")
        ->and($userProfile->resume_file_name)->toBe('resume.txt')
        ->and($userProfile->resume_file_mime)->toBe('text/plain')
        ->and($userProfile->resume_file_size)->toBeGreaterThan(0);

    $this->actingAs($user)
        ->get(route('resume-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ResumeProfile')
            ->where('userProfile.uploaded_resume.filename', 'resume.txt')
            ->where('userProfile.uploaded_resume.path', "resume-uploads/{$user->id}/current.txt")
            ->where('userProfile.base_resume_text', 'Laravel Vue SQL AWS')
        );
});

it('replaces persisted resume metadata when a new file is uploaded', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('resume-profile.store'), [
        'resume_file' => UploadedFile::fake()->createWithContent('resume.txt', 'First resume'),
    ]);

    $this->actingAs($user)->patch(route('resume-profile.update'), [
        'resume_file' => UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('resume-profile.show'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->resume_file_path)->toBe("resume-uploads/{$user->id}/current.pdf")
        ->and($userProfile->resume_file_name)->toBe('resume.pdf')
        ->and($userProfile->resume_file_mime)->toBe('application/pdf')
        ->and($userProfile->resume_file_size)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists("resume-uploads/{$user->id}/current.pdf");
});

it('clears stale resume text when a non-text upload cannot extract new resume content', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    UserProfile::query()->create([
        'user_id' => $user->id,
        'base_resume_text' => 'Old resume text',
    ]);

    $this->actingAs($user)->patch(route('resume-profile.update'), [
        'base_resume_text' => '',
        'resume_file' => UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('resume-profile.show'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->base_resume_text)->toBeNull()
        ->and($userProfile->resume_file_name)->toBe('resume.pdf');
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
