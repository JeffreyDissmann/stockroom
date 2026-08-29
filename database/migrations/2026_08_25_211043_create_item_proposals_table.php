<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-derived suggestions awaiting a human decision.
 *
 * The review agent never writes to `items`. It records what it would change
 * here, and an admin accepts or rejects each one — so a hallucinated serial
 * number costs a click rather than corrupting the inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            // The item attribute this targets: description, manufacturer,
            // model_number or serial_number. Not an enum column — the set will
            // grow, and a check constraint here would mean a migration each time.
            $table->string('field', 40);

            // What the field held when the proposal was made. Kept so the review
            // UI can show the before/after, and so an accept can be refused when
            // the value has since changed underneath it.
            $table->text('current_value')->nullable();
            $table->text('proposed_value');

            $table->string('status', 12)->default('pending');

            // Which photos produced this, and which model. Both are for
            // explaining a proposal to the person judging it — "where did this
            // come from" is the first question a wrong suggestion raises.
            $table->json('source_image_ids');
            $table->string('model');

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The review queue reads pending-by-age; the item pair drives the
            // per-item badge on the item page.
            $table->index(['status', 'created_at']);
            $table->index(['item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_proposals');
    }
};
