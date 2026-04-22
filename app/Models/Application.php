<?php

namespace App\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    public const STATUS_WISHLIST = 'wishlist';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_INTERVIEW = 'interview';

    public const STATUS_OFFER = 'offer';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_name',
        'job_title',
        'source_url',
        'status',
        'applied_at',
        'notes',
    ];

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_WISHLIST,
            self::STATUS_APPLIED,
            self::STATUS_INTERVIEW,
            self::STATUS_OFFER,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
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
}
