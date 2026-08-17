<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleSeo extends Model
{
    protected $fillable = [
        'article_id',
        'seo_title',
        'meta_description',
        'focus_keyword',
        'secondary_keywords',
        'slug',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image_ref',
        'faq_schema',
        'article_schema',
        'internal_link_suggestions',
        'prompt_version',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'faq_schema' => 'array',
        'article_schema' => 'array',
        'internal_link_suggestions' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
