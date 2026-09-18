<?php

namespace App\Models;

use App\Enums\StageKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStageHistory extends Model
{
    protected $table = 'order_stage_history';

    protected $fillable = ['order_id', 'from_stage', 'to_stage', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['from_stage' => StageKey::class, 'to_stage' => StageKey::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

