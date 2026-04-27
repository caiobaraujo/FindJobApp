<?php

namespace App\Models;

use Database\Factories\JobLeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLead extends Model
{
    /** @use HasFactory<JobLeadFactory> */
    use HasFactory;

    public const STATUS_SAVED = 'saved';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_IGNORED = 'ignored';

    public const WORK_MODE_REMOTE = 'remote';

    public const WORK_MODE_HYBRID = 'hybrid';

    public const WORK_MODE_ONSITE = 'onsite';

    public const ANALYSIS_STATE_ANALYZED = 'analyzed';

    public const ANALYSIS_STATE_MISSING = 'missing';

    protected $fillable = [
        'user_id',
        'company_name',
        'job_title',
        'source_name',
        'source_url',
        'normalized_source_url',
        'source_host',
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
            ->orderByRaw('relevance_score IS NULL')
            ->orderByDesc('relevance_score')
            ->latest();
    }
}
