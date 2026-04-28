<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'target_role',
        'target_roles',
        'preferred_locations',
        'preferred_work_modes',
        'auto_discover_jobs',
        'last_discovered_at',
        'last_discovered_new_count',
        'last_discovery_batch_id',
        'professional_summary',
        'core_skills',
        'work_experience_text',
        'education_text',
        'certifications_text',
        'languages_text',
        'base_resume_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'core_skills' => 'array',
            'target_roles' => 'array',
            'preferred_locations' => 'array',
            'preferred_work_modes' => 'array',
            'auto_discover_jobs' => 'boolean',
            'last_discovered_at' => 'datetime',
            'last_discovered_new_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
