<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A completed piece of maintenance on an item — either the completion
     * of a maintenance task or an ad-hoc record ("repaired drawer handle"),
     * in which case maintenance_task_id is null.
     *
     * Entries are history: deleting a task keeps its entries as ad-hoc
     * records (nullOnDelete); deleting the item removes them with it.
     */
    public function up(): void
    {
        Schema::create('maintenance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('maintenance_task_id')->nullable()->constrained('maintenance_tasks')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('completed_at');
            $table->text('notes')->nullable();
            // Mirrors the precision of items.purchase_price.
            $table->decimal('cost', 12, 2)->nullable();
            $table->timestamps();

            // The item Show page lists an item's history newest-first; the
            // task side needs its latest entry.
            $table->index(['item_id', 'completed_at']);
            $table->index('maintenance_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_entries');
    }
};
