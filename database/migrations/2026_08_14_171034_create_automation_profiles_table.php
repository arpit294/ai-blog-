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
        Schema::create('automation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('niche');
            $table->string('target_audience');
            $table->string('language');
            $table->string('tone');
            $table->integer('min_words');
            $table->integer('max_words');
            $table->integer('quota_count');
            $table->enum('quota_period', ['daily', 'weekly', 'monthly', 'custom']);
            $table->boolean('generate_seo')->default(false);
            $table->boolean('generate_image')->default(false);
            $table->enum('duplicate_mode', ['strict', 'standard', 'off'])->default('standard');
            $table->decimal('duplicate_threshold', 3, 2)->default(0.85);
            $table->enum('publish_mode', ['draft', 'review', 'scheduled', 'auto_publish'])->default('draft');
            $table->enum('status', ['active', 'paused', 'disabled'])->default('paused');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_profiles');
    }
};
