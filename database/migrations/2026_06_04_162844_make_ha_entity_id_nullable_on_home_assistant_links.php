<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A link can identify its Home Assistant target by device OR entity — an
     * item usually maps to a whole device (which owns many entities), and the
     * device-page URL carries only the device id. Make ha_entity_id nullable so
     * device-only links are valid; "at least one of entity/device" is enforced
     * in the request validation, not the schema.
     */
    public function up(): void
    {
        Schema::table('home_assistant_links', function (Blueprint $table) {
            $table->string('ha_entity_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('home_assistant_links', function (Blueprint $table) {
            $table->string('ha_entity_id')->nullable(false)->change();
        });
    }
};
