<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard - Phase 9 Analytics') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Top KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase">Total Articles</h3>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total_articles'] }}</p>
                    <div class="text-xs text-gray-500 mt-1">Pub: {{ $stats['published_articles'] }} | Draft: {{ $stats['draft_articles'] }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase">Avg Quality Score</h3>
                    <p class="text-3xl font-bold text-blue-600 mt-2">{{ $stats['avg_quality_score'] }} / 100</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase">Failed Runs</h3>
                    <p class="text-3xl font-bold {{ $stats['failed_runs'] > 0 ? 'text-red-600' : 'text-green-600' }} mt-2">{{ $stats['failed_runs'] }}</p>
                    <div class="text-xs text-gray-500 mt-1">Requires attention</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase">Next Scheduled Run</h3>
                    <p class="text-xl font-bold text-gray-900 mt-2">{{ $stats['next_run'] }}</p>
                </div>
            </div>

            <!-- Profile Overview & Quota -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Automation Profiles & Quota</h3>
                <div class="mb-4 text-sm text-gray-600">
                    Active: <span class="font-bold">{{ $stats['active_profiles'] }}</span> | 
                    Paused: <span class="font-bold">{{ $stats['paused_profiles'] }}</span> | 
                    Rejected Duplicate Topics: <span class="font-bold text-red-500">{{ $stats['rejected_topics'] }}</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm whitespace-nowrap">
                        <thead class="border-b font-medium bg-gray-50">
                            <tr>
                                <th class="px-4 py-2">Profile</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Publish Mode</th>
                                <th class="px-4 py-2">Quota ({{ config('automation.quota_period', 'weekly') }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($profiles as $profile)
                            <tr class="border-b">
                                <td class="px-4 py-2 font-medium">{{ $profile->name }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs rounded {{ $profile->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($profile->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ ucfirst($profile->publish_mode) }}</td>
                                <td class="px-4 py-2">
                                    @php $q = $quotaStats[$profile->id]; @endphp
                                    @if($q['mode'] === 'unlimited')
                                        Unlimited (Used: {{ $q['used'] }})
                                    @else
                                        {{ $q['used'] }} / {{ $q['limit'] }} published
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1 max-w-[100px]">
                                            <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $q['percentage'] }}%"></div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Recent Automation Runs</h3>
                <ul class="space-y-3">
                    @forelse($recentActivity as $run)
                    <li class="border-b pb-2">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-medium">{{ $run->profile->name }}</span>
                                <span class="text-gray-500 text-sm ml-2">Stage: {{ $run->current_stage }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ $run->created_at->diffForHumans() }}</span>
                        </div>
                        @if($run->last_error)
                            <div class="text-red-500 text-xs mt-1">{{ Str::limit($run->last_error, 100) }}</div>
                        @endif
                    </li>
                    @empty
                    <li class="text-gray-500 text-sm">No recent activity.</li>
                    @endforelse
                </ul>
            </div>
            
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-700">System Info</h3>
                <p class="text-gray-600 text-sm mt-2">Last Successful Article: <span class="font-bold">{{ $stats['last_successful_article'] }}</span></p>
            </div>

        </div>
    </div>
</x-app-layout>
