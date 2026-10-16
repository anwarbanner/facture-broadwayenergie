<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->unique();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->date('date');
            $table->date('validity_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected'])->default('draft');
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_tva', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->string('currency', 3)->default('MAD');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('purchase_quotes')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'partial', 'delivered', 'cancelled'])->default('draft');
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_tva', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->string('currency', 3)->default('MAD');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('delivery_date');
            $table->enum('status', ['partial', 'complete'])->default('partial');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('delivery_note_id')->nullable()->constrained('delivery_notes')->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount_ht', 15, 2)->default(0);
            $table->decimal('tva_amount', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_remaining', 15, 2)->default(0);
            $table->enum('status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
            $table->string('payment_terms')->nullable();
            $table->string('currency', 3)->default('MAD');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status', 'due_date']);
        });

        Schema::create('supplier_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_invoices')->default(0);
            $table->decimal('total_payments', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'period_start', 'period_end']);
        });

        Schema::create('purchase_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('purchase_quotes')->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('price_ht', 15, 2);
            $table->decimal('tva_rate', 5, 2)->default(20);
            $table->decimal('total_ht', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->index(['quote_id', 'product_id']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('price_ht', 15, 2);
            $table->decimal('tva_rate', 5, 2)->default(20);
            $table->decimal('total_ht', 15, 2);
            $table->decimal('delivered_qty', 15, 3)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->index(['order_id', 'product_id']);
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('received_qty', 15, 3)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->index(['delivery_note_id', 'product_id']);
        });

        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('price_ht', 15, 2);
            $table->decimal('tva_rate', 5, 2)->default(20);
            $table->decimal('total_ht', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->index(['invoice_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_quote_items');
        Schema::dropIfExists('supplier_statements');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_quotes');
    }
};
