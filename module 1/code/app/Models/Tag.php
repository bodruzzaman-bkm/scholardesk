<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    // Allowing these columns to be saved in the database
    protected $fillable = [
        'name',
        'color',
        'user_id',
    ];

    // Relationship: A tag belongs to a specific user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: A tag can be applied to multiple papers (Many-to-Many)
    public function papers(): BelongsToMany
    {
        return $this->belongsToMany(Paper::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Readable text colour for this tag's background.
     *
     * Keeps the luminance maths out of the Blade templates, which previously
     * hard-coded white text and made light tags unreadable.
     */
    public function contrastingTextColor(): string
    {
        $hex = ltrim((string) $this->color, '#');

        if (strlen($hex) !== 6) {
            return '#FFFFFF';
        }

        [$r, $g, $b] = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        // Perceived brightness (ITU-R BT.601).
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? '#1F2937' : '#FFFFFF';
    }
}
