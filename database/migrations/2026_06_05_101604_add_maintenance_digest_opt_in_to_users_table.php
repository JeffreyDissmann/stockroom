<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user opt-in for the daily maintenance digest email. Defaults to
     * on — the household is small and the digest only sends when there is
     * something due, so opting out is the exceptional case.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('maintenance_digest_opt_in')->default(true)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('maintenance_digest_opt_in');
        });
    }
};
