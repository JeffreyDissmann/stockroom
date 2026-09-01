<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sold_date` is on nearly every read the app makes — Item::scopeOwned and
 * scopeSold put a null check on the inventory list, a container's contents,
 * search, the command palette, tag and room counts, the dashboard statistics,
 * maintenance reminders and the battery forecast. The column shipped without an
 * index.
 *
 * The composite matches the shape those queries actually use: parent_id first
 * (browsing one container), sold_date second (owned or archived). The existing
 * ['parent_id', 'name'] index serves the ordering, not this filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->index(['parent_id', 'sold_date']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropIndex(['parent_id', 'sold_date']);
        });
    }
};
