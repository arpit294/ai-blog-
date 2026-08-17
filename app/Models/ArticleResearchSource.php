<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleResearchSource extends Model
{
    protected $fillable = [
        'article_id',
        'url',
        'title',
        'snippet'
    ];
}
