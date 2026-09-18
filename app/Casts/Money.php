<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Money implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?float
    {
        return $value === null ? null : round($value / 100, 2);
    }

    public function set($model, string $key, $value, array $attributes): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) str_replace([',', '£'], '', (string) $value)) * 100);
    }
}
