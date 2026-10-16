<?php

use App\Support\ActivityLogPresenter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Readable activity log: each entry gets its kind of action (creation, modification, finance...) and whether
 * it succeeded or was refused, so the page can filter and count without technical codes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('action');
            $table->string('outcome', 10)->nullable()->after('category');
            $table->index(['category', 'created_at']);
        });

        DB::table('activity_logs')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $category = ActivityLogPresenter::classify($row->method, $row->route_name, $row->action, $row->url);
                $refused = $category === 'login_failed' || (int) $row->status_code >= 400;
                DB::table('activity_logs')->where('id', $row->id)->update([
                    'category' => $category,
                    'outcome' => $refused ? 'refused' : 'ok',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['category', 'created_at']);
            $table->dropColumn(['category', 'outcome']);
        });
    }
};
