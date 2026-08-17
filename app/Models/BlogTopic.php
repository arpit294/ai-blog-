<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTopic extends Model
{
    protected $fillable = [
        'automation_id',
        'title',
        'normalized_title',
        'summary',
        'category',
        'intent',
        'primary_keyword',
        'target_keyword',
        'estimated_search_volume',
        'status',
        'rejection_reason',
        'embedding_ref',
        'source_run_id',
        'published_article_id',
        'semantic_embedding',
        'is_duplicate',
        'matched_record_type',
        'matched_record_id',
        'similarity_score',
        'duplicate_flagged_at',
    ];

    public function embedding()
    {
        return $this->morphOne(\App\Models\AutomationEmbedding::class, 'embeddable');
    }

    public function matchedRecord()
    {
        return $this->morphTo();
    }

    public function automationProfile()
    {
        return $this->belongsTo(AutomationProfile::class, 'automation_id');
    }

    public function sourceRun()
    {
        return $this->belongsTo(AutomationRun::class, 'source_run_id');
    }
}
