<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The device's battery type ("AA", "CR2032", "AA ×4"). A fixed property
     * of the item, not the cycle — every battery you put in a CR2032 sensor
     * is a CR2032. Free string (App\Enums\BatteryType is the curated picker
     * list, not a DB cast) so an unusual cell can still be recorded.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('battery_type')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('battery_type');
        });
    }
};
