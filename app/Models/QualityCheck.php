<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityCheck extends Model
{
    protected $fillable = [
        'article_id',
        'overall_score',
        'structure_score',
        'completeness_score',
        'seo_score',
        'readability_score',
        'uniqueness_score',
        'technical_validity_score',
        'status',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
