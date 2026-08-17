<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Articles') }}
            </h2>
            <a href="{{ route('articles.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Create Article
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($articles as $article)
                        <div class="bg-white rounded-lg border shadow-sm overflow-hidden flex flex-col">
                            <!-- Image Header -->
                            @if($article->image && $article->image->status === 'generated')
                                <img src="{{ Storage::disk(config('automation.image_disk', 'public'))->url($article->image->path) }}" alt="{{ $article->title }}" class="w-full h-48 object-cover">
                            @else
                                <img src="https://loremflickr.com/600/400/{{ urlencode(strtolower($article->category->name ?? 'blog')) }}?random={{ $article->id }}" alt="Placeholder" class="w-full h-48 object-cover">
                            @endif
                            
                            <!-- Card Body -->
                            <div class="p-4 flex-1 flex flex-col">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">{{ $article->category->name ?? 'Uncategorized' }}</span>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        @if($article->status === 'approved' || $article->status === 'published') bg-green-100 text-green-800
                                        @elseif($article->status === 'needs_review') bg-red-100 text-red-800
                                        @elseif($article->status === 'review') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ Str::headline($article->status) }}
                                    </span>
                                </div>
                                
                                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ Str::limit($article->title, 60) }}</h3>
                                <p class="text-gray-600 text-sm flex-1">{{ Str::limit($article->excerpt, 100) }}</p>
                                
                                <!-- Actions -->
                                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                                    <a href="{{ route('articles.show', $article) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Read More &rarr;</a>
                                    
                                    <div class="flex space-x-3 text-sm">
                                        <a href="{{ route('articles.edit', $article) }}" class="text-gray-500 hover:text-indigo-600">Edit</a>
                                        <form action="{{ route('articles.destroy', $article) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-gray-500 hover:text-red-600" onclick="return confirm('Are you sure you want to delete this article?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $articles->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
