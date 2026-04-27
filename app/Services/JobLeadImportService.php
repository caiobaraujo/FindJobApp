<?php

namespace App\Services;

use App\Models\JobLead;

class JobLeadImportService
{
    public const STATUS_CREATED = 'created';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_INVALID = 'invalid';

    /**
     * @param array<string, mixed> $attributes
     * @return array{status: string, job_lead: JobLead|null}
     */
    public function importForUser(int $userId, string $sourceUrl, array $attributes = []): array
    {
        if (! $this->isImportableUrl($sourceUrl)) {
            return [
                'status' => self::STATUS_INVALID,
                'job_lead' => null,
            ];
        }

        $normalizedSourceUrl = $this->normalizedSourceUrl($sourceUrl);

        if ($normalizedSourceUrl === null) {
            return [
                'status' => self::STATUS_INVALID,
                'job_lead' => null,
            ];
        }

        $duplicateJobLead = JobLead::query()
            ->where('user_id', $userId)
            ->where('normalized_source_url', $normalizedSourceUrl)
            ->first();

        if ($duplicateJobLead !== null) {
            return [
                'status' => self::STATUS_DUPLICATE,
                'job_lead' => $duplicateJobLead,
            ];
        }

        $descriptionText = $this->nullableString($attributes['description_text'] ?? null);
        $analysis = app(JobLeadKeywordExtractor::class)->analyze($descriptionText);
        $fallbackCompanyName = $this->nullableString($attributes['fallback_company_name'] ?? null)
            ?? $this->fallbackCompanyName($sourceUrl);
        $defaultJobTitle = $this->nullableString($attributes['default_job_title'] ?? null)
            ?? 'Imported job';

        $jobLead = JobLead::query()->create(array_filter([
            'user_id' => $userId,
            'source_url' => $sourceUrl,
            'normalized_source_url' => $normalizedSourceUrl,
            'source_host' => $this->sourceHost($normalizedSourceUrl),
            'source_name' => $this->nullableString($attributes['source_name'] ?? null),
            'company_name' => $this->nullableString($attributes['company_name'] ?? null) ?? $fallbackCompanyName,
            'job_title' => $this->nullableString($attributes['job_title'] ?? null) ?? $defaultJobTitle,
            'location' => $this->nullableString($attributes['location'] ?? null),
            'work_mode' => $this->nullableString($attributes['work_mode'] ?? null),
            'salary_range' => $this->nullableString($attributes['salary_range'] ?? null),
            'description_excerpt' => $this->nullableString($attributes['description_excerpt'] ?? null),
            'description_text' => $descriptionText,
            'extracted_keywords' => $analysis['extracted_keywords'],
            'ats_hints' => $analysis['ats_hints'],
            'relevance_score' => $this->nullableInt($attributes['relevance_score'] ?? null),
            'lead_status' => $this->nullableString($attributes['lead_status'] ?? null) ?? JobLead::STATUS_SAVED,
            'discovered_at' => $this->nullableString($attributes['discovered_at'] ?? null) ?? today()->toDateString(),
        ], fn (mixed $value): bool => $value !== null));

        return [
            'status' => self::STATUS_CREATED,
            'job_lead' => $jobLead,
        ];
    }

    private function isImportableUrl(string $sourceUrl): bool
    {
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($sourceUrl, PHP_URL_SCHEME);

        return in_array(strtolower((string) $scheme), ['http', 'https'], true);
    }

    private function normalizedSourceUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null || trim($sourceUrl) === '') {
            return null;
        }

        $parts = parse_url($sourceUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ($scheme === '' || $host === '') {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/+#', '/', $path);
        $path = is_string($path) ? $path : '/';

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function sourceHost(string $normalizedSourceUrl): ?string
    {
        $host = parse_url($normalizedSourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host)) ?? strtolower($host);
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

    private function fallbackCompanyName(?string $sourceUrl): string
    {
        if ($sourceUrl === null || $sourceUrl === '') {
            return 'Imported company';
        }

        $host = parse_url($sourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'Imported company';
        }

        $segments = explode('.', preg_replace('/^www\./', '', strtolower($host)) ?? strtolower($host));
        $primarySegment = $segments[0] ?? '';

        if (in_array($primarySegment, ['jobs', 'careers', 'boards', 'apply'], true) && count($segments) >= 2) {
            $primarySegment = $segments[count($segments) - 2];
        }

        $words = preg_split('/[-_]+/', $primarySegment) ?: [];
        $words = array_filter(array_map(
            fn (string $word): ?string => $this->nullableString($word),
            $words,
        ));

        if ($words === []) {
            return 'Imported company';
        }

        return implode(' ', array_map(
            fn (string $word): string => ucfirst(strtolower($word)),
            $words,
        ));
    }

    private function nullableInt(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        return (int) $value;
    }
}
