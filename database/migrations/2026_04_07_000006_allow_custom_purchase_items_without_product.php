<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_quote_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('product_id');
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('product_id');
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_note_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('product_id');
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_invoice_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('product_id');
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_invoice_items', 'item_name')) {
                $table->dropColumn('item_name');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_note_items', 'item_name')) {
                $table->dropColumn('item_name');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'item_name')) {
                $table->dropColumn('item_name');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });

        Schema::table('purchase_quote_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_quote_items', 'item_name')) {
                $table->dropColumn('item_name');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });
    }
};
