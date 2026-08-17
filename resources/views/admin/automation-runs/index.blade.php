<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Automation Runs') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <!-- Filters -->
                <form method="GET" action="{{ route('automation-runs.index') }}" class="mb-6 flex gap-4">
                    <div>
                        <select name="status" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">All Statuses</option>
                            <option value="queued" {{ request('status') == 'queued' ? 'selected' : '' }}>Queued</option>
                            <option value="running" {{ request('status') == 'running' ? 'selected' : '' }}>Running</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                            <option value="skipped" {{ request('status') == 'skipped' ? 'selected' : '' }}>Skipped</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Filter
                        </button>
                    </div>
                </form>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-sm">
                        @foreach($runs as $run)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">#{{ $run->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $run->profile->name ?? 'Unknown' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($run->status === 'completed') bg-green-100 text-green-800
                                    @elseif($run->status === 'running') bg-blue-100 text-blue-800
                                    @elseif($run->status === 'failed') bg-red-100 text-red-800
                                    @elseif($run->status === 'skipped') bg-gray-100 text-gray-800
                                    @else bg-yellow-100 text-yellow-800 @endif">
                                    {{ ucfirst($run->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ Str::headline($run->current_stage) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $run->attempts }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $run->started_at ? $run->started_at->diffForHumans() : '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $run->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium">
                                <a href="{{ route('automation-runs.show', $run) }}" class="text-indigo-600 hover:text-indigo-900">View Details</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ $runs->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
