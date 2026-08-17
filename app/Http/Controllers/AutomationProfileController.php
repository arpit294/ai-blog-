<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationProfileRequest;
use App\Http\Requests\UpdateAutomationProfileRequest;
use App\Models\AutomationProfile;

class AutomationProfileController extends Controller
{
    public function index()
    {
        $profiles = AutomationProfile::with('user')->latest()->paginate(10);
        return view('admin.automation-profiles.index', compact('profiles'));
    }

    public function create()
    {
        return view('admin.automation-profiles.create');
    }

    public function store(StoreAutomationProfileRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        
        $profile = AutomationProfile::create($data);
        
        if (isset($data['schedules'])) {
            foreach ($data['schedules'] as $schedule) {
                $profile->schedules()->create($schedule);
            }
        }

        return redirect()->route('automation-profiles.index')->with('success', 'Automation Profile created successfully.');
    }

    public function show(AutomationProfile $automationProfile)
    {
        $automationProfile->load('schedules');
        return view('admin.automation-profiles.show', compact('automationProfile'));
    }

    public function edit(AutomationProfile $automationProfile)
    {
        $automationProfile->load('schedules');
        return view('admin.automation-profiles.edit', compact('automationProfile'));
    }

    public function update(UpdateAutomationProfileRequest $request, AutomationProfile $automationProfile)
    {
        $data = $request->validated();
        $automationProfile->update($data);

        if (isset($data['schedules'])) {
            $automationProfile->schedules()->delete();
            foreach ($data['schedules'] as $schedule) {
                $automationProfile->schedules()->create($schedule);
            }
        } else {
            $automationProfile->schedules()->delete();
        }

        return redirect()->route('automation-profiles.index')->with('success', 'Automation Profile updated successfully.');
    }

    public function destroy(AutomationProfile $automationProfile)
    {
        $automationProfile->delete();
        return redirect()->route('automation-profiles.index')->with('success', 'Automation Profile deleted successfully.');
    }
}
