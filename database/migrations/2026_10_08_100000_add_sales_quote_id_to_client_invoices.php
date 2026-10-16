<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Simple mode: a quote can be cashed in directly (quote -> validated, paid invoice). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->foreignId('sales_quote_id')->nullable()->after('delivery_id')
                ->constrained('sales_quotes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_quote_id');
        });
    }
};
