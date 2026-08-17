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
            $table->decimal('semantic_duplicate_threshold', 3, 2)->default(0.85)->after('duplicate_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_profiles', function (Blueprint $table) {
            $table->dropColumn('semantic_duplicate_threshold');
        });
    }
};
