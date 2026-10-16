<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->unique();
            $table->foreignId('client_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->date('date');
            $table->date('validity_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'converted'])->default('draft');
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_tva', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->string('currency', 3)->default('MAD');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('client_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('sales_quotes')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
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

            $table->index(['client_id', 'status']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('delivery_date');
            $table->text('shipping_address')->nullable();
            $table->enum('status', ['pending', 'shipped', 'delivered', 'returned'])->default('pending');
            $table->string('tracking_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('client_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
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

            $table->index(['client_id', 'status', 'due_date']);
        });

        Schema::create('client_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_invoices')->default(0);
            $table->decimal('total_payments', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'period_start', 'period_end']);
        });

        Schema::create('sales_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('sales_quotes')->cascadeOnDelete();
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

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
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

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->unsignedInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('shipped_qty', 15, 3)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->index(['delivery_id', 'product_id']);
        });

        Schema::create('client_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('client_invoices')->cascadeOnDelete();
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
        Schema::dropIfExists('client_invoice_items');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_quote_items');
        Schema::dropIfExists('client_statements');
        Schema::dropIfExists('client_invoices');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_quotes');
    }
};
