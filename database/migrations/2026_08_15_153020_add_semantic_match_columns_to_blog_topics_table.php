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
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE blog_topics MODIFY COLUMN status ENUM('candidate', 'reserved', 'rejected', 'used', 'review_required') DEFAULT 'candidate'");
        }

        Schema::table('blog_topics', function (Blueprint $table) {
            $table->string('matched_record_type')->nullable()->after('rejection_reason');
            $table->unsignedBigInteger('matched_record_id')->nullable()->after('matched_record_type');
            $table->decimal('similarity_score', 5, 4)->nullable()->after('matched_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE blog_topics MODIFY COLUMN status ENUM('candidate', 'reserved', 'rejected', 'used') DEFAULT 'candidate'");
        }

        Schema::table('blog_topics', function (Blueprint $table) {
            $table->dropColumn(['matched_record_type', 'matched_record_id', 'similarity_score']);
        });
    }
};
