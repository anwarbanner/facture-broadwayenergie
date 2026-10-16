<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real payments: one payment (money in or out) can be allocated to several invoices / credit notes.
 * invoices.amount_paid and balance_remaining become caches recomputed from the allocations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            // in: money received by the company (client payment, supplier refund)
            // out: money paid by the company (supplier payment, client refund)
            $table->enum('direction', ['in', 'out']);
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'check', 'transfer', 'card', 'bill', 'other'])->default('transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            // Payments are never deleted: a wrong payment is voided and stays in the history.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contact_id', 'payment_date']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('client_invoice_id')->nullable()->constrained('client_invoices')->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        $this->backfill('client_invoices', 'client_id', 'client_invoice_id', 'in');
        $this->backfill('supplier_invoices', 'supplier_id', 'supplier_invoice_id', 'out');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }

    /** Amounts already typed in amount_paid become one historical payment per invoice. */
    private function backfill(string $table, string $contactColumn, string $allocationColumn, string $direction): void
    {
        $invoices = DB::table($table)
            ->where('document_type', 'invoice')
            ->where('amount_paid', '>', 0)
            ->whereNull('deleted_at')
            ->get();

        foreach ($invoices as $invoice) {
            $paymentId = DB::table('payments')->insertGetId([
                'contact_id' => $invoice->{$contactColumn},
                'direction' => $direction,
                'payment_date' => $invoice->invoice_date ?? now()->toDateString(),
                'amount' => $invoice->amount_paid,
                'method' => 'other',
                'reference' => 'Reprise historique',
                'notes' => 'Montant paye saisi avant la gestion des paiements.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_allocations')->insert([
                'payment_id' => $paymentId,
                $allocationColumn => $invoice->id,
                'amount' => $invoice->amount_paid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
