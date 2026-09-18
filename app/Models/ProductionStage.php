<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionStage extends Model
{
    protected $fillable = [
        'key', 'name', 'position', 'gate_requires_stock', 'gate_requires_final_artwork',
        'gate_requires_digitised_file', 'is_default_start', 'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'gate_requires_stock' => 'boolean',
            'gate_requires_final_artwork' => 'boolean',
            'gate_requires_digitised_file' => 'boolean',
            'is_default_start' => 'boolean',
            'is_terminal' => 'boolean',
        ];
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStageHistory::class);
    }
}
