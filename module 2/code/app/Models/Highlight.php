<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Highlight extends Model
{
    protected $fillable = ['paper_id', 'user_id', 'text', 'note', 'color', 'position'];
    
    // Auto-convert JSON to Array when retrieving
    protected $casts = [
        'position' => 'array',
    ];

    public function paper()
    {
        return $this->belongsTo(Paper::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}