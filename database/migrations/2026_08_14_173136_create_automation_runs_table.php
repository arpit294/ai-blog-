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
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued, running, topic_selection, content_generation, seo_generation, image_generation, quality_check, review, published, failed, skipped
            $table->string('current_stage')->default('initialization'); // scheduler, initialization, topic_selection, content_generation, seo_generation, image_generation, quality_check, review, publishing, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('run_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for faster lookups
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
