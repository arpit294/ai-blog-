<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Laravel', 'slug' => 'laravel', 'description' => 'Laravel Framework', 'status' => 'active'],
            ['name' => 'PHP', 'slug' => 'php', 'description' => 'PHP Programming', 'status' => 'active'],
            ['name' => 'AI', 'slug' => 'ai', 'description' => 'Artificial Intelligence', 'status' => 'active'],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
