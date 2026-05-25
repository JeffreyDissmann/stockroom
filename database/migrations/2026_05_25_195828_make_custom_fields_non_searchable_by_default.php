<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            // Fields are excluded from search unless explicitly opted in.
            $table->boolean('is_searchable')->default(false)->change();
        });

        // Bring existing fields (including system ones) in line with the new default.
        DB::table('custom_fields')->update(['is_searchable' => false]);
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->boolean('is_searchable')->default(true)->change();
        });
    }
};
