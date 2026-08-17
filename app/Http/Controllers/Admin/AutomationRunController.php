<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AutomationRun;

class AutomationRunController extends Controller
{
    public function index(Request $request)
    {
        $query = AutomationRun::with('profile')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('profile_id')) {
            $query->where('automation_profile_id', $request->profile_id);
        }

        $runs = $query->paginate(15)->withQueryString();

        return view('admin.automation-runs.index', compact('runs'));
    }

    public function show(AutomationRun $automationRun)
    {
        $automationRun->load('profile', 'generations');
        return view('admin.automation-runs.show', compact('automationRun'));
    }
}
