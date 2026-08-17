<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationEmbedding extends Model
{
    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'vector',
        'model_name',
        'dimensions',
    ];

    protected $casts = [
        'vector' => 'array',
    ];

    public function embeddable()
    {
        return $this->morphTo();
    }
}
