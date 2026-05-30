<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Symmetric many-to-many between items — "A is related to B". Used by the
     * "related items" section on each item's Show page, including the
     * box-for-item flow where the link survives moving the box away from the
     * original parent.
     *
     * Stores both directions (A→B and B→A) so a single `BelongsToMany` on the
     * model can answer "what's related to me?" without UNIONs. The doubling
     * is encapsulated in Item::linkRelated() / unlinkRelated() so consumers
     * never see the duplication. See git log for the design trade-off.
     */
    public function up(): void
    {
        Schema::create('item_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('related_item_id')->constrained('items')->cascadeOnDelete();
            $table->timestamps();

            // One pair, one row per direction. The unique covers (1,2) and
            // (2,1) as distinct rows by design (see comment above).
            $table->unique(['item_id', 'related_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_relations');
    }
};
