<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many between Stockroom items and Paperless-ngx documents.
     *
     * Modelled as one row per (item, doc) pair because the "doc" side
     * doesn't have a corresponding model on our side — we never store
     * doc metadata, only its remote id. The Paperless integration job
     * inserts these; the item Show page reads them to render the
     * "From document" click-through link.
     *
     * Unique on (item_id, paperless_document_id) so re-running an intake
     * for the same pair is idempotent.
     */
    public function up(): void
    {
        Schema::create('paperless_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            // Foreign key to a record in *Paperless*, not a Stockroom table —
            // no constraint we can enforce here.
            $table->unsignedBigInteger('paperless_document_id');
            $table->timestamps();

            $table->unique(['item_id', 'paperless_document_id']);
            // The doc-side lookup ("does this doc already link to anything?")
            // happens in the intake job's link-instead-of-create branch.
            $table->index('paperless_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paperless_links');
    }
};
