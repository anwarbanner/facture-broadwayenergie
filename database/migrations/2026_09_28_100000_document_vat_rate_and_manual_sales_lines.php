<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - One VAT rate per document (applied to the document's total HT) instead of one rate per line.
 *   Existing documents keep their stored amounts; the new column is only back-filled from their lines.
 * - Free-text ("manual") lines on the sales side, like the purchase side already has.
 */
return new class extends Migration {
    /** document table => its lines table and foreign key */
    private const DOCUMENTS = [
        'sales_quotes' => ['sales_quote_items', 'quote_id'],
        'sales_orders' => ['sales_order_items', 'order_id'],
        'client_invoices' => ['client_invoice_items', 'invoice_id'],
        'purchase_quotes' => ['purchase_quote_items', 'quote_id'],
        'purchase_orders' => ['purchase_order_items', 'order_id'],
        'supplier_invoices' => ['supplier_invoice_items', 'invoice_id'],
    ];

    private const SALES_LINES = ['sales_quote_items', 'sales_order_items', 'delivery_items', 'client_invoice_items'];

    public function up(): void
    {
        $default = (float) config('branding.default_vat_rate', 20);

        foreach (self::DOCUMENTS as $table => [$linesTable, $foreignKey]) {
            if (!Schema::hasColumn($table, 'tva_rate')) {
                Schema::table($table, function (Blueprint $blueprint) use ($default) {
                    $blueprint->decimal('tva_rate', 5, 2)->default($default)->after('total_ttc');
                });
            }

            // Most frequent line rate of each document (lines were entered with one rate each before).
            $rates = DB::table($linesTable)
                ->select($foreignKey, 'tva_rate', DB::raw('COUNT(*) AS n'))
                ->groupBy($foreignKey, 'tva_rate')
                ->orderByDesc('n')
                ->get()
                ->unique($foreignKey);

            foreach ($rates as $row) {
                DB::table($table)->where('id', $row->{$foreignKey})->update(['tva_rate' => $row->tva_rate]);
            }
        }

        foreach (self::SALES_LINES as $table) {
            if (!Schema::hasColumn($table, 'item_name')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('item_name')->nullable()->after('product_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::SALES_LINES as $table) {
            if (Schema::hasColumn($table, 'item_name')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('item_name'));
            }
        }

        foreach (array_keys(self::DOCUMENTS) as $table) {
            if (Schema::hasColumn($table, 'tva_rate')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('tva_rate'));
            }
        }
    }
};
