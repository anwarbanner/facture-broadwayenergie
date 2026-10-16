<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_name');
            $table->decimal('hours_count', 10, 2)->default(1);
            $table->decimal('service_price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('legacy_product_id')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_name', 'status']);
        });

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'type')) {
            DB::table('products')
                ->where('type', 'service')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('services')->insert([
                            'service_name' => $row->product_name,
                            'hours_count' => 1,
                            'service_price' => $row->sale_price_ht ?? 0,
                            'description' => $row->description,
                            'status' => $row->status ?? 'active',
                            'legacy_product_id' => $row->id,
                            'created_by' => $row->created_by,
                            'updated_by' => $row->updated_by,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                            'deleted_at' => $row->deleted_at,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
