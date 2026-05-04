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
        Schema::create('recommendation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')
                ->constrained('search_histories')
                ->onDelete('cascade');
            $table->string('restaurant_name');
            $table->string('google_place_id')->nullable();
            $table->decimal('saw_score', 8, 6);
            $table->unsignedTinyInteger('rank');
            $table->json('criteria_breakdown');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_logs');
    }
};
