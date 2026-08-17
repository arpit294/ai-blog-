<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTopic;
use App\Models\AutomationProfile;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogTopic::with(['automationProfile', 'sourceRun'])->latest();

        if ($request->filled('automation_id')) {
            $query->where('automation_id', $request->automation_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $topics = $query->paginate(20)->withQueryString();
        $profiles = AutomationProfile::all();

        return view('admin.topic-memory.index', compact('topics', 'profiles'));
    }

    public function block(BlogTopic $topic)
    {
        $topic->update([
            'status' => 'rejected',
            'rejection_reason' => 'manual_block',
        ]);

        return redirect()->back()->with('success', 'Topic manually blocked.');
    }
    public function approve(BlogTopic $topic)
    {
        $topic->update([
            'status' => 'reserved',
        ]);

        return redirect()->back()->with('success', 'Topic manually approved.');
    }
}
