<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\UserProfile;
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
            ...$this->sharedPageProps(),
            'hasResumeProfile' => $userProfile !== null,
            'userProfile' => $this->userProfileData($userProfile),
        ]);
    }

    public function create(Request $request): Response
    {
        $userProfile = UserProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return Inertia::render('Profile/CreateResume', [
            ...$this->sharedPageProps(),
            'hasResumeProfile' => $userProfile !== null,
            'userProfile' => $this->userProfileData($userProfile),
        ]);
    }

    public function store(StoreUserProfileRequest $request): RedirectResponse
    {
        UserProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $this->userProfilePayload($request->validated(), $request->user()->id),
        );

        return redirect()
            ->route('resume-profile.show')
            ->with('success', 'Resume setup saved successfully.');
    }

    public function update(UpdateUserProfileRequest $request): RedirectResponse
    {
        UserProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $this->userProfilePayload($request->validated(), $request->user()->id),
        );

        return redirect()
            ->route('resume-profile.show')
            ->with('success', 'Resume setup updated successfully.');
    }

    /**
     * @param array<string, mixed> $validatedData
     * @return array<string, mixed>
     */
    private function userProfilePayload(array $validatedData, int $userId): array
    {
        $uploadedResumeFile = $validatedData['resume_file'] ?? null;
        $storedResumeText = $this->storedResumeText($uploadedResumeFile, $validatedData['base_resume_text'] ?? null);

        if ($uploadedResumeFile instanceof UploadedFile) {
            $this->storeResumeFile($uploadedResumeFile, $userId);
        }

        return array_filter([
            'user_id' => $userId,
            'target_role' => $this->nullableString($validatedData['target_role'] ?? null),
            'professional_summary' => $this->nullableString($validatedData['professional_summary'] ?? null),
            'core_skills' => $this->coreSkills($validatedData['core_skills'] ?? null),
            'work_experience_text' => $this->nullableString($validatedData['work_experience_text'] ?? null),
            'education_text' => $this->nullableString($validatedData['education_text'] ?? null),
            'certifications_text' => $this->nullableString($validatedData['certifications_text'] ?? null),
            'languages_text' => $this->nullableString($validatedData['languages_text'] ?? null),
            'base_resume_text' => $storedResumeText,
        ], fn (mixed $value): bool => $value !== null);
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
            'uploaded_resume' => $this->uploadedResumeData($userProfile->user_id),
        ];
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

        if ($uploadedResumeFile->getClientOriginalExtension() !== 'txt') {
            return $this->nullableString($fallbackResumeText);
        }

        $contents = file_get_contents($uploadedResumeFile->getRealPath());

        if ($contents === false) {
            return $this->nullableString($fallbackResumeText);
        }

        return $this->nullableString($contents);
    }

    private function storeResumeFile(UploadedFile $uploadedResumeFile, int $userId): void
    {
        $directory = "resume-uploads/{$userId}";
        Storage::disk('local')->deleteDirectory($directory);
        $uploadedResumeFile->storeAs(
            $directory,
            'current.'.$uploadedResumeFile->getClientOriginalExtension(),
            'local',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadedResumeData(int $userId): ?array
    {
        $files = Storage::disk('local')->files("resume-uploads/{$userId}");

        if ($files === []) {
            return null;
        }

        $path = $files[0];

        return [
            'filename' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'size' => Storage::disk('local')->size($path),
            'last_modified' => Storage::disk('local')->lastModified($path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedPageProps(): array
    {
        return [
            'availableLocales' => ['pt', 'en', 'es'],
            'locale' => app()->getLocale(),
            'translations' => trans('app'),
        ];
    }
}
