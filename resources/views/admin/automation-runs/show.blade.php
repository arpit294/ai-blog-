<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Automation Run Details') }} #{{ $automationRun->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Details Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2">Run Information</h3>
                
                <div class="grid grid-cols-2 gap-y-4 text-sm">
                    <div class="font-medium text-gray-500">Run ID:</div>
                    <div>{{ $automationRun->id }}</div>

                    <div class="font-medium text-gray-500">Profile:</div>
                    <div>{{ $automationRun->profile->name ?? 'Unknown' }}</div>

                    <div class="font-medium text-gray-500">Run Key:</div>
                    <div class="truncate" title="{{ $automationRun->run_key }}">{{ $automationRun->run_key }}</div>

                    <div class="font-medium text-gray-500">Status:</div>
                    <div>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            @if($automationRun->status === 'completed') bg-green-100 text-green-800
                            @elseif($automationRun->status === 'running') bg-blue-100 text-blue-800
                            @elseif($automationRun->status === 'failed') bg-red-100 text-red-800
                            @elseif($automationRun->status === 'skipped') bg-gray-100 text-gray-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            {{ ucfirst($automationRun->status) }}
                        </span>
                    </div>

                    <div class="font-medium text-gray-500">Current Stage:</div>
                    <div>{{ Str::headline($automationRun->current_stage) }}</div>

                    <div class="font-medium text-gray-500">Attempts:</div>
                    <div>{{ $automationRun->attempts }}</div>

                    <div class="font-medium text-gray-500">Started At:</div>
                    <div>{{ $automationRun->started_at ?? '-' }}</div>

                    <div class="font-medium text-gray-500">Completed At:</div>
                    <div>{{ $automationRun->completed_at ?? '-' }}</div>

                    <div class="font-medium text-gray-500">Failed At:</div>
                    <div class="text-red-600">{{ $automationRun->failed_at ?? '-' }}</div>

                    <div class="font-medium text-gray-500">Created At:</div>
                    <div>{{ $automationRun->created_at }}</div>

                    <div class="font-medium text-gray-500">Updated At:</div>
                    <div>{{ $automationRun->updated_at }}</div>
                </div>

                @if($automationRun->last_error)
                <div class="mt-6">
                    <h4 class="font-medium text-red-600 mb-2">Last Error</h4>
                    <pre class="bg-gray-100 p-3 rounded text-xs text-red-800 overflow-x-auto whitespace-pre-wrap">{{ $automationRun->last_error }}</pre>
                </div>
                @endif
            </div>

            <!-- Timeline Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2">Execution Timeline</h3>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-center text-gray-700">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Run created on {{ $automationRun->created_at->format('M d, H:i:s') }}
                    </li>

                    @if($automationRun->started_at)
                    <li class="flex items-center text-gray-700">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Worker started on {{ $automationRun->started_at->format('M d, H:i:s') }} (Attempt {{ $automationRun->attempts }})
                    </li>
                    @endif

                    <li class="flex items-center text-gray-700">
                        <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Stage: {{ Str::headline($automationRun->current_stage) }}
                    </li>

                    @if($automationRun->generations->count() > 0)
                        <li class="pl-7 mt-2 mb-2">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">AI Generations</h4>
                            <ul class="space-y-2">
                            @foreach($automationRun->generations as $gen)
                                <li class="flex items-center text-xs text-gray-600 border-l-2 {{ $gen->status === 'success' ? 'border-green-400' : 'border-red-400' }} pl-2">
                                    <svg class="w-3 h-3 text-purple-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    <span class="font-medium mr-1">{{ $gen->task_type }}</span>
                                    <span class="text-gray-400 mr-1">({{ $gen->status }})</span>
                                    <span class="text-gray-400">{{ $gen->duration_ms }}ms</span>
                                </li>
                            @endforeach
                            </ul>
                        </li>
                    @endif

                    @if($automationRun->completed_at)
                    <li class="flex items-center text-gray-700">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Execution completed on {{ $automationRun->completed_at->format('M d, H:i:s') }}
                    </li>
                    @endif

                    @if($automationRun->failed_at)
                    <li class="flex items-center text-red-600 font-medium">
                        <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Failed on {{ $automationRun->failed_at->format('M d, H:i:s') }}
                    </li>
                    @endif
                </ul>
            </div>
            
        </div>
    </div>
</x-app-layout>
