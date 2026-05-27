<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoted_places', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Food & price info (for relevance scoring)
            $table->json('food_types');          // e.g. ["chicken", "indonesian"]
            $table->string('price_display');     // e.g. "Rp 25k - 50k"
            $table->unsignedInteger('min_price')->default(0); // in IDR, for budget matching

            // Media & contact
            $table->string('photo_path')->nullable();  // stored in storage/app/public
            $table->string('whatsapp')->nullable();    // owner contact
            $table->string('gmaps_url')->nullable();   // optional Google Maps link

            // Scheduling & status
            $table->boolean('is_active')->default(false);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            $table->timestamps();
        });

        // Add is_admin to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoted_places');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};