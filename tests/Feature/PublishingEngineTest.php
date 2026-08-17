<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationProfile;
use App\Models\Article;
use App\Models\BlogTopic;
use App\Models\AutomationRun;
use App\Models\Category;
use App\Services\Automation\Publisher;
use App\Jobs\PublishArticle;
use Carbon\Carbon;

class PublishingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $profile;
    protected $category;
    protected $topic;
    protected $run;
    protected $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category'
        ]);
        
        $this->profile = AutomationProfile::create([
            'user_id' => $this->user->id,
            'name' => 'Test Profile',
            'niche' => 'Tech',
            'target_audience' => 'Devs',
            'language' => 'English',
            'tone' => 'Professional',
            'min_words' => 500,
            'max_words' => 1000,
            'quota_count' => 3,
            'quota_period' => 'weekly',
            'quota_mode' => 'limited',
            'completion_state' => 'published',
            'publish_mode' => 'auto_publish',
            'status' => 'active',
            'timezone' => 'UTC'
        ]);

        $this->run = AutomationRun::create([
            'automation_profile_id' => $this->profile->id,
            'status' => 'running',
            'current_stage' => 'publishing',
            'run_key' => 'test_key',
        ]);

        $this->topic = BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => 'Test Topic',
            'normalized_title' => 'test topic',
            'summary' => 'Test summary',
            'status' => 'candidate',
            'source_run_id' => $this->run->id
        ]);

        $this->article = Article::create([
            'user_id' => $this->user->id,
            'automation_profile_id' => $this->profile->id,
            'topic_id' => $this->topic->id,
            'category_id' => $this->category->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Test Content',
            'status' => 'review', // Starting state before publish
        ]);
    }

    public function test_publisher_idempotency()
    {
        $publisher = app(Publisher::class);
        
        // First publish
        $result1 = $publisher->publish($this->article);
        $this->assertTrue($result1);
        
        $this->article->refresh();
        $this->assertEquals('published', $this->article->status);
        $this->assertDatabaseCount('publishing_logs', 1);
        $this->assertDatabaseCount('automation_quota_events', 1);

        // Second publish (idempotent)
        $result2 = $publisher->publish($this->article);
        $this->assertTrue($result2);
        
        $this->assertDatabaseCount('publishing_logs', 1); // No new log
        $this->assertDatabaseCount('automation_quota_events', 1); // No double quota
    }

    public function test_publish_mode_draft_is_skipped()
    {
        $this->profile->update(['publish_mode' => 'draft']);
        
        $publisher = app(Publisher::class);
        $result = $publisher->publish($this->article);
        
        $this->assertFalse($result);
        $this->article->refresh();
        $this->assertNotEquals('published', $this->article->status);
        $this->assertDatabaseCount('publishing_logs', 0);
    }

    public function test_publish_mode_review_requires_approval()
    {
        $this->profile->update(['publish_mode' => 'review']);
        
        $publisher = app(Publisher::class);
        $result = $publisher->publish($this->article);
        
        $this->assertFalse($result); // Failed because it's not approved

        // Now approve it
        $this->article->update(['status' => 'approved']);
        $result = $publisher->publish($this->article);
        
        $this->assertTrue($result);
        $this->article->refresh();
        $this->assertEquals('published', $this->article->status);
    }

    public function test_quota_acceptance_scenario()
    {
        // Weekly quota = 3. Publish 3 times (by creating 3 runs/articles).
        $publisher = app(Publisher::class);

        // Run 1
        $publisher->publish($this->article);

        // Run 2
        $run2 = AutomationRun::create(['automation_profile_id' => $this->profile->id, 'status' => 'running', 'current_stage' => 'publishing', 'run_key' => 'test_key_2']);
        $topic2 = BlogTopic::create(['automation_id' => $this->profile->id, 'source_run_id' => $run2->id, 'title' => 'Test 2', 'normalized_title' => 'test 2', 'summary' => 'S2', 'status' => 'candidate']);
        $article2 = Article::create(['user_id' => $this->user->id, 'automation_profile_id' => $this->profile->id, 'topic_id' => $topic2->id, 'category_id' => $this->category->id, 'status' => 'review', 'title' => 'T2', 'slug' => 't2', 'content' => 'C2']);
        $publisher->publish($article2);

        // Run 3
        $run3 = AutomationRun::create(['automation_profile_id' => $this->profile->id, 'status' => 'running', 'current_stage' => 'publishing', 'run_key' => 'test_key_3']);
        $topic3 = BlogTopic::create(['automation_id' => $this->profile->id, 'source_run_id' => $run3->id, 'title' => 'Test 3', 'normalized_title' => 'test 3', 'summary' => 'S3', 'status' => 'candidate']);
        $article3 = Article::create(['user_id' => $this->user->id, 'automation_profile_id' => $this->profile->id, 'topic_id' => $topic3->id, 'category_id' => $this->category->id, 'status' => 'review', 'title' => 'T3', 'slug' => 't3', 'content' => 'C3']);
        $publisher->publish($article3);

        $this->assertDatabaseCount('automation_quota_events', 3);

        // Run 4
        $run4 = AutomationRun::create(['automation_profile_id' => $this->profile->id, 'status' => 'running', 'current_stage' => 'publishing', 'run_key' => 'test_key_4']);
        $topic4 = BlogTopic::create(['automation_id' => $this->profile->id, 'source_run_id' => $run4->id, 'title' => 'Test 4', 'normalized_title' => 'test 4', 'summary' => 'S4', 'status' => 'candidate']);
        $article4 = Article::create(['user_id' => $this->user->id, 'automation_profile_id' => $this->profile->id, 'topic_id' => $topic4->id, 'category_id' => $this->category->id, 'status' => 'review', 'title' => 'T4', 'slug' => 't4', 'content' => 'C4']);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Quota exhausted");
        $publisher->publish($article4);
    }

    public function test_publish_scheduled_sweep()
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->profile->update(['publish_mode' => 'scheduled']);
        $this->article->update(['status' => 'scheduled', 'scheduled_at' => Carbon::now()->subHour()]);

        $this->artisan('automation:publish-scheduled')->assertExitCode(0);

        \Illuminate\Support\Facades\Queue::assertPushed(PublishArticle::class, function ($job) {
            return $job->articleId === $this->article->id;
        });
    }
}
