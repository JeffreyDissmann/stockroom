<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('type')->default(CustomFieldType::Text->value);
            $table->unsignedInteger('sort_order')->default(0);
            // System fields (e.g. an import's source id) are locked: not user
            // editable/deletable and hidden from the normal item UI.
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
