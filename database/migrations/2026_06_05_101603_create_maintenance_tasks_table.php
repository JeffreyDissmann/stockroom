<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recurring (or one-off) maintenance schedule attached to an item,
     * e.g. "change smoke detector battery every 12 months".
     *
     * Three schedule types share the table; the type decides which columns
     * are populated:
     *  - interval: interval_value + interval_unit (next due = last completion + interval)
     *  - calendar: rrule, RFC 5545 (fixed cadence regardless of completion date)
     *  - one_off:  next_due_at only; archived (is_active = false) on completion
     *
     * next_due_at is a stored, indexed projection maintained exclusively by
     * the MaintenanceSchedule service — the daily digest scan and the global
     * maintenance page filter/sort on it in SQL, so it must not be derived
     * per-row at read time.
     */
    public function up(): void
    {
        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('schedule_type', 24);
            $table->unsignedSmallInteger('interval_value')->nullable();
            $table->string('interval_unit', 8)->nullable();
            $table->text('rrule')->nullable();
            $table->date('next_due_at')->nullable();
            $table->date('last_completed_at')->nullable();
            // How many days before next_due_at the task enters the digest.
            $table->unsignedSmallInteger('reminder_lead_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The digest scan and the global page both query active tasks
            // by due date; the item Show page lists a single item's tasks
            // in due order.
            $table->index(['is_active', 'next_due_at']);
            $table->index(['item_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};
