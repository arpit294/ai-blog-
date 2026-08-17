<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationSchedule extends Model
{
    protected $fillable = [
        'automation_profile_id',
        'weekday',
        'time',
    ];

    public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AutomationProfile::class, 'automation_profile_id');
    }
}
