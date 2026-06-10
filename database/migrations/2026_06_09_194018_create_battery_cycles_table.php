<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per physical battery installed in an item — "the current
     * battery" is the open cycle (removed_at null); closed cycles are the
     * history that powers the depletion chart and battery-longevity stats.
     *
     * The "at most one open cycle per item" invariant is enforced in the
     * BatteryRecorder service (a partial unique index isn't uniformly
     * portable across pgsql/sqlite via the schema builder).
     *
     * The battery TYPE lives on the item (items.battery_type) — it's a fixed
     * property of the device, not something that changes per battery. A cycle
     * only records this physical battery's lifespan; `notes` captures any
     * one-off ("used rechargeable pack").
     */
    public function up(): void
    {
        Schema::create('battery_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->timestamp('installed_at');
            $table->timestamp('removed_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            // "open cycle for this item" + "newest cycle first" lookups.
            $table->index(['item_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_cycles');
    }
};
