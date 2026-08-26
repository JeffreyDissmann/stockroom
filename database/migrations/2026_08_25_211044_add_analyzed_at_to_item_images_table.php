<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when the review agent last looked at a photo.
 *
 * Deliberately on the image rather than the item: an item counts as needing
 * review while any of its photos is unanalysed, so adding a photo months later
 * re-queues that item on its own, with no flag to remember to clear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_images', function (Blueprint $table) {
            $table->timestamp('analyzed_at')->nullable()->after('is_primary');

            // The batch job's only query: find images never looked at.
            $table->index('analyzed_at');
        });
    }

    public function down(): void
    {
        Schema::table('item_images', function (Blueprint $table) {
            $table->dropIndex(['analyzed_at']);
            $table->dropColumn('analyzed_at');
        });
    }
};
