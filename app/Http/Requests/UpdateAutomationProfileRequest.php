<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'niche' => 'required|string',
            'target_audience' => 'required|string',
            'language' => 'required|string|max:50',
            'tone' => 'required|string',
            'min_words' => 'required|integer|min:1',
            'max_words' => 'required|integer|gte:min_words',
            'quota_count' => 'required|integer|min:1',
            'quota_period' => 'required|in:daily,weekly,monthly,custom',
            'generate_seo' => 'boolean',
            'generate_image' => 'boolean',
            'duplicate_mode' => 'required|in:strict,standard,off',
            'duplicate_threshold' => 'required|numeric|min:0|max:1',
            'publish_mode' => 'required|in:draft,review,scheduled,auto_publish',
            'status' => 'required|in:active,paused,disabled',
            'schedules' => 'nullable|array',
            'schedules.*.weekday' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.time' => 'required|date_format:H:i',
        ];
    }
}
