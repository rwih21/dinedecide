<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PromotedPlace extends Model
{
    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'food_types',
        'price_display',
        'min_price',
        'photo_path',
        'whatsapp',
        'gmaps_url',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'food_types' => 'array',
        'is_active'  => 'boolean',
        'starts_at'  => 'date',
        'ends_at'    => 'date',
    ];

    // Only currently active and scheduled promotions
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('starts_at')
                           ->orWhere('starts_at', '<=', now());
                     })
                     ->where(function ($q) {
                         $q->whereNull('ends_at')
                           ->orWhere('ends_at', '>=', now());
                     });
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo_path && Storage::disk('public')->exists($this->photo_path)) {
            return Storage::url($this->photo_path);
        }
        return 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=80';
    }
}