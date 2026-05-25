<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            // Every type is serialised to text and interpreted by the field's type.
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'custom_field_id']);
            // Supports the "which item has custom field X = Y" lookup used by imports.
            $table->index('custom_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
