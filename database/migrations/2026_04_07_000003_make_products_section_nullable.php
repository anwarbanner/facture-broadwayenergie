<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'section_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropForeign(['section_id']);
            } catch (\Throwable $e) {
                // Ignore if the foreign key does not exist.
            }
        });

        DB::statement('ALTER TABLE products MODIFY section_id INT UNSIGNED NULL');

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->foreign('section_id')->references('id')->on('sections')->nullOnDelete();
            } catch (\Throwable $e) {
                // Ignore if the foreign key already exists.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'section_id')) {
            return;
        }

        DB::table('products')->whereNull('section_id')->update(['section_id' => 1]);

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropForeign(['section_id']);
            } catch (\Throwable $e) {
                // Ignore if the foreign key does not exist.
            }
        });

        DB::statement('ALTER TABLE products MODIFY section_id INT UNSIGNED NOT NULL');

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
            } catch (\Throwable $e) {
                // Ignore if the foreign key already exists.
            }
        });
    }
};
