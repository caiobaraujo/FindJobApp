<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\UserProfile;
use App\Services\JobLeadKeywordExtractor;
use App\Services\ResumeTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $userProfile = UserProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return Inertia::render('Profile/ResumeProfile', [
            'hasResumeProfile' => $userProfile !== null,
            'detectedResumeSkills' => $this->detectedResumeSkills($userProfile),
            'userProfile' => $this->userProfileData($userProfile),
        ]);
    }

    public function create(Request $request): Response
    {
        $userProfile = UserProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return Inertia::render('Profile/CreateResume', [
            'hasResumeProfile' => $userProfile !== null,
            'detectedResumeSkills' => $this->detectedResumeSkills($userProfile),
            'userProfile' => $this->userProfileData($userProfile),
        ]);
    }

    public function store(StoreUserProfileRequest $request): RedirectResponse
    {
        $userProfile = UserProfile::query()->firstOrNew([
            'user_id' => $request->user()->id,
        ]);

        $this->saveUserProfile(
            $userProfile,
            $this->userProfilePayload($request->validated(), $request->user()->id),
        );

        return redirect()
            ->route('resume-profile.show')
            ->with('success', __('app.resume.save_success'));
    }

    public function update(UpdateUserProfileRequest $request): RedirectResponse
    {
        $userProfile = UserProfile::query()->firstOrNew([
            'user_id' => $request->user()->id,
        ]);

        $this->saveUserProfile(
            $userProfile,
            $this->userProfilePayload($request->validated(), $request->user()->id),
        );

        return redirect()
            ->route('resume-profile.show')
            ->with('success', __('app.resume.update_success'));
    }

    /**
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function userProfilePayload(array $validatedData, int $userId): array
    {
        $uploadedResumeFile = $validatedData['resume_file'] ?? null;
        $storedResumeText = $this->storedResumeText($uploadedResumeFile, $validatedData['base_resume_text'] ?? null);
        $resumeFileMetadata = $this->resumeFileMetadata($uploadedResumeFile, $userId);

        if ($uploadedResumeFile instanceof UploadedFile) {
            $this->storeResumeFile($uploadedResumeFile, $resumeFileMetadata['resume_file_path']);
        }

        $payload = [
            'user_id' => $userId,
            'target_role' => $this->nullableString($validatedData['target_role'] ?? null),
            'professional_summary' => $this->nullableString($validatedData['professional_summary'] ?? null),
            'core_skills' => $this->coreSkills($validatedData['core_skills'] ?? null),
            'work_experience_text' => $this->nullableString($validatedData['work_experience_text'] ?? null),
            'education_text' => $this->nullableString($validatedData['education_text'] ?? null),
            'certifications_text' => $this->nullableString($validatedData['certifications_text'] ?? null),
            'languages_text' => $this->nullableString($validatedData['languages_text'] ?? null),
            'base_resume_text' => $storedResumeText,
        ];

        if ($resumeFileMetadata === []) {
            return $payload;
        }

        return [
            ...$payload,
            ...$resumeFileMetadata,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userProfileData(?UserProfile $userProfile): ?array
    {
        if ($userProfile === null) {
            return null;
        }

        return [
            'target_role' => $userProfile->target_role,
            'professional_summary' => $userProfile->professional_summary,
            'core_skills' => $userProfile->core_skills ?? [],
            'work_experience_text' => $userProfile->work_experience_text,
            'education_text' => $userProfile->education_text,
            'certifications_text' => $userProfile->certifications_text,
            'languages_text' => $userProfile->languages_text,
            'base_resume_text' => $userProfile->base_resume_text,
            'uploaded_resume' => $this->uploadedResumeData($userProfile),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function saveUserProfile(UserProfile $userProfile, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $userProfile->{$key} = $value;
        }

        $userProfile->save();
    }

    /**
     * @return list<string>|null
     */
    private function coreSkills(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $skills = preg_split('/[\n,]+/', $value) ?: [];
        $skills = array_values(array_unique(array_filter(array_map(
            fn (string $skill): ?string => $this->nullableString($skill),
            $skills,
        ))));

        if ($skills === []) {
            return null;
        }

        return $skills;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return null;
        }

        return $trimmedValue;
    }

    private function storedResumeText(mixed $uploadedResumeFile, mixed $fallbackResumeText): ?string
    {
        if (! $uploadedResumeFile instanceof UploadedFile) {
            return $this->nullableString($fallbackResumeText);
        }

        $extractedText = app(ResumeTextExtractor::class)->extract($uploadedResumeFile);

        if ($extractedText === null) {
            return $this->nullableString($fallbackResumeText);
        }

        return $extractedText;
    }

    private function storeResumeFile(UploadedFile $uploadedResumeFile, string $path): void
    {
        Storage::disk('local')->deleteDirectory(dirname($path));

        $uploadedResumeFile->storeAs(
            dirname($path),
            basename($path),
            'local',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadedResumeData(UserProfile $userProfile): ?array
    {
        if ($userProfile->resume_file_path === null || $userProfile->resume_file_name === null) {
            return null;
        }

        return [
            'filename' => $userProfile->resume_file_name,
            'mime' => $userProfile->resume_file_mime,
            'size' => $userProfile->resume_file_size,
            'path' => $userProfile->resume_file_path,
            'uploaded' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumeFileMetadata(mixed $uploadedResumeFile, int $userId): array
    {
        if (! $uploadedResumeFile instanceof UploadedFile) {
            return [];
        }

        $filename = 'current.'.$uploadedResumeFile->getClientOriginalExtension();

        return [
            'resume_file_path' => "resume-uploads/{$userId}/{$filename}",
            'resume_file_name' => $uploadedResumeFile->getClientOriginalName(),
            'resume_file_mime' => $uploadedResumeFile->getClientMimeType(),
            'resume_file_size' => $uploadedResumeFile->getSize(),
        ];
    }

    /**
     * @return list<string>
     */
    private function detectedResumeSkills(?UserProfile $userProfile): array
    {
        if ($userProfile === null) {
            return [];
        }

        $skills = [];

        foreach ($userProfile->core_skills ?? [] as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $normalizedSkill = $this->nullableString($skill);

            if ($normalizedSkill === null) {
                continue;
            }

            $skills[] = mb_strtolower($normalizedSkill);
        }

        if (filled($userProfile->base_resume_text)) {
            $analysis = app(JobLeadKeywordExtractor::class)->analyze($userProfile->base_resume_text);

            foreach ($analysis['extracted_keywords'] as $keyword) {
                if (! is_string($keyword)) {
                    continue;
                }

                $skills[] = $keyword;
            }
        }

        return array_slice(array_values(array_unique($skills)), 0, 10);
    }
}
