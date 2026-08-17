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
            $table->enum('quota_mode', ['limited', 'unlimited'])->default('limited')->after('quota_period');
            $table->string('timezone')->default('Asia/Kolkata')->after('quota_mode');
            $table->string('completion_state')->default('published')->after('timezone');
            $table->boolean('reserve_quota_on_approval')->default(false)->after('completion_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_profiles', function (Blueprint $table) {
            $table->dropColumn(['quota_mode', 'timezone', 'completion_state', 'reserve_quota_on_approval']);
        });
    }
};
