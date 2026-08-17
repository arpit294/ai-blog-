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

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
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
            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex space-x-4 items-center">
                <form action="{{ route('articles.approve', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Approve</button>
                </form>
                <form action="{{ route('articles.reject', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Reject</button>
                </form>
                <form action="{{ route('articles.regenerateTitle', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Regenerate SEO</button>
                </form>
                <form action="{{ route('articles.rerunQualityChecks', $article) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Rerun Quality Check</button>
                </form>
            </div>

            <!-- Content Area -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Main Body -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Featured Image -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-semibold">Featured Image</h3>
                            <form action="{{ route('articles.regenerateImage', $article) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold py-1 px-3 rounded">Regenerate Image</button>
                            </form>
                        </div>
                        @if($article->image && $article->image->status === 'generated')
                            <div class="mb-4">
                                <img src="{{ Storage::disk(config('automation.image_disk', 'public'))->url($article->image->path) }}" alt="{{ $article->image->alt_text }}" class="w-full max-w-md rounded shadow-sm border">
                                <div class="text-xs text-gray-500 mt-2">Alt Text: {{ $article->image->alt_text }}</div>
                                <div class="text-xs text-gray-500">Prompt: {{ $article->image->prompt }}</div>
                            </div>
                        @elseif($article->image && $article->image->status === 'failed')
                            <div class="text-sm text-red-600 italic">Image generation failed. (Provider: {{ $article->image->provider }})</div>
                        @else
                            <div class="text-sm text-gray-500 italic">No image generated or skipped by profile settings.</div>
                        @endif
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Article Content</h3>
                        @php $content = json_decode($article->content, true); @endphp
                        
                        @if($content)
                            <div class="prose max-w-none text-sm">
                                <strong>Introduction:</strong>
                                <div>{!! nl2br(e($content['introduction'] ?? '')) !!}</div>
                                
                                @foreach($content['sections'] ?? [] as $index => $section)
                                    <div class="mt-4 p-4 border rounded relative">
                                        <h4 class="font-bold">{{ $section['heading'] ?? '' }}</h4>
                                        <div class="mt-2">{!! nl2br(e($section['content'] ?? '')) !!}</div>
                                        
                                        <!-- Regenerate Section Form -->
                                        <form action="{{ route('articles.regenerateSection', $article) }}" method="POST" class="mt-4 border-t pt-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="heading" value="{{ $section['heading'] ?? '' }}">
                                            <div class="flex space-x-2">
                                                <input type="text" name="reason" placeholder="Reason to regenerate..." class="flex-1 text-xs border-gray-300 rounded" required>
                                                <button type="submit" class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-bold py-1 px-3 rounded">Regen</button>
                                            </div>
                                        </form>
                                    </div>
                                @endforeach

                                <div class="mt-4">
                                    <strong>Conclusion:</strong>
                                    <div>{!! nl2br(e($content['conclusion'] ?? '')) !!}</div>
                                </div>
                            </div>
                        @else
                            <div class="prose max-w-none text-sm bg-gray-50 p-4 rounded border">
                                {!! $article->content !!}
                            </div>
                        @endif
                        
                        <!-- Request Global Changes Form -->
                        <form action="{{ route('articles.requestChanges', $article) }}" method="POST" class="mt-6 border-t pt-4">
                            @csrf @method('PATCH')
                            <h4 class="font-medium text-sm mb-2">Request Body Changes</h4>
                            <textarea name="reason" rows="2" class="w-full text-sm border-gray-300 rounded" placeholder="E.g., Make it more professional..." required></textarea>
                            <button type="submit" class="mt-2 bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-4 rounded text-sm">Request Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Sidebar Metadata -->
                <div class="space-y-6">
                    <!-- Quality Check -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Quality Check</h3>
                        @if($article->qualityCheck)
                            <div class="text-center mb-4">
                                <span class="text-3xl font-bold {{ $article->qualityCheck->overall_score >= 0.8 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($article->qualityCheck->overall_score * 100, 0) }}%
                                </span>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Overall Score</div>
                            </div>
                            
                            <ul class="space-y-2 text-xs">
                                <li class="flex justify-between"><span>Structure</span> <span class="font-medium">{{ $article->qualityCheck->structure_score * 100 }}%</span></li>
                                <li class="flex justify-between"><span>Completeness</span> <span class="font-medium">{{ $article->qualityCheck->completeness_score * 100 }}%</span></li>
                                <li class="flex justify-between"><span>SEO</span> <span class="font-medium">{{ $article->qualityCheck->seo_score * 100 }}%</span></li>
                                <li class="flex justify-between"><span>Readability</span> <span class="font-medium">{{ $article->qualityCheck->readability_score * 100 }}%</span></li>
                                <li class="flex justify-between"><span>Uniqueness</span> <span class="font-medium">{{ $article->qualityCheck->uniqueness_score * 100 }}%</span></li>
                                <li class="flex justify-between"><span>Tech Validity</span> <span class="font-medium">{{ $article->qualityCheck->technical_validity_score * 100 }}%</span></li>
                            </ul>

                            @if(!empty($article->qualityCheck->details))
                                <div class="mt-4 pt-4 border-t">
                                    <h4 class="font-semibold text-red-600 text-xs mb-2">Issues Detected</h4>
                                    <ul class="text-xs text-red-700 list-disc pl-4 space-y-1">
                                    @foreach($article->qualityCheck->details as $cat => $issues)
                                        @foreach($issues as $issue)
                                            <li>{{ $issue }}</li>
                                        @endforeach
                                    @endforeach
                                    </ul>
                                </div>
                            @endif
                        @else
                            <div class="text-sm text-gray-500 italic">No quality checks run yet.</div>
                        @endif
                    </div>

                    <!-- SEO Data -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 border-b pb-2">SEO Metadata</h3>
                        @if($article->seo)
                            <div class="space-y-3 text-sm">
                                <div>
                                    <span class="text-xs text-gray-500 block">SEO Title</span>
                                    <div class="font-medium">{{ $article->seo->seo_title }}</div>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">Meta Description</span>
                                    <div class="text-gray-700 text-xs">{{ $article->seo->meta_description }}</div>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">Focus Keyword</span>
                                    <div>{{ $article->seo->focus_keyword ?: '-' }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-gray-500 italic">No SEO metadata generated.</div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
