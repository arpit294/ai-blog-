<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGenerationLog extends Model
{
    protected $fillable = [
        'generation_id',
        'event',
        'payload_ref',
    ];

    public function generation()
    {
        return $this->belongsTo(AiGeneration::class, 'generation_id');
    }
}
