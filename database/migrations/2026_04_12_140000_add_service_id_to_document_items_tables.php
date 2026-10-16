<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_quote_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('client_invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('client_invoice_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
            $table->unsignedInteger('product_id')->nullable()->change();
        });

        Schema::table('purchase_quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_quote_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_note_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
        });

        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_invoice_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_invoice_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_note_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });

        Schema::table('purchase_quote_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_quote_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });

        Schema::table('client_invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoice_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });

        Schema::table('sales_quote_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_quote_items', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            $table->unsignedInteger('product_id')->nullable(false)->change();
        });
    }
};
