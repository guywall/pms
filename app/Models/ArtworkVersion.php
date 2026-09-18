<?php

namespace App\Models;

use App\Enums\ArtworkStatus;
use App\Models\Concerns\BelongsToCustomerTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ArtworkVersion extends Model implements HasMedia

{
    use BelongsToCustomerTeam, InteractsWithMedia;

    protected $table = 'artwork_versions';

    protected $fillable = [
        'order_id', 'version_number', 'status', 'is_final', 'notes', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArtworkStatus::class,
            'is_final' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
{
        return $this->belongsTo(User::class, 'approved_by');
    }
}
