<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Paper;
use App\Models\user;

class Collection extends Model
{
    // Allowing these columns to be saved in the database
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    // Relationship: A collection belongs to a specific user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: A collection can contain multiple papers (Many-to-Many)
    public function papers()
    {
        return $this->belongsToMany(Paper::class);
    }
}
