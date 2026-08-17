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
        Schema::table('blog_topics', function (Blueprint $table) {
            $table->string('target_keyword')->nullable();
            $table->integer('estimated_search_volume')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_topics', function (Blueprint $table) {
            $table->dropColumn(['target_keyword', 'estimated_search_volume']);
        });
    }
};
