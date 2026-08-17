<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ isset($automationProfile) ? __('Edit Automation Profile') : __('Create Automation Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form action="{{ isset($automationProfile) ? route('automation-profiles.update', $automationProfile) : route('automation-profiles.store') }}" method="POST">
                    @csrf
                    @if(isset($automationProfile))
                        @method('PUT')
                    @endif

                    <!-- 1. Basic Information -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">1. Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <x-input-label for="name" :value="__('Automation Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $automationProfile->name ?? '')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="niche" :value="__('Niche')" />
                            <x-text-input id="niche" class="block mt-1 w-full" type="text" name="niche" :value="old('niche', $automationProfile->niche ?? '')" required />
                            <x-input-error :messages="$errors->get('niche')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="target_audience" :value="__('Target Audience')" />
                            <x-text-input id="target_audience" class="block mt-1 w-full" type="text" name="target_audience" :value="old('target_audience', $automationProfile->target_audience ?? '')" required />
                            <x-input-error :messages="$errors->get('target_audience')" class="mt-2" />
                        </div>
                    </div>

                    <!-- 2. Writing Configuration -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">2. Writing Configuration</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <x-input-label for="language" :value="__('Language')" />
                            <x-text-input id="language" class="block mt-1 w-full" type="text" name="language" :value="old('language', $automationProfile->language ?? 'English')" required />
                            <x-input-error :messages="$errors->get('language')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tone" :value="__('Tone')" />
                            <x-text-input id="tone" class="block mt-1 w-full" type="text" name="tone" :value="old('tone', $automationProfile->tone ?? '')" required />
                            <x-input-error :messages="$errors->get('tone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="min_words" :value="__('Minimum Words')" />
                            <x-text-input id="min_words" class="block mt-1 w-full" type="number" name="min_words" :value="old('min_words', $automationProfile->min_words ?? '')" required />
                            <x-input-error :messages="$errors->get('min_words')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="max_words" :value="__('Maximum Words')" />
                            <x-text-input id="max_words" class="block mt-1 w-full" type="number" name="max_words" :value="old('max_words', $automationProfile->max_words ?? '')" required />
                            <x-input-error :messages="$errors->get('max_words')" class="mt-2" />
                        </div>
                    </div>

                    <!-- 3. Automation Configuration -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">3. Automation Configuration</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <x-input-label for="quota_count" :value="__('Quota Count')" />
                            <x-text-input id="quota_count" class="block mt-1 w-full" type="number" name="quota_count" :value="old('quota_count', $automationProfile->quota_count ?? '')" required />
                            <x-input-error :messages="$errors->get('quota_count')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="quota_period" :value="__('Quota Period')" />
                            <select id="quota_period" name="quota_period" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                <option value="daily" {{ old('quota_period', $automationProfile->quota_period ?? '') == 'daily' ? 'selected' : '' }}>Daily</option>
                                <option value="weekly" {{ old('quota_period', $automationProfile->quota_period ?? '') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="monthly" {{ old('quota_period', $automationProfile->quota_period ?? '') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="custom" {{ old('quota_period', $automationProfile->quota_period ?? '') == 'custom' ? 'selected' : '' }}>Custom</option>
                            </select>
                            <x-input-error :messages="$errors->get('quota_period')" class="mt-2" />
                        </div>
                    </div>

                    <!-- 4. Content Options -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">4. Content Options</h3>
                    <div class="flex items-center gap-6 mb-6">
                        <label for="generate_seo" class="inline-flex items-center">
                            <input id="generate_seo" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="generate_seo" value="1" {{ old('generate_seo', $automationProfile->generate_seo ?? false) ? 'checked' : '' }}>
                            <span class="ms-2 text-sm text-gray-600">{{ __('SEO Enabled') }}</span>
                        </label>
                        <label for="generate_image" class="inline-flex items-center">
                            <input id="generate_image" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="generate_image" value="1" {{ old('generate_image', $automationProfile->generate_image ?? false) ? 'checked' : '' }}>
                            <span class="ms-2 text-sm text-gray-600">{{ __('Image Enabled') }}</span>
                        </label>
                    </div>

                    <!-- 5. Duplicate Protection -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">5. Duplicate Protection</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <x-input-label for="duplicate_mode" :value="__('Duplicate Mode')" />
                            <select id="duplicate_mode" name="duplicate_mode" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                <option value="strict" {{ old('duplicate_mode', $automationProfile->duplicate_mode ?? '') == 'strict' ? 'selected' : '' }}>Strict</option>
                                <option value="standard" {{ old('duplicate_mode', $automationProfile->duplicate_mode ?? '') == 'standard' ? 'selected' : '' }}>Standard</option>
                                <option value="off" {{ old('duplicate_mode', $automationProfile->duplicate_mode ?? '') == 'off' ? 'selected' : '' }}>Off</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="duplicate_threshold" :value="__('Duplicate Threshold')" />
                            <x-text-input id="duplicate_threshold" class="block mt-1 w-full" type="number" step="0.01" name="duplicate_threshold" :value="old('duplicate_threshold', $automationProfile->duplicate_threshold ?? '0.85')" required />
                            <p class="text-sm text-gray-500 mt-1">Value between 0 and 1.</p>
                            <x-input-error :messages="$errors->get('duplicate_threshold')" class="mt-2" />
                        </div>
                    </div>

                    <!-- 6. Publishing -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">6. Publishing</h3>
                    <div class="mb-6">
                        <x-input-label for="publish_mode" :value="__('Publishing Mode')" />
                        <select id="publish_mode" name="publish_mode" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full md:w-1/2">
                            <option value="draft" {{ old('publish_mode', $automationProfile->publish_mode ?? '') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="review" {{ old('publish_mode', $automationProfile->publish_mode ?? '') == 'review' ? 'selected' : '' }}>Review</option>
                            <option value="scheduled" {{ old('publish_mode', $automationProfile->publish_mode ?? '') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="auto_publish" {{ old('publish_mode', $automationProfile->publish_mode ?? '') == 'auto_publish' ? 'selected' : '' }}>Auto Publish</option>
                        </select>
                        <x-input-error :messages="$errors->get('publish_mode')" class="mt-2" />
                    </div>

                    <!-- 7. Status -->
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">7. Status</h3>
                    <div class="mb-6">
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full md:w-1/2">
                            <option value="active" {{ old('status', $automationProfile->status ?? '') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="paused" {{ old('status', $automationProfile->status ?? '') == 'paused' ? 'selected' : '' }}>Paused</option>
                            <option value="disabled" {{ old('status', $automationProfile->status ?? '') == 'disabled' ? 'selected' : '' }}>Disabled</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-4 pt-4 border-t">
                        <x-primary-button class="ms-4">
                            {{ isset($automationProfile) ? __('Update Profile') : __('Create Profile') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
