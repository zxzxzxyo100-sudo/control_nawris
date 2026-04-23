<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders: core logistics entity. Status drives workflow; promised_delivery_at drives delay logic.
 *
 * Delay rule (business): an order is "delayed" when it is not terminal (completed/canceled),
 * has a promised delivery datetime in the past, and age of lateness >= delay_days threshold.
 * "With captain" means the order is currently assigned (captain_id set) while still active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique(); // External / customer-facing reference

            // Optional link to a registered customer user (portal); null for walk-in / API-only.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Assigned courier; null until dispatched to a captain.
            $table->foreignId('captain_id')->nullable()->constrained('captains')->nullOnDelete();

            // Workflow: pending -> in_transit -> completed | canceled
            $table->string('status', 32)->default('pending')->index();

            $table->timestamp('promised_delivery_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
