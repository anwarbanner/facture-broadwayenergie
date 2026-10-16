<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simple mode: a BL made from a quote follows the quote (its lines are rewritten when the quote changes)
 * until it is edited by hand ("Modifier le BL"): follows_quote = false, the quote no longer overwrites it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->boolean('follows_quote')->default(true)->after('moves_stock');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('follows_quote');
        });
    }
};
