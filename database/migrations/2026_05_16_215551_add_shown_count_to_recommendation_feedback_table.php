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
        Schema::table('recommendation_feedback', function (Blueprint $table) {
            $table->unsignedSmallInteger('shown_count')->default(1)->after('fats');
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_feedback', function (Blueprint $table) {
            $table->dropColumn('shown_count');
        });
    }
};
