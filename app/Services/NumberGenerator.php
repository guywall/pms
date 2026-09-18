<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGenerator
{
    public function nextOrderNumber(): string
    {
        return $this->generate('JOB');
    }

    public function nextPurchaseOrderNumber(): string
    {
        return $this->generate('PO');
    }

    private function generate(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $year = now()->format('Y');
            $lock = DB::table('number_sequences')->where('key', "{$prefix}_{$year}")->lockForUpdate()->first();

            if (! $lock) {
                DB::table('number_sequences')->insert(['key' => "{$prefix}_{$year}", 'value' => 0]);
                $next = 1;
            } else {
                $next = $lock->value + 1;
            }

            DB::table('number_sequences')->where('key', "{$prefix}_{$year}")->update(['value' => $next]);

            return sprintf('%s-%s-%05d', $prefix, $year, $next);
        });
    }
}
