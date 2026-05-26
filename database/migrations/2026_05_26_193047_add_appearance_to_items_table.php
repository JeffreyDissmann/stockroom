<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            // An optional chosen icon (name from the curated set) shown on the
            // tile for rooms and containers, which rarely have photos.
            $table->string('icon', 40)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
