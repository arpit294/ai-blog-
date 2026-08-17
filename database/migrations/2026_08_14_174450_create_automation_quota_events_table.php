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
        Schema::create('automation_quota_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('completion_type');
            $table->timestamp('quota_window_start');
            $table->timestamp('quota_window_end');
            $table->timestamp('counted_at');
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('automation_profile_id');
            $table->index('automation_run_id');
            $table->index('quota_window_start');
            $table->index('quota_window_end');
            $table->index('completion_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_quota_events');
    }
};
