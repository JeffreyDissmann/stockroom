<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1:1 link between a Stockroom item and a Home Assistant entity.
     *
     * Modelled like paperless_links (the remote side has no Stockroom
     * model — we only store the back-reference), but constrained to one
     * link per item: a Home Assistant device maps to exactly one item.
     * The Home Assistant integration writes these via the v1 API so the
     * Stockroom item carries a deep link back to its HA device page.
     */
    public function up(): void
    {
        Schema::create('home_assistant_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            // The HA entity id, e.g. "sensor.living_room_tv". Indexed for the
            // reverse lookup ("which item is this entity linked to?").
            $table->string('ha_entity_id');
            // HA device-registry id; nullable because an entity may not belong
            // to a device.
            $table->string('ha_device_id')->nullable();
            // HA-supplied display name and a deep link to the device page.
            $table->string('friendly_name')->nullable();
            $table->string('url')->nullable();
            // Optional discriminator for households running more than one HA
            // instance against the same Stockroom.
            $table->string('instance_id')->nullable();
            $table->timestamps();

            // One link per item — enforces the 1:1 mapping.
            $table->unique('item_id');
            $table->index('ha_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_assistant_links');
    }
};
