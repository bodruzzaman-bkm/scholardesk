<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiteratureReview extends Model
{
    protected $fillable = ['collection_id', 'user_id', 'title', 'content', 'paper_ids'];

    protected function casts(): array
    {
        return ['paper_ids' => 'array'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
