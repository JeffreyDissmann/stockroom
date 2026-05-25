<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('description');

            // Acquisition
            $table->string('purchased_from')->nullable()->after('quantity');
            $table->date('purchase_date')->nullable()->after('purchased_from');
            $table->decimal('purchase_price', 12, 2)->nullable()->after('purchase_date');

            // Identification
            $table->string('manufacturer')->nullable()->after('purchase_price');
            $table->string('model_number')->nullable()->after('manufacturer');
            $table->string('serial_number')->nullable()->after('model_number');

            // Warranty
            $table->boolean('lifetime_warranty')->default(false)->after('serial_number');
            $table->date('warranty_expires')->nullable()->after('lifetime_warranty');
            $table->text('warranty_details')->nullable()->after('warranty_expires');

            // Disposal / sale
            $table->string('sold_to')->nullable()->after('warranty_details');
            $table->decimal('sold_price', 12, 2)->nullable()->after('sold_to');
            $table->date('sold_date')->nullable()->after('sold_price');
            $table->text('sold_notes')->nullable()->after('sold_date');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'quantity',
                'purchased_from',
                'purchase_date',
                'purchase_price',
                'manufacturer',
                'model_number',
                'serial_number',
                'lifetime_warranty',
                'warranty_expires',
                'warranty_details',
                'sold_to',
                'sold_price',
                'sold_date',
                'sold_notes',
            ]);
        });
    }
};
