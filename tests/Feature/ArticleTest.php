<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AutomationProfile;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_belongs_to_an_automation_profile()
    {
        $user = User::factory()->create();
        $profile = AutomationProfile::create([
            'user_id' => $user->id,
            'name' => 'Profile',
            'niche' => 'Niche',
            'target_audience' => 'Audience',
            'language' => 'English',
            'tone' => 'Tone',
            'min_words' => 100,
            'max_words' => 200,
            'quota_count' => 1,
            'quota_period' => 'daily',
            'duplicate_mode' => 'off',
            'publish_mode' => 'draft',
            'status' => 'active',
        ]);

        $article = Article::create([
            'user_id' => $user->id,
            'automation_profile_id' => $profile->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Content here',
        ]);

        $this->assertTrue($article->profile->is($profile));
    }

    public function test_article_belongs_to_a_category()
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        $article = Article::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Test Article 2',
            'slug' => 'test-article-2',
            'content' => 'Content here',
        ]);

        $this->assertTrue($article->category->is($category));
    }
}
