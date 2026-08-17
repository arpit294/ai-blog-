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
        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2)->default(0);
            $table->decimal('structure_score', 5, 2)->default(0);
            $table->decimal('completeness_score', 5, 2)->default(0);
            $table->decimal('seo_score', 5, 2)->default(0);
            $table->decimal('readability_score', 5, 2)->default(0);
            $table->decimal('uniqueness_score', 5, 2)->default(0);
            $table->decimal('technical_validity_score', 5, 2)->default(0);
            $table->string('status')->default('needs_review'); // passed, failed, needs_review
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_checks');
    }
};
