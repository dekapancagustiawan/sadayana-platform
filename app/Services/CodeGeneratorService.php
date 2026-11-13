<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CodeGeneratorService
{
    /**
     * Generate an auto-incrementing, month-resetting unique code.
     * Format: PREFIX + DDMMYY + Running Number (padded to 3 digits)
     * e.g., BRN190726001
     *
     * @param  string  $prefix  E.g. 'BRN'
     * @param  string  $table  E.g. 'branches'
     * @param  string  $column  E.g. 'branch_code'
     */
    public function generate(string $prefix, string $table, string $column): string
    {
        $now = Carbon::now();
        $dateStr = $now->format('dmy'); // E.g., '190726'
        $monthYear = $now->format('my'); // E.g., '0726'

        // We wrap this inside a database transaction to lock the sequence row
        return DB::transaction(function () use ($prefix, $table, $monthYear, $dateStr) {
            // Find and lock the sequence row for this entity type and month
            $sequence = DB::table('code_sequences')
                ->where('entity_type', $table)
                ->where('month_year', $monthYear)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextNumber = $sequence->last_number + 1;
                DB::table('code_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_number' => $nextNumber,
                        'updated_at' => now(),
                    ]);
            } else {
                $nextNumber = 1;
                try {
                    DB::table('code_sequences')->insert([
                        'id' => (string) Str::uuid(),
                        'entity_type' => $table,
                        'month_year' => $monthYear,
                        'last_number' => $nextNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    // In case of concurrent insert race conditions, query again with lock
                    $sequence = DB::table('code_sequences')
                        ->where('entity_type', $table)
                        ->where('month_year', $monthYear)
                        ->lockForUpdate()
                        ->first();

                    if ($sequence) {
                        $nextNumber = $sequence->last_number + 1;
                        DB::table('code_sequences')
                            ->where('id', $sequence->id)
                            ->update([
                                'last_number' => $nextNumber,
                                'updated_at' => now(),
                            ]);
                    } else {
                        throw $e;
                    }
                }
            }

            $runningNumberStr = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

            return $prefix.$dateStr.$runningNumberStr;
        });
    }
}
