<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captains (couriers): last-mile operators who carry assigned orders.
 * Performance metrics can be aggregated from orders (delivery times, delays, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captains', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // Human-readable courier id for ops / API filters
            $table->string('full_name');
            $table->string('phone', 32)->nullable();
            $table->string('vehicle_type', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captains');
    }
};
