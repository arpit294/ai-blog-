<?php

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\PublishingLog;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Publisher
{
    protected QuotaConsumptionService $quotaConsumption;

    public function __construct(QuotaConsumptionService $quotaConsumption)
    {
        $this->quotaConsumption = $quotaConsumption;
    }

    public function publish(Article $article): bool
    {
        $profile = $article->profile;

        try {
            return DB::transaction(function () use ($article, $profile) {
                // Lock the article row to prevent concurrent publishing
                $lockedArticle = DB::table('articles')->where('id', $article->id)->lockForUpdate()->first();

                if (!$lockedArticle) {
                    throw new \Exception("Article not found for publishing.");
                }

                if ($lockedArticle->status === 'published') {
                    Log::info("Article {$article->id} is already published. Skipping.");
                    return true;
                }

                // Check publish mode logic
                if ($profile->publish_mode === 'draft') {
                    Log::info("Article {$article->id} publish mode is draft. Skipping publish.");
                    return false;
                }

                if ($profile->publish_mode === 'scheduled') {
                    if (!$lockedArticle->scheduled_at || Carbon::parse($lockedArticle->scheduled_at)->isFuture()) {
                        Log::info("Article {$article->id} is scheduled for future. Skipping publish.");
                        return false;
                    }
                }

                if ($profile->publish_mode === 'review' && !in_array($lockedArticle->status, ['approved', 'scheduled'])) {
                    Log::info("Article {$article->id} requires approval. Current status: {$lockedArticle->status}");
                    return false;
                }

                $providerPublishId = Str::uuid()->toString();

                // Consume quota if completion_state == 'published'
                if ($profile->completion_state === 'published') {
                    $runId = $article->topic->source_run_id ?? null;
                    if ($runId) {
                        $run = AutomationRun::find($runId);
                        if ($run) {
                            $quotaConsumed = $this->quotaConsumption->consume($run, 'published');
                            if (!$quotaConsumed) {
                                throw new \Exception("Quota exhausted or failed to consume for run {$runId}. Cannot publish.");
                            }
                        } else {
                            throw new \Exception("Run not found");
                        }
                    } else {
                        throw new \Exception("RunId is null");
                    }
                }

                // Actually publish
                Article::where('id', $article->id)->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                PublishingLog::create([
                    'article_id' => $article->id,
                    'channel' => 'internal',
                    'status' => 'success',
                    'provider_publish_id' => $providerPublishId,
                ]);

                Log::info("Article {$article->id} published successfully.");

                return true;
            });
        } catch (\Exception $e) {
            Log::error("Failed to publish article {$article->id}: " . $e->getMessage());

            PublishingLog::create([
                'article_id' => $article->id,
                'channel' => 'internal',
                'status' => 'failed',
                'response_ref' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }
}
