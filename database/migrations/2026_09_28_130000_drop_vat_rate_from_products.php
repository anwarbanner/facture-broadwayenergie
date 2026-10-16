<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** VAT is one rate per document (2026-09-28): the product no longer has its own rate. */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('products', 'vat_rate')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('vat_rate'));
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'vat_rate')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('vat_rate', 5, 2)->default(20)->after('sale_price_ht');
            });
        }
    }
};
