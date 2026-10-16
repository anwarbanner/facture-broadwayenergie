<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Manual clean-up of the activity log. Not scheduled: how long to keep the log is the owner's decision.
 * --reads-only removes only the old page views recorded before 2026-09-28 (they are no longer logged).
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'activity-logs:prune {--days=365 : keep the entries of the last N days} {--reads-only : only remove page views (GET)} {--force : do not ask for confirmation}';

    protected $description = 'Delete activity log entries older than N days';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $query = ActivityLog::where('created_at', '<', now()->subDays($days));
        if ($this->option('reads-only')) {
            $query->where('method', 'GET');
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('Aucune entree a supprimer.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Supprimer {$count} entree(s) du journal d'activite anterieures a {$days} jour(s) ?")) {
            return self::FAILURE;
        }

        $query->delete();
        $this->info("{$count} entree(s) supprimee(s).");

        return self::SUCCESS;
    }
}
