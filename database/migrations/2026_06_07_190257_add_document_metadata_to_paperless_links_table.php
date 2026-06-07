<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached display metadata for the linked Paperless document, so the
     * Connections card and the assistant can show "Rechnung · AEG receipt"
     * instead of a bare #id without a Paperless round-trip per render.
     *
     * Snapshots, not live state: written wherever a link is created (manual
     * link, intake, adopt command) and refreshed by the "Repair Paperless
     * links" job — a rename in Paperless heals on the next repair run. All
     * nullable: rows from before this migration carry no metadata until
     * repaired, and a document may have no type/correspondent at all.
     * `document_type` and `correspondent` store the resolved NAMES (the
     * Paperless API returns ids; we never need those again after resolving).
     */
    public function up(): void
    {
        Schema::table('paperless_links', function (Blueprint $table) {
            $table->string('document_title')->nullable()->after('paperless_document_id');
            $table->string('document_type')->nullable()->after('document_title');
            // Stored for completeness (it's free at write time); not yet
            // rendered anywhere.
            $table->string('correspondent')->nullable()->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('paperless_links', function (Blueprint $table) {
            $table->dropColumn(['document_title', 'document_type', 'correspondent']);
        });
    }
};
