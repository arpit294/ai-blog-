<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AutomationProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = \App\Models\User::first();

        if ($user) {
            \App\Models\AutomationProfile::firstOrCreate([
                'name' => 'Tech Blog Automation'
            ], [
                'user_id' => $user->id,
                'niche' => 'Technology',
                'target_audience' => 'Developers',
                'language' => 'English',
                'tone' => 'Professional + Technical',
                'min_words' => 1500,
                'max_words' => 2000,
                'quota_count' => 3,
                'quota_period' => 'weekly',
                'generate_seo' => true,
                'generate_image' => true,
                'duplicate_mode' => 'strict',
                'duplicate_threshold' => 0.85,
                'publish_mode' => 'review',
                'status' => 'paused',
            ]);
        }
    }
}
