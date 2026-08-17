<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGeneration extends Model
{
    protected $fillable = [
        'run_id',
        'task_type',
        'model',
        'prompt_version',
        'status',
        'duration_ms',
    ];

    public function run()
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }

    public function logs()
    {
        return $this->hasMany(AiGenerationLog::class, 'generation_id');
    }
}
