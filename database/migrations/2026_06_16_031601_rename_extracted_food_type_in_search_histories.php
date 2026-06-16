<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_histories', function (Blueprint $table) {
            $table->renameColumn('extracted_food_type', 'extracted_food_search');
        });
    }

    public function down(): void
    {
        Schema::table('search_histories', function (Blueprint $table) {
            $table->renameColumn('extracted_food_search', 'extracted_food_type');
        });
    }
};