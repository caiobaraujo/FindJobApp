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

    public function isErrorState(): bool
    {
        return $this->errorMessage() !== null;
    }

    public function errorMessage(): ?string
    {
        $generatedText = trim((string) $this->generated_text);

        if ($generatedText === '') {
            return null;
        }

        foreach (['en', 'pt', 'es'] as $locale) {
            foreach ([
                'app.resume_variants.unavailable',
                'app.resume_variants.unavailable_model',
                'app.resume_variants.generation_failed',
            ] as $translationKey) {
                if ($generatedText === __($translationKey, [], $locale)) {
                    return $generatedText;
                }
            }
        }

        return null;
    }
}
