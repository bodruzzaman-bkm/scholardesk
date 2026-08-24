<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperChunk extends Model
{
    protected $fillable = ['paper_id', 'chunk_index', 'content', 'embedding'];

    protected function casts(): array
    {
        return [
            // Stored as a JSON array of floats. SQLite has no vector type, so
            // cosine similarity is computed in PHP by EmbeddingService.
            'embedding' => 'array',
            'chunk_index' => 'integer',
        ];
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }
}
