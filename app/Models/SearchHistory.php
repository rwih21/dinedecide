<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    protected $fillable = [
        'user_id',
        'raw_query',
        'extracted_food_type',
        'latitude',
        'longitude',
    ];

    public function recommendations()
    {
        return $this->hasMany(RecommendationLog::class, 'search_id');
    }
}