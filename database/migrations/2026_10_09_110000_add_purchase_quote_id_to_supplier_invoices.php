<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Simple mode: a supplier invoice made in one click from a supplier quote ("devis fournisseur"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('purchase_quote_id')->nullable()->after('delivery_note_id')
                ->constrained('purchase_quotes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_quote_id');
        });
    }
};
