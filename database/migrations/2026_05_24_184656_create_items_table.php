<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('type', 16)->default('item')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
