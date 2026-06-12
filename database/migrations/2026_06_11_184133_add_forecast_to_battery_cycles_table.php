<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cached snapshot of the depletion projection for this cycle, written by
     * the RefreshBatteryForecast job after each reading. The API and the web
     * panel read it instead of re-running the regression, so recording a level
     * never blocks on the analysis. Null until the job has produced a fit.
     */
    public function up(): void
    {
        Schema::table('battery_cycles', function (Blueprint $table) {
            $table->json('forecast')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('battery_cycles', function (Blueprint $table) {
            $table->dropColumn('forecast');
        });
    }
};
