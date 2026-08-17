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
        Schema::create('keyword_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automation_profiles')->cascadeOnDelete();
            $table->string('keyword')->index();
            $table->integer('search_volume')->nullable();
            $table->integer('competition_score')->nullable();
            $table->string('status')->default('available'); // available, used
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();
            
            // Prevent duplicate keywords per profile
            $table->unique(['automation_id', 'keyword']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keyword_candidates');
    }
};
