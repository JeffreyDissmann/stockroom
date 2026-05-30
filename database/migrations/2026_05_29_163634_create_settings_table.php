<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Support\ActivityLogStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Generic single-household key/value store for app preferences that live in
     * the DB (admin-editable from /household/preferences), as opposed to env-
     * driven config like CURRENCY / APP_LOCALE. The first user is the box-tag
     * pointer (#9): the "Create a box for this item" action attaches whichever
     * tag this setting names, so the household can rename it / swap it without
     * touching code.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // `key` is reserved in MySQL/MariaDB without quoting, but Laravel's
            // grammar handles that — we keep it because it's idiomatic.
            $table->string('key')->unique();
            // Stored as JSON text so a setting can hold an int (tag id),
            // a string, a bool, or a nested array without a separate type
            // column. The Setting model casts on read.
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Bootstrap the default "Box" tag + point box_tag_id at it. Idempotent
        // — re-running on an existing install reuses any tag named "Box".
        //
        // Disable Spatie ActivityLog for just this write — bootstrapping a
        // system tag isn't a user action and shouldn't appear in the audit
        // log. ActivityLogStatus is the canonical toggle (LogsActivity reads
        // it on every model event). Wrapped in try/finally so a hypothetical
        // failure can't leave logging globally disabled.
        $logStatus = app(ActivityLogStatus::class);
        $logStatus->disable();
        try {
            $boxTag = Tag::query()->firstOrCreate(
                ['name' => 'Box'],
                ['color' => '#a78bfa'], // muted purple — distinct from common item colours
            );
        } finally {
            $logStatus->enable();
        }

        Setting::set('box_tag_id', $boxTag->id);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        // Leave the "Box" tag behind — users may have attached it manually.
    }
};
