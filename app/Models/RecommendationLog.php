<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationLog extends Model
{
    protected $fillable = [
        'search_id',
        'restaurant_name',
        'google_place_id',
        'saw_score',
        'rank',
        'criteria_breakdown',
    ];

    protected $casts = [
        'criteria_breakdown' => 'array',
    ];

    public function search()
    {
        return $this->belongsTo(SearchHistory::class, 'search_id');
    }
}