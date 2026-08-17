<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automation_profiles', function (Blueprint $table) {
            $table->string('image_aspect_ratio')->default('16:9');
            $table->string('image_lora')->nullable(); // JSON or comma separated string of LoRA weights, optional
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_profiles', function (Blueprint $table) {
            $table->dropColumn(['image_aspect_ratio', 'image_lora']);
        });
    }
};
