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

    protected $fillable = [
        'user_id',
        'company_name',
        'job_title',
        'source_name',
        'source_url',
        'location',
        'work_mode',
        'salary_range',
        'description_excerpt',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discovered_at' => 'date',
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
