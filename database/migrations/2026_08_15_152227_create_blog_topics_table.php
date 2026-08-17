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
        Schema::create('blog_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automation_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->string('normalized_title');
            $table->text('summary');
            $table->string('category')->nullable();
            $table->string('intent')->nullable();
            $table->string('primary_keyword')->nullable();
            $table->enum('status', ['candidate', 'reserved', 'rejected', 'used', 'review_required'])->default('candidate');
            $table->enum('rejection_reason', ['exact_duplicate', 'text_duplicate', 'semantic_duplicate', 'manual_block', 'semantic_check_failed'])->nullable();
            $table->string('embedding_ref')->nullable();
            $table->foreignId('source_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->timestamps();

            $table->index(['automation_id', 'status']);
            $table->index('normalized_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_topics');
    }
};
