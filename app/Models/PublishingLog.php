<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishingLog extends Model
{
    protected $fillable = [
        'article_id',
        'channel',
        'status',
        'response_ref',
        'provider_publish_id',
    ];

    protected $casts = [
        'response_ref' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
