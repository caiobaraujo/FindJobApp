<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeVariant extends Model
{
    public const MODE_FAITHFUL = 'faithful';

    public const MODE_ATS_BOOST = 'ats_boost';

    public const MODE_ATS_SAFE = 'ats_safe';

    protected $fillable = [
        'user_id',
        'job_lead_id',
        'mode',
        'generated_text',
    ];

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_FAITHFUL,
            self::MODE_ATS_BOOST,
            self::MODE_ATS_SAFE,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobLead(): BelongsTo
    {
        return $this->belongsTo(JobLead::class);
    }
}
