<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial credit notes: a credit note line points to the invoice line it credits (credited_item_id),
 * so the quantity still creditable on each line is known. Free lines (commercial discount) have none.
 * Existing (full) credit notes are linked line by line, in the order they were copied.
 */
return new class extends Migration {
    private const TABLES = [
        'client_invoices' => 'client_invoice_items',
        'supplier_invoices' => 'supplier_invoice_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $invoices => $items) {
            Schema::table($items, function (Blueprint $table) use ($items) {
                $table->foreignId('credited_item_id')->nullable()->after('invoice_id')
                    ->constrained($items)->restrictOnDelete();
            });
            Schema::table($invoices, function (Blueprint $table) {
                $table->string('credit_reason')->nullable()->after('credited_invoice_id');
            });

            $creditNotes = DB::table($invoices)->where('document_type', 'credit_note')->whereNotNull('credited_invoice_id')->get(['id', 'credited_invoice_id']);
            foreach ($creditNotes as $note) {
                $source = DB::table($items)->where('invoice_id', $note->credited_invoice_id)->orderBy('id')->pluck('id')->values();
                $copies = DB::table($items)->where('invoice_id', $note->id)->orderBy('id')->pluck('id')->values();
                foreach ($copies as $i => $copyId) {
                    if (isset($source[$i])) {
                        DB::table($items)->where('id', $copyId)->update(['credited_item_id' => $source[$i]]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $invoices => $items) {
            Schema::table($items, fn (Blueprint $table) => $table->dropConstrainedForeignId('credited_item_id'));
            Schema::table($invoices, fn (Blueprint $table) => $table->dropColumn('credit_reason'));
        }
    }
};
