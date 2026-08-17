<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Topic Memory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="GET" class="mb-6 flex space-x-4">
                        <select name="automation_id" class="border-gray-300 rounded-md">
                            <option value="">All Profiles</option>
                            @foreach($profiles as $profile)
                                <option value="{{ $profile->id }}" {{ request('automation_id') == $profile->id ? 'selected' : '' }}>
                                    {{ $profile->name }}
                                </option>
                            @endforeach
                        </select>
                        <select name="status" class="border-gray-300 rounded-md">
                            <option value="">All Statuses</option>
                            <option value="candidate" {{ request('status') == 'candidate' ? 'selected' : '' }}>Candidate</option>
                            <option value="reserved" {{ request('status') == 'reserved' ? 'selected' : '' }}>Reserved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="used" {{ request('status') == 'used' ? 'selected' : '' }}>Used</option>
                            <option value="review_required" {{ request('status') == 'review_required' ? 'selected' : '' }}>Review Required</option>
                        </select>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Filter</button>
                    </form>

                    <table class="min-w-full bg-white border border-gray-200">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b">Title</th>
                                <th class="py-2 px-4 border-b">Profile</th>
                                <th class="py-2 px-4 border-b">Status</th>
                                <th class="py-2 px-4 border-b">Reason</th>
                                <th class="py-2 px-4 border-b">Semantic Match</th>
                                <th class="py-2 px-4 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topics as $topic)
                            <tr>
                                <td class="py-2 px-4 border-b">{{ $topic->title }}</td>
                                <td class="py-2 px-4 border-b">{{ $topic->automationProfile->name }}</td>
                                <td class="py-2 px-4 border-b">{{ ucfirst($topic->status) }}</td>
                                <td class="py-2 px-4 border-b">{{ $topic->rejection_reason ?? '-' }}</td>
                                <td class="py-2 px-4 border-b">
                                    @if($topic->matched_record_id)
                                        <span class="text-xs text-gray-500">
                                            {{ class_basename($topic->matched_record_type) }} #{{ $topic->matched_record_id }}
                                            <br>Score: {{ number_format($topic->similarity_score, 2) }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2 px-4 border-b flex space-x-2">
                                    @if($topic->status === 'review_required')
                                        <form method="POST" action="{{ route('topic-memory.approve', $topic) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-green-500 hover:underline">Approve</button>
                                        </form>
                                    @endif

                                    @if($topic->status !== 'rejected')
                                        <form method="POST" action="{{ route('topic-memory.block', $topic) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-red-500 hover:underline">Block</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $topics->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
