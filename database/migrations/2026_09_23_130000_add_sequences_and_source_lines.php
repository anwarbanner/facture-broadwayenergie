<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - document_sequences: one locked counter per (table, prefix, year) so two users can never get the same number.
 * - source line links: each delivery / receipt line points to its order line, each invoice line to its
 *   delivery / receipt line. Prices and remaining quantities are read through these links instead of
 *   guessing by product id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 150)->unique(); // e.g. "client_invoices:FAC:2026"
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            $table->foreignId('sales_order_item_id')->nullable()->after('delivery_id')
                ->constrained('sales_order_items')->nullOnDelete();
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('delivery_note_id')
                ->constrained('purchase_order_items')->nullOnDelete();
        });

        Schema::table('client_invoice_items', function (Blueprint $table) {
            $table->foreignId('delivery_item_id')->nullable()->after('invoice_id')
                ->constrained('delivery_items')->nullOnDelete();
        });

        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->foreignId('delivery_note_item_id')->nullable()->after('invoice_id')
                ->constrained('delivery_note_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('delivery_note_item_id'));
        Schema::table('client_invoice_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('delivery_item_id'));
        Schema::table('delivery_note_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_order_item_id'));
        Schema::table('delivery_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_order_item_id'));
        Schema::dropIfExists('document_sequences');
    }
};
