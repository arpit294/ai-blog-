<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationQuotaEvent extends Model
{
    protected $fillable = [
        'automation_profile_id',
        'automation_run_id',
        'completion_type',
        'quota_window_start',
        'quota_window_end',
        'counted_at',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'quota_window_start' => 'datetime',
        'quota_window_end' => 'datetime',
        'counted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function profile()
    {
        return $this->belongsTo(AutomationProfile::class, 'automation_profile_id');
    }

    public function run()
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }
}
