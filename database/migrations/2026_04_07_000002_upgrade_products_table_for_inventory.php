<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'reference')) {
                $table->string('reference')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('products', 'designation')) {
                $table->string('designation')->nullable()->after('product_name');
            }

            if (!Schema::hasColumn('products', 'type')) {
                $table->enum('type', ['product', 'service'])->default('product')->after('designation');
            }

            if (!Schema::hasColumn('products', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('section_id')->constrained('categories')->nullOnDelete();
            }

            if (!Schema::hasColumn('products', 'sale_price_ht')) {
                $table->decimal('sale_price_ht', 15, 2)->default(0)->after('category_id');
            }

            if (!Schema::hasColumn('products', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(20)->after('sale_price_ht');
            }

            if (!Schema::hasColumn('products', 'cost_price_ht')) {
                $table->decimal('cost_price_ht', 15, 2)->default(0)->after('vat_rate');
            }

            if (!Schema::hasColumn('products', 'unit_type')) {
                $table->string('unit_type', 30)->default('piece')->after('cost_price_ht');
            }

            if (!Schema::hasColumn('products', 'stock_quantity')) {
                $table->decimal('stock_quantity', 15, 3)->default(0)->after('unit_type');
            }

            if (!Schema::hasColumn('products', 'min_stock_alert_level')) {
                $table->decimal('min_stock_alert_level', 15, 3)->default(0)->after('stock_quantity');
            }

            if (!Schema::hasColumn('products', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('min_stock_alert_level');
            }

            if (!Schema::hasColumn('products', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('products', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }

            $table->index(['type', 'status']);
            $table->index(['category_id', 'stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['category_id', 'stock_quantity']);

            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('category_id');

            $table->dropColumn([
                'reference',
                'designation',
                'type',
                'sale_price_ht',
                'vat_rate',
                'cost_price_ht',
                'unit_type',
                'stock_quantity',
                'min_stock_alert_level',
                'status',
                'created_by',
                'updated_by',
                'deleted_at',
            ]);
        });
    }
};
