<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationProfile extends Model
{
    /** @use HasFactory<\Database\Factories\AutomationProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'niche',
        'target_audience',
        'language',
        'tone',
        'min_words',
        'max_words',
        'quota_count',
        'quota_period',
        'quota_mode',
        'timezone',
        'completion_state',
        'reserve_quota_on_approval',
        'generate_seo',
        'generate_image',
        'duplicate_mode',
        'duplicate_threshold',
        'semantic_duplicate_threshold',
        'publish_mode',
        'status',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'generate_seo' => 'boolean',
        'generate_image' => 'boolean',
        'duplicate_threshold' => 'float',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AutomationSchedule::class);
    }

    public function articles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function automationRuns()
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function quotaEvents()
    {
        return $this->hasMany(AutomationQuotaEvent::class);
    }
}
