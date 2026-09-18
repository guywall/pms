<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrderDocument extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['order_id', 'uploaded_by', 'category', 'name', 'notes'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
