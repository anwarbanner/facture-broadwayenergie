<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simple mode: a bon de livraison made in one click from a quote (no sales order), printed only:
 * it never moves stock (moves_stock = false). Deliveries made from orders keep moving stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE deliveries MODIFY sales_order_id BIGINT UNSIGNED NULL');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('sales_quote_id')->nullable()->after('sales_order_id')
                ->constrained('sales_quotes')->restrictOnDelete();
            $table->boolean('moves_stock')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_quote_id');
            $table->dropColumn('moves_stock');
        });

        DB::statement('ALTER TABLE deliveries MODIFY sales_order_id BIGINT UNSIGNED NOT NULL');
    }
};
