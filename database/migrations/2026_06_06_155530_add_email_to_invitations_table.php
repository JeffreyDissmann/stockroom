<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the invite was emailed to, when the admin chose to send it
     * rather than (or in addition to) copy-pasting the link. Null = the
     * original copy-paste-only flow. Also used to prefill — never lock —
     * the registration form's email field.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
