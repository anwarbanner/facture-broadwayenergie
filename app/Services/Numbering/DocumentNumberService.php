<?php

namespace App\Services\Numbering;

use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers: PREFIX-YYYY-0001.
 *
 * The counter lives in document_sequences and is read with SELECT ... FOR UPDATE, so two concurrent
 * requests can never receive the same number. When called inside a caller's transaction the lock is
 * held until that transaction commits: a rolled-back document gives its number back (no gap).
 */
class DocumentNumberService
{
    public function next(string $table, string $column, string $prefix): string
    {
        $year = now()->format('Y');
        $basePrefix = $prefix . '-' . $year . '-';
        $key = $table . ':' . $prefix . ':' . $year;

        return DB::transaction(function () use ($table, $column, $basePrefix, $key) {
            if (!DB::table('document_sequences')->where('sequence_key', $key)->exists()) {
                // First number of the year for this series: start after numbers created before the counter existed.
                DB::table('document_sequences')->insertOrIgnore([
                    'sequence_key' => $key,
                    'last_number' => $this->highestExisting($table, $column, $basePrefix),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $sequence = DB::table('document_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
            $number = (int) $sequence->last_number + 1;

            DB::table('document_sequences')->where('id', $sequence->id)->update([
                'last_number' => $number,
                'updated_at' => now(),
            ]);

            return sprintf('%s%04d', $basePrefix, $number);
        });
    }

    private function highestExisting(string $table, string $column, string $basePrefix): int
    {
        $highest = 0;
        foreach (DB::table($table)->where($column, 'like', $basePrefix . '%')->pluck($column) as $value) {
            $highest = max($highest, (int) substr((string) $value, strlen($basePrefix)));
        }

        return $highest;
    }
}
