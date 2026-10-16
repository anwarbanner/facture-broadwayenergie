<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice lifecycle: draft (editable, provisional number) -> validated (numbered, locked)
 * -> cancelled (only through a credit note). Credit notes live in the same tables.
 */
return new class extends Migration
{
    private array $tables = ['client_invoices', 'supplier_invoices'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->enum('document_type', ['invoice', 'credit_note'])->default('invoice')->after('invoice_number');
                $blueprint->enum('state', ['draft', 'validated', 'cancelled'])->default('draft')->after('status');
                $blueprint->foreignId('credited_invoice_id')->nullable()->after('document_type')
                    ->constrained($table)->restrictOnDelete();
                $blueprint->timestamp('validated_at')->nullable()->after('state');
                $blueprint->foreignId('validated_by')->nullable()->after('validated_at')
                    ->constrained('users')->nullOnDelete();
                $blueprint->index(['document_type', 'state']);
            });

            // Invoices that existed before this migration were already issued.
            DB::table($table)->update(['state' => 'validated', 'validated_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['document_type', 'state']);
                $blueprint->dropConstrainedForeignId('validated_by');
                $blueprint->dropConstrainedForeignId('credited_invoice_id');
                $blueprint->dropColumn(['document_type', 'state', 'validated_at']);
            });
        }
    }
};
