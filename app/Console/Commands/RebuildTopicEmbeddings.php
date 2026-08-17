<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\AutomationEmbedding;
use App\Models\BlogTopic;
use App\Services\AI\EmbeddingProvider;
use Illuminate\Console\Command;

class RebuildTopicEmbeddings extends Command
{
    protected $signature = 'automation:rebuild-embeddings {--force : Rebuild for records that already have embeddings}';
    protected $description = 'Generate embeddings for existing BlogTopics and Articles';

    public function handle(EmbeddingProvider $embeddingService)
    {
        $force = $this->option('force');

        $this->info('Starting embedding rebuild...');

        $this->rebuildForModel(BlogTopic::class, function ($topic) {
            return trim("{$topic->title} - {$topic->summary}");
        }, $embeddingService, $force);

        $this->rebuildForModel(Article::class, function ($article) {
            return trim("{$article->title} - {$article->excerpt}");
        }, $embeddingService, $force);

        $this->info('Embedding rebuild complete.');
    }

    protected function rebuildForModel($modelClass, callable $textExtractor, EmbeddingProvider $embeddingService, bool $force)
    {
        $this->info("Processing {$modelClass}...");

        $query = $modelClass::query();

        if ($modelClass === BlogTopic::class) {
            $query->whereIn('status', ['reserved', 'used']);
        }

        if (!$force) {
            $query->whereDoesntHave('embedding');
        }

        $query->chunkById(50, function ($records) use ($modelClass, $textExtractor, $embeddingService, $force) {
            foreach ($records as $record) {
                if (!$force && $record->embedding()->exists()) {
                    continue;
                }

                $text = $textExtractor($record);
                if (empty($text)) {
                    continue;
                }

                try {
                    $vector = $embeddingService->embed($text);

                    AutomationEmbedding::updateOrCreate(
                        [
                            'embeddable_type' => $modelClass,
                            'embeddable_id' => $record->id,
                        ],
                        [
                            'vector' => $vector,
                            'model_name' => method_exists($embeddingService, 'model') ? $embeddingService->model : 'nomic-embed-text',
                            'dimensions' => count($vector),
                        ]
                    );

                    $this->line("Embedded {$modelClass} ID: {$record->id}");
                } catch (\Exception $e) {
                    $this->error("Failed to embed {$modelClass} ID {$record->id}: " . $e->getMessage());
                }
                
                usleep(50000);
            }
        });
    }
}
