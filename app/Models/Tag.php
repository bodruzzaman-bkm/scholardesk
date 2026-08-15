<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    // Allowing these columns to be saved in the database
    protected $fillable = [
        'name',
        'color',
        'user_id',
    ];

    // Relationship: A tag belongs to a specific user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: A tag can be applied to multiple papers (Many-to-Many)
    public function papers()
    {
        return $this->belongsToMany(Paper::class);
    }
}
