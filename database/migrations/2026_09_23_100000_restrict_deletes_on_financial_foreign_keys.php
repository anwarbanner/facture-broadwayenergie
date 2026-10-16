<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial documents must never disappear because their client, supplier,
 * parent document or product was hard-deleted. Switch those foreign keys from
 * ON DELETE CASCADE to ON DELETE RESTRICT.
 *
 * Line items -> their own document (e.g. client_invoice_items.invoice_id) keep
 * CASCADE on purpose: a line has no meaning without its document.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string}> table, column, referenced table */
    private array $foreignKeys = [
        ['sales_quotes', 'client_id', 'contacts'],
        ['sales_orders', 'client_id', 'contacts'],
        ['deliveries', 'client_id', 'contacts'],
        ['deliveries', 'sales_order_id', 'sales_orders'],
        ['client_invoices', 'client_id', 'contacts'],
        ['client_statements', 'client_id', 'contacts'],
        ['purchase_quotes', 'supplier_id', 'contacts'],
        ['purchase_orders', 'supplier_id', 'contacts'],
        ['delivery_notes', 'supplier_id', 'contacts'],
        ['delivery_notes', 'purchase_order_id', 'purchase_orders'],
        ['supplier_invoices', 'supplier_id', 'contacts'],
        ['supplier_statements', 'supplier_id', 'contacts'],
        ['stock_movements', 'product_id', 'products'],
    ];

    public function up(): void
    {
        $this->rebuild('restrict');
    }

    public function down(): void
    {
        $this->rebuild('cascade');
    }

    private function rebuild(string $onDelete): void
    {
        // SQLite cannot alter foreign keys in place.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->foreignKeys as [$table, $column, $references]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $onDelete) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($references)->onDelete($onDelete);
            });
        }
    }
};
