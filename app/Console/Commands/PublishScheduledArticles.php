<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Jobs\PublishArticle;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PublishScheduledArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sweep for scheduled articles that are due and dispatch PublishArticle';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting scheduled publish sweep...');
        Log::info('PublishScheduledArticles: Starting sweep');

        // Find articles that are 'scheduled' and where scheduled_at <= now()
        $articles = Article::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Carbon::now())
            ->get();

        if ($articles->isEmpty()) {
            $this->info('No due scheduled articles found.');
            return 0;
        }

        foreach ($articles as $article) {
            $runId = $article->topic->source_run_id ?? 0;
            if ($runId) {
                PublishArticle::dispatch($runId, $article->id);
                $this->info("Dispatched PublishArticle for article ID: {$article->id}");
            } else {
                Log::warning("Article {$article->id} is due for publish but has no source_run_id. Skipping.");
            }
        }

        $this->info('Sweep complete.');
        return 0;
    }
}
