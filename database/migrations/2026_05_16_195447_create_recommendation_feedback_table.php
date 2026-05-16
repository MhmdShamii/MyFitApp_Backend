<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('recommendation_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('meal_title', 255);
            $table->unsignedBigInteger('meal_post_id')->nullable();
            $table->string('source_type', 20);
            $table->string('action', 20)->default('logged');
            $table->string('meal_time_slot', 20);
            $table->smallInteger('logged_hour');
            $table->float('calories')->nullable();
            $table->float('protein')->nullable();
            $table->float('carbs')->nullable();
            $table->float('fats')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('user_profile')->onDelete('cascade');

            $table->index('profile_id');
            $table->index(['profile_id', 'meal_time_slot']);
            $table->index(['profile_id', 'action']);
        });

        DB::statement('ALTER TABLE recommendation_feedback ADD COLUMN embedding vector(1536) NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_feedback');
    }
};
