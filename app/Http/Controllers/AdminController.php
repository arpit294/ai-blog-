<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\AutomationProfile;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $profiles = AutomationProfile::all();
        $activeProfiles = $profiles->where('status', 'active')->count();
        $pausedProfiles = $profiles->where('status', 'paused')->count();
        
        $totalArticles = Article::count();
        $publishedArticles = Article::where('status', 'published')->count();
        $draftArticles = Article::where('status', 'draft')->count();

        // Failed runs count (needs attention)
        $failedRuns = \App\Models\AutomationRun::whereIn('current_stage', ['failed', 'needs_review'])->count();

        // Average quality score
        $avgQualityScore = \App\Models\QualityCheck::avg('overall_score') ?? 0;

        // Rejected duplicates count
        $rejectedTopics = \App\Models\BlogTopic::where('status', 'rejected')->count();

        // Next run
        $nextRunProfile = AutomationProfile::where('status', 'active')->whereNotNull('next_run_at')->orderBy('next_run_at')->first();
        $nextRun = $nextRunProfile ? $nextRunProfile->next_run_at->diffForHumans() : 'None scheduled';

        // Last successful article
        $lastSuccessful = Article::where('status', 'published')->latest('published_at')->first();

        // Weekly quota progress logic
        $quotaManager = app(\App\Services\Automation\QuotaManager::class);
        $quotaStats = [];
        foreach ($profiles as $profile) {
            $quotaStats[$profile->id] = $quotaManager->getQuotaStatus($profile);
        }

        // Recent activity (latest 5 logs or runs)
        $recentActivity = \App\Models\AutomationRun::with('profile')->latest()->take(5)->get();

        $stats = [
            'total_articles' => $totalArticles,
            'published_articles' => $publishedArticles,
            'draft_articles' => $draftArticles,
            'total_categories' => Category::count(),
            'active_profiles' => $activeProfiles,
            'paused_profiles' => $pausedProfiles,
            'failed_runs' => $failedRuns,
            'avg_quality_score' => round($avgQualityScore, 1),
            'rejected_topics' => $rejectedTopics,
            'next_run' => $nextRun,
            'last_successful_article' => $lastSuccessful ? $lastSuccessful->title : 'None yet',
        ];

        return view('admin.dashboard', compact('stats', 'profiles', 'quotaStats', 'recentActivity'));
    }
}
