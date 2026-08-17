<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    protected $fillable = [
        'automation_profile_id',
        'status',
        'current_stage',
        'started_at',
        'completed_at',
        'failed_at',
        'last_error',
        'attempts',
        'run_key',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
        'attempts' => 'integer',
    ];

    public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AutomationProfile::class, 'automation_profile_id');
    }

    public function generations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AiGeneration::class, 'run_id');
    }
}
