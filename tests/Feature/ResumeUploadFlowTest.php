<?php

use App\Models\JobLead;
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

it('extracts resume text from a docx upload', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('resume-profile.store'), [
        'resume_file' => fakeDocxResumeUpload('resume.docx', 'Laravel Vue SQL AWS'),
    ])->assertRedirect(route('resume-profile.show'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->base_resume_text)->toBe('Laravel Vue SQL AWS')
        ->and($userProfile->resume_file_path)->toBe("resume-uploads/{$user->id}/current.docx")
        ->and($userProfile->resume_file_name)->toBe('resume.docx');
});

it('keeps uploaded pdf metadata and manual fallback when extraction is unavailable', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('resume-profile.store'), [
        'base_resume_text' => 'Manual Laravel Vue fallback',
        'resume_file' => UploadedFile::fake()->createWithContent('resume.pdf', 'not a valid pdf document'),
    ])->assertRedirect(route('resume-profile.show'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->base_resume_text)->toBe('Manual Laravel Vue fallback')
        ->and($userProfile->resume_file_name)->toBe('resume.pdf')
        ->and($userProfile->resume_file_path)->toBe("resume-uploads/{$user->id}/current.pdf");
});

it('keeps doc uploads as honest fallback only when no manual text is provided', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('resume-profile.store'), [
        'resume_file' => UploadedFile::fake()->createWithContent('resume.doc', 'Laravel Vue SQL AWS'),
    ])->assertRedirect(route('resume-profile.show'));

    $userProfile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($userProfile->base_resume_text)->toBeNull()
        ->and($userProfile->resume_file_name)->toBe('resume.doc')
        ->and($userProfile->resume_file_path)->toBe("resume-uploads/{$user->id}/current.doc");
});

it('matches jobs from extracted uploaded resume text', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();

    JobLead::factory()->for($user)->create([
        'company_name' => 'Extracted Match Co',
        'job_title' => 'Laravel Engineer',
        'source_url' => 'https://example.com/jobs/extracted-match',
        'description_text' => 'Full description',
        'extracted_keywords' => ['laravel', 'vue'],
    ]);

    $this->actingAs($user)->post(route('resume-profile.store'), [
        'resume_file' => fakeDocxResumeUpload('resume.docx', 'Laravel engineer with Vue experience.'),
    ])->assertRedirect(route('resume-profile.show'));

    $this->actingAs($user)
        ->get(route('matched-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('JobLeads/Index')
            ->where('resumeReady', true)
            ->has('matchedJobs', 1)
            ->where('matchedJobs.0.company_name', 'Extracted Match Co')
            ->where('matchedJobs.0.matched_keywords.0', 'laravel')
        );
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

function fakeDocxResumeUpload(string $name, string $text): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'resume-upload-docx-');
    $archive = new ZipArchive();
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('[Content_Types].xml', fakeDocxContentTypesXml());
    $archive->addFromString('_rels/.rels', fakeDocxRelsXml());
    $archive->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
    $archive->close();

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

function fakeDocxContentTypesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        .'</Types>';
}

function fakeDocxRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        .'</Relationships>';
}

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
