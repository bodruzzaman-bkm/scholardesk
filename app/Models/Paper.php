<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Collection;

class Paper extends Model
{
    // Allowing these columns to be saved in the database
    protected $fillable = [
        'title',
        'authors', 
        'year',    
        'venue',   
        'abstract',
        'doi',
        'file_path',
        'reading_status',
        'user_id',
    ];

    // Relationship: A paper belongs to a specific user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Relationship: A paper can belong to multiple collections (Many-to-Many)
    public function collections()
    {
        return $this->belongsToMany(Collection::class);
    }

    // Relationship: A paper can have many tags (Many-to-Many)
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
