<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A battery level sample (0-100%) at a point in time, belonging to a
     * battery cycle. Home Assistant appends these as it reports the device's
     * level; the depletion chart plots them per cycle and the forecast
     * regresses the current cycle's series to predict the next change.
     *
     * item_id is denormalised (alongside the cycle FK) so "latest reading for
     * an item" is a single indexed lookup without joining through cycles.
     */
    public function up(): void
    {
        Schema::create('battery_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battery_cycle_id')->constrained('battery_cycles')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['item_id', 'recorded_at']);
            $table->index(['battery_cycle_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_readings');
    }
};
