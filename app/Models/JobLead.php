<?php

namespace App\Models;

use Database\Factories\JobLeadFactory;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JobLead extends Model
{
    /** @use HasFactory<JobLeadFactory> */
    use HasFactory;

    public const STATUS_SAVED = 'saved';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_IGNORED = 'ignored';

    public const SOURCE_TYPE_MANUAL = 'manual';

    public const SOURCE_TYPE_BULK = 'bulk';

    public const SOURCE_TYPE_JOB_BOARD = 'job_board';

    public const SOURCE_TYPE_POST = 'post';

    public const SOURCE_TYPE_EXTENSION = 'extension';

    public const WORK_MODE_REMOTE = 'remote';

    public const WORK_MODE_HYBRID = 'hybrid';

    public const WORK_MODE_ONSITE = 'onsite';

    public const ANALYSIS_STATE_ANALYZED = 'analyzed';

    public const ANALYSIS_STATE_MISSING = 'missing';

    public const ANALYSIS_READINESS_READY = 'ready';

    public const ANALYSIS_READINESS_NEEDS_DESCRIPTION = 'needs_description';

    public const LOCATION_SCOPE_BRAZIL = 'brazil';

    public const LOCATION_SCOPE_ALL = 'all';

    public const LOCATION_CLASSIFICATION_BRAZIL = 'brazil';

    public const LOCATION_CLASSIFICATION_INTERNATIONAL = 'international';

    public const LOCATION_CLASSIFICATION_UNKNOWN = 'unknown';

    protected $fillable = [
        'user_id',
        'company_name',
        'job_title',
        'source_name',
        'source_type',
        'source_platform',
        'source_post_url',
        'source_author',
        'source_context_text',
        'source_url',
        'normalized_source_url',
        'source_host',
        'discovery_batch_id',
        'location',
        'work_mode',
        'salary_range',
        'description_excerpt',
        'description_text',
        'extracted_keywords',
        'ats_hints',
        'relevance_score',
        'lead_status',
        'discovered_at',
    ];

    /**
     * @return list<string>
     */
    public static function leadStatuses(): array
    {
        return [
            self::STATUS_SAVED,
            self::STATUS_SHORTLISTED,
            self::STATUS_APPLIED,
            self::STATUS_IGNORED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function sourceTypes(): array
    {
        return [
            self::SOURCE_TYPE_MANUAL,
            self::SOURCE_TYPE_BULK,
            self::SOURCE_TYPE_JOB_BOARD,
            self::SOURCE_TYPE_POST,
            self::SOURCE_TYPE_EXTENSION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function workModes(): array
    {
        return [
            self::WORK_MODE_REMOTE,
            self::WORK_MODE_HYBRID,
            self::WORK_MODE_ONSITE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function analysisStates(): array
    {
        return [
            self::ANALYSIS_STATE_ANALYZED,
            self::ANALYSIS_STATE_MISSING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function analysisReadinessOptions(): array
    {
        return [
            self::ANALYSIS_READINESS_READY,
            self::ANALYSIS_READINESS_NEEDS_DESCRIPTION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function locationScopes(): array
    {
        return [
            self::LOCATION_SCOPE_BRAZIL,
            self::LOCATION_SCOPE_ALL,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discovered_at' => 'date',
            'extracted_keywords' => 'array',
            'ats_hints' => 'array',
            'relevance_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasMeaningfulAnalysis(): bool
    {
        return Str::of((string) $this->description_text)->trim()->isNotEmpty()
            && ($this->extracted_keywords ?? []) !== [];
    }

    public function hasLimitedAnalysis(): bool
    {
        return ! $this->hasMeaningfulAnalysis();
    }

    public function locationClassification(): string
    {
        $normalizedText = $this->normalizedLocationContextText();

        if ($this->containsLocationSignal($normalizedText, $this->brazilLocationPatterns())) {
            return self::LOCATION_CLASSIFICATION_BRAZIL;
        }

        if ($this->containsLocationSignal($normalizedText, $this->internationalLocationPatterns())) {
            return self::LOCATION_CLASSIFICATION_INTERNATIONAL;
        }

        $sourceFallbackClassification = $this->sourceFallbackLocationClassification();

        if ($sourceFallbackClassification !== null) {
            return $sourceFallbackClassification;
        }

        return self::LOCATION_CLASSIFICATION_UNKNOWN;
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('company_name', 'like', "%{$search}%")
                ->orWhere('job_title', 'like', "%{$search}%");
        });
    }

    public function scopeLeadStatus(Builder $query, ?string $leadStatus): Builder
    {
        if (blank($leadStatus)) {
            return $query;
        }

        return $query->where('lead_status', $leadStatus);
    }

    public function scopeVisibleInWorkspace(Builder $query, bool $showIgnored, ?string $leadStatus): Builder
    {
        if ($showIgnored || $leadStatus === self::STATUS_IGNORED) {
            return $query;
        }

        return $query->where('lead_status', '!=', self::STATUS_IGNORED);
    }

    public function scopeDiscoveryBatch(Builder $query, ?string $discoveryBatchId): Builder
    {
        if (blank($discoveryBatchId)) {
            return $query;
        }

        return $query->where('discovery_batch_id', $discoveryBatchId);
    }

    public function scopeWorkMode(Builder $query, ?string $workMode): Builder
    {
        if (blank($workMode)) {
            return $query;
        }

        return $query->where('work_mode', $workMode);
    }

    public function scopeAnalysisState(Builder $query, ?string $analysisState): Builder
    {
        if (blank($analysisState)) {
            return $query;
        }

        if ($analysisState === self::ANALYSIS_STATE_ANALYZED) {
            return $query
                ->whereNotNull('description_text')
                ->whereJsonLength('extracted_keywords', '>', 0);
        }

        if ($analysisState !== self::ANALYSIS_STATE_MISSING) {
            return $query;
        }

        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('description_text')
                ->orWhereNull('extracted_keywords')
                ->orWhereJsonLength('extracted_keywords', 0);
        });
    }

    public function scopeAnalysisReadiness(Builder $query, ?string $analysisReadiness): Builder
    {
        if (blank($analysisReadiness)) {
            return $query;
        }

        if ($analysisReadiness === self::ANALYSIS_READINESS_READY) {
            return $query
                ->whereNotNull('description_text')
                ->whereJsonLength('extracted_keywords', '>', 0);
        }

        if ($analysisReadiness !== self::ANALYSIS_READINESS_NEEDS_DESCRIPTION) {
            return $query;
        }

        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('description_text')
                ->orWhereNull('extracted_keywords')
                ->orWhereJsonLength('extracted_keywords', 0);
        });
    }

    public function scopeMinimumRelevanceScore(Builder $query, ?int $minimumRelevanceScore): Builder
    {
        if ($minimumRelevanceScore === null) {
            return $query;
        }

        return $query->whereNotNull('relevance_score')
            ->where('relevance_score', '>=', $minimumRelevanceScore);
    }

    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                "case
                    when lead_status = ? then 1
                    when lead_status = ? then 2
                    else 0
                end",
                [self::STATUS_APPLIED, self::STATUS_IGNORED],
            )
            ->orderByRaw('relevance_score IS NULL')
            ->orderByDesc('relevance_score')
            ->latest();
    }

    private function normalizedLocationContextText(): string
    {
        return Str::of(implode(' ', array_filter([
            $this->location,
            $this->source_context_text,
            $this->description_excerpt,
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '')))
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();
    }

    private function sourceFallbackLocationClassification(): ?string
    {
        $normalizedSourceName = $this->normalizedSourceValue($this->source_name);

        if (in_array($normalizedSourceName, $this->internationalSourceNames(), true)) {
            return self::LOCATION_CLASSIFICATION_INTERNATIONAL;
        }

        if ($normalizedSourceName === 'company career pages' && $this->isBrazilCareerPageLead()) {
            return self::LOCATION_CLASSIFICATION_BRAZIL;
        }

        return null;
    }

    private function normalizedSourceValue(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();
    }

    /**
     * @return list<string>
     */
    private function internationalSourceNames(): array
    {
        return [
            'we work remotely',
            'remotive',
            'larajobs',
            'python job board',
            'django community jobs',
        ];
    }

    private function isBrazilCareerPageLead(): bool
    {
        if ($this->source_type !== self::SOURCE_TYPE_JOB_BOARD) {
            return false;
        }

        $sourceUrl = $this->source_url;

        if (! is_string($sourceUrl) || trim($sourceUrl) === '') {
            return false;
        }

        foreach ($this->configuredCompanyCareerTargets() as $target) {
            if (! is_array($target)) {
                continue;
            }

            if (! $this->regionLooksBrazilian($target['region'] ?? null)) {
                continue;
            }

            $careerUrls = is_array($target['career_urls'] ?? null)
                ? $target['career_urls']
                : [];

            foreach ($careerUrls as $careerUrl) {
                if (! is_string($careerUrl) || trim($careerUrl) === '') {
                    continue;
                }

                if (str_starts_with($sourceUrl, $careerUrl)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, mixed>
     */
    private function configuredCompanyCareerTargets(): array
    {
        $container = Container::getInstance();

        if (! $container instanceof Container || ! $container->bound('config')) {
            return [];
        }

        $targets = config('job_discovery.company_career_targets', []);

        return is_array($targets) ? $targets : [];
    }

    private function regionLooksBrazilian(mixed $region): bool
    {
        if (! is_string($region) || trim($region) === '') {
            return false;
        }

        $normalizedRegion = $this->normalizedSourceValue($region);

        return $this->containsLocationSignal($normalizedRegion, $this->brazilLocationPatterns());
    }

    /**
     * @param list<string> $patterns
     */
    private function containsLocationSignal(string $normalizedText, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedText) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function brazilLocationPatterns(): array
    {
        return [
            '/\bbrasil\b/',
            '/\bbrazil\b/',
            '/\bbr\b/',
            '/\bbelo horizonte\b/',
            '/\bbh\b/',
            '/\bsao paulo\b/',
            '/\brio de janeiro\b/',
            '/\bminas gerais\b/',
            '/\bmg\b/',
            '/\bcontagem\b/',
            '/\bnova lima\b/',
            '/\bbetim\b/',
            '/\bremoto brasil\b/',
            '/\bremote brazil\b/',
            '/\banywhere in brazil\b/',
        ];
    }

    /**
     * @return list<string>
     */
    private function internationalLocationPatterns(): array
    {
        return [
            '/\bworldwide\b/',
            '/\banywhere\b/',
            '/\bglobal\b/',
            '/\beurope\b/',
            '/\busa\b/',
            '/\bunited states\b/',
            '/\bcanada\b/',
            '/\buk\b/',
            '/\bgermany\b/',
            '/\bportugal\b/',
            '/\blatin america\b/',
            '/\blatam\b/',
        ];
    }
}
