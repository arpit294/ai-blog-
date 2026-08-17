<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\AutomationProfile;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with(['category', 'profile'])->latest()->paginate(10);
        return view('admin.articles.index', compact('articles'));
    }

    public function create()
    {
        $categories = Category::all();
        $profiles = AutomationProfile::all();
        return view('admin.articles.create', compact('categories', 'profiles'));
    }

    public function store(StoreArticleRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        Article::create($data);
        return redirect()->route('articles.index')->with('success', 'Article created successfully.');
    }

    public function show(Article $article)
    {
        return view('admin.articles.show', compact('article'));
    }

    public function edit(Article $article)
    {
        $categories = Category::all();
        $profiles = AutomationProfile::all();
        return view('admin.articles.edit', compact('article', 'categories', 'profiles'));
    }

    public function update(UpdateArticleRequest $request, Article $article)
    {
        $article->update($request->validated());
        return redirect()->route('articles.index')->with('success', 'Article updated successfully.');
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return redirect()->route('articles.index')->with('success', 'Article deleted successfully.');
    }

    // --- Human Review Workflow Actions ---

    public function createWithAi(\Illuminate\Http\Request $request)
    {
        $profile = \App\Models\AutomationProfile::where('status', 'active')->first();

        if (!$profile) {
            return back()->with('error', 'No active automation profile found. Please set one up first.');
        }

        $customTopic = $request->input('custom_topic');

        $runService = app(\App\Services\Automation\AutomationRunService::class);
        $run = $runService->dispatchRun($profile, $customTopic);

        if ($run) {
            return back()->with('success', 'AI Generation started! An article will be created shortly.');
        }

        return back()->with('error', 'AI Generation is already running or queued for this profile.');
    }

    public function activeRunStatus()
    {
        $run = \App\Models\AutomationRun::whereIn('status', ['running', 'queued'])
            ->orderBy('id', 'desc')
            ->first();

        if (!$run) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'stage' => str_replace('_', ' ', \Illuminate\Support\Str::title($run->current_stage)),
            'status' => $run->status,
        ]);
    }

    public function approve(Article $article)
    {
        $profile = $article->profile;
        $runId = $article->topic->source_run_id ?? null;

        if ($profile->completion_state === 'approved' && $runId) {
            $run = \App\Models\AutomationRun::find($runId);
            if ($run) {
                $quotaConsumption = app(\App\Services\Automation\QuotaConsumptionService::class);
                $consumed = $quotaConsumption->consume($run, 'approved');
                if (!$consumed) {
                    return back()->with('error', 'Quota exhausted or failed to consume. Cannot approve.');
                }
            }
        }

        $article->update(['status' => 'approved']); 

        if ($profile->publish_mode === 'review' && $runId) {
            \App\Jobs\PublishArticle::dispatch($runId, $article->id);
            return back()->with('success', 'Article approved and published/queued for publishing.');
        } elseif ($profile->publish_mode === 'scheduled') {
            $scheduledAt = $article->scheduled_at ?? $profile->next_run_at ?? now()->addDay();
            $article->update([
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
            ]);
            return back()->with('success', 'Article approved and scheduled for publishing.');
        }

        return back()->with('success', 'Article approved.');
    }

    public function reject(Article $article, \Illuminate\Http\Request $request)
    {
        $article->update(['status' => 'rejected']);
        // Optional: save rejection reason somewhere if needed
        return back()->with('success', 'Article rejected.');
    }

    public function requestChanges(Article $article, \Illuminate\Http\Request $request)
    {
        $request->validate(['reason' => 'required|string']);
        $contentData = json_decode($article->content, true);
        if (isset($contentData['sections'][0])) {
            $contentGen = app(\App\Services\Automation\ContentGenerator::class);
            $contentGen->regenerateSection($article, $contentData['sections'][0]['heading'], $request->reason);
            // Re-run quality checks
            \App\Jobs\RunQualityChecks::dispatchSync($article->topic->run_id ?? 0, $article->id);
            return back()->with('success', 'Changes requested. Article is being updated.');
        }
        return back()->with('error', 'Cannot request changes: no sections found.');
    }

    public function regenerateTitle(Article $article)
    {
        if ($article->seo) $article->seo->delete();
        $seoGen = app(\App\Services\Automation\SeoGenerator::class);
        $seoGen->generate($article->profile, $article, $article->topic->run_id ?? 0);
        \App\Jobs\RunQualityChecks::dispatchSync($article->topic->run_id ?? 0, $article->id);
        return back()->with('success', 'SEO regenerated successfully.');
    }

    public function regenerateImage(Article $article)
    {
        if ($article->image) {
            $article->image->delete();
        }
        $runId = $article->topic->run_id ?? 0;
        \App\Jobs\GenerateImage::dispatchSync($runId, $article->id);
        return back()->with('success', 'Image generation dispatched successfully.');
    }

    public function regenerateSection(Article $article, \Illuminate\Http\Request $request)
    {
        $request->validate(['heading' => 'required|string', 'reason' => 'required|string']);
        $contentGen = app(\App\Services\Automation\ContentGenerator::class);
        $contentGen->regenerateSection($article, $request->heading, $request->reason);
        \App\Jobs\RunQualityChecks::dispatchSync($article->topic->run_id ?? 0, $article->id);
        return back()->with('success', 'Section regenerated successfully.');
    }

    public function rerunQualityChecks(Article $article)
    {
        if ($article->qualityCheck) $article->qualityCheck->delete();
        \App\Jobs\RunQualityChecks::dispatchSync($article->topic->run_id ?? 0, $article->id);
        return back()->with('success', 'Quality checks re-run successfully.');
    }
}
