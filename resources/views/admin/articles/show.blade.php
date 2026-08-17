<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight flex justify-between items-center">
            <span>{{ __('Article Review') }}: {{ $article->title }}</span>
            <span class="px-3 py-1 text-sm rounded-full 
                @if($article->status === 'approved' || $article->status === 'published') bg-green-100 text-green-800
                @elseif($article->status === 'needs_review') bg-red-100 text-red-800
                @elseif($article->status === 'review') bg-yellow-100 text-yellow-800
                @else bg-gray-100 text-gray-800 @endif">
                {{ Str::headline($article->status) }}
            </span>
        </h2>
    </x-slot>

    <div class="py-12 bg-gray-50">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <!-- Alerts -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Quick Actions -->
            <div class="bg-white shadow-sm sm:rounded-lg p-4 flex space-x-4 items-center justify-center border-b-4 border-indigo-500">
                <form action="{{ route('articles.approve', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-full transition-colors">Approve for Publishing</button>
                </form>
                <form action="{{ route('articles.reject', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-700 font-semibold py-2 px-6 rounded-full border border-red-200 transition-colors">Reject</button>
                </form>
                <form action="{{ route('articles.regenerateTitle', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-full transition-colors">Regen SEO</button>
                </form>
                <form action="{{ route('articles.rerunQualityChecks', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold py-2 px-4 rounded-full transition-colors">Run Quality Check</button>
                </form>
            </div>

            <!-- Article Reading View -->
            <article class="bg-white shadow-xl sm:rounded-2xl overflow-hidden">
                <!-- Featured Image -->
                @if($article->image && $article->image->status === 'generated')
                    <div class="w-full h-80 overflow-hidden relative group">
                        <img src="{{ Storage::disk(config('automation.image_disk', 'public'))->url($article->image->path) }}" alt="{{ $article->image->alt_text }}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <form action="{{ route('articles.regenerateImage', $article) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="bg-white text-gray-900 font-bold py-2 px-6 rounded-full shadow-lg hover:bg-gray-100">Regenerate Image</button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="w-full h-48 bg-gradient-to-r from-gray-200 to-gray-300 flex items-center justify-center relative">
                        <div class="text-gray-500 italic">No featured image</div>
                        <form action="{{ route('articles.regenerateImage', $article) }}" method="POST" class="absolute bottom-4 right-4">
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-white text-gray-700 text-xs font-bold py-1 px-3 rounded shadow hover:bg-gray-50">Generate Image</button>
                        </form>
                    </div>
                @endif

                <!-- Article Header -->
                <header class="px-8 pt-10 pb-6 border-b border-gray-100">
                    <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight tracking-tight mb-6 font-serif">
                        {{ $article->title }}
                    </h1>
                    
                    <div class="flex items-center text-sm text-gray-500 space-x-6">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            {{ $article->created_at->format('M j, Y') }}
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            @php
                                $wordCount = str_word_count(strip_tags($article->content));
                                $readTime = max(1, ceil($wordCount / 200));
                            @endphp
                            {{ $readTime }} min read ({{ number_format($wordCount) }} words)
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                {{ $article->topic->category ?? 'General' }}
                            </span>
                        </div>
                    </div>
                </header>

                <!-- Article Body -->
                <div class="px-8 py-10">
                    @php 
                        $content = json_decode($article->content, true); 
                    @endphp
                    
                    <div class="prose prose-lg prose-indigo max-w-none text-gray-800 leading-relaxed font-serif" style="line-height: 1.8;">
                        @if($content)
                            @if(!empty($content['introduction']))
                                <div class="text-xl text-gray-600 leading-relaxed mb-10 italic">
                                    {!! nl2br(e($content['introduction'])) !!}
                                </div>
                            @endif
                            
                            @foreach($content['sections'] ?? [] as $index => $section)
                                <div class="mb-12 group relative">
                                    <h2 class="text-2xl font-bold text-gray-900 mt-12 mb-6 font-sans tracking-tight">{{ $section['heading'] ?? '' }}</h2>
                                    <div class="text-lg">
                                        {!! nl2br(e($section['content'] ?? '')) !!}
                                    </div>
                                    
                                    <!-- Contextual Action: Regenerate Section -->
                                    <div class="absolute -left-4 top-0 -ml-24 opacity-0 group-hover:opacity-100 transition-opacity hidden md:block">
                                        <form action="{{ route('articles.regenerateSection', $article) }}" method="POST" class="bg-white shadow rounded p-2 border text-xs">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="heading" value="{{ $section['heading'] ?? '' }}">
                                            <input type="text" name="reason" placeholder="Regen reason..." class="w-full text-xs border-gray-300 rounded mb-1 px-2 py-1" required>
                                            <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 rounded">Regen</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach

                            @if(!empty($content['conclusion']))
                                <hr class="my-10 border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-900 mb-6 font-sans tracking-tight">Conclusion</h2>
                                <div class="text-lg">
                                    {!! nl2br(e($content['conclusion'])) !!}
                                </div>
                            @endif

                            @if(!empty($content['faq']))
                                <div class="mt-16 bg-gray-50 rounded-xl p-8 border border-gray-100">
                                    <h3 class="text-2xl font-bold text-gray-900 mb-6 font-sans">Frequently Asked Questions</h3>
                                    <div class="space-y-6">
                                        @foreach($content['faq'] as $faq)
                                            <div>
                                                <h4 class="text-lg font-bold text-gray-900">{{ $faq['question'] }}</h4>
                                                <p class="mt-2 text-gray-600">{{ $faq['answer'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="text-lg">
                                {!! $article->content !!}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="bg-gray-50 px-8 py-6 border-t border-gray-100">
                    <form action="{{ route('articles.requestChanges', $article) }}" method="POST" class="flex items-center space-x-4">
                        @csrf @method('PATCH')
                        <label class="font-medium text-sm text-gray-700 flex-shrink-0">Request Revisions:</label>
                        <input type="text" name="reason" class="flex-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="E.g., Make it more professional, expand on the third section..." required>
                        <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded-md shadow-sm transition-colors text-sm">Send Request</button>
                    </form>
                </div>
            </article>

            <!-- Metadata Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Quality Check -->
                <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Quality Score
                    </h3>
                    @if($article->qualityCheck)
                        <div class="flex items-center justify-between mb-6 pb-6 border-b border-gray-100">
                            <div>
                                <div class="text-4xl font-extrabold {{ $article->qualityCheck->overall_score >= 0.8 ? 'text-green-500' : 'text-red-500' }}">
                                    {{ number_format($article->qualityCheck->overall_score * 100, 0) }}<span class="text-2xl">%</span>
                                </div>
                                <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold mt-1">Overall Rating</div>
                            </div>
                            <div class="w-16 h-16 rounded-full border-4 flex items-center justify-center {{ $article->qualityCheck->overall_score >= 0.8 ? 'border-green-100' : 'border-red-100' }}">
                                <svg class="w-8 h-8 {{ $article->qualityCheck->overall_score >= 0.8 ? 'text-green-500' : 'text-red-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($article->qualityCheck->overall_score >= 0.8)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    @endif
                                </svg>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex justify-between items-center p-2 rounded bg-gray-50"><span class="text-gray-600">Structure</span> <span class="font-bold">{{ $article->qualityCheck->structure_score * 100 }}%</span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-gray-50"><span class="text-gray-600">Completeness</span> <span class="font-bold">{{ $article->qualityCheck->completeness_score * 100 }}%</span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-gray-50"><span class="text-gray-600">SEO</span> <span class="font-bold">{{ $article->qualityCheck->seo_score * 100 }}%</span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-gray-50"><span class="text-gray-600">Readability</span> <span class="font-bold">{{ $article->qualityCheck->readability_score * 100 }}%</span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-gray-50"><span class="text-gray-600">Uniqueness</span> <span class="font-bold">{{ $article->qualityCheck->uniqueness_score * 100 }}%</span></div>
                            <div class="flex justify-between items-center p-2 rounded bg-gray-50"><span class="text-gray-600">Tech Validity</span> <span class="font-bold">{{ $article->qualityCheck->technical_validity_score * 100 }}%</span></div>
                        </div>
                    @else
                        <div class="text-sm text-gray-500 italic p-4 bg-gray-50 rounded">No quality checks run yet.</div>
                    @endif
                </div>

                <!-- SEO Data -->
                <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        SEO Metadata
                    </h3>
                    @if($article->seo)
                        <div class="space-y-4 text-sm">
                            <div class="bg-gray-50 p-3 rounded">
                                <span class="text-xs text-gray-400 uppercase tracking-wide font-semibold block mb-1">Focus Keyword</span>
                                <div class="font-bold text-indigo-700 bg-indigo-50 inline-block px-2 py-1 rounded">{{ $article->seo->focus_keyword ?: '-' }}</div>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wide font-semibold block mb-1">SEO Title</span>
                                <div class="font-medium text-gray-900">{{ $article->seo->seo_title }}</div>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wide font-semibold block mb-1">Meta Description</span>
                                <div class="text-gray-600 leading-relaxed">{{ $article->seo->meta_description }}</div>
                            </div>
                        </div>
                    @else
                        <div class="text-sm text-gray-500 italic p-4 bg-gray-50 rounded">No SEO metadata generated.</div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
