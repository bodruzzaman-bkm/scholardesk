<?php

namespace App\Models;

use App\Enums\PaperSource;
use App\Enums\ReadingStatus;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'url',
        'source',
        'file_path',
        'reading_status',
        'user_id',
        'full_text',
        'indexed_at',
        'index_status',
    ];

    protected function casts(): array
    {
        return [
            'reading_status' => ReadingStatus::class,
            'source' => PaperSource::class,
            'year' => 'integer',
            'indexed_at' => 'datetime',
        ];
    }

    // Relationship: A paper belongs to a specific user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: A paper can belong to multiple collections (Many-to-Many)
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    // Relationship: A paper can have many tags (Many-to-Many)
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    // Relationship: A paper can have many highlights
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    /**
     * Notes attached to this paper, newest first.
     *
     * Ordering lives here rather than in every call site because the paper
     * detail page and the export bundle both want the same order. The id is a
     * tie-break: several notes written within the same second share a
     * created_at, and ordering on that alone returns them arbitrarily.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(PaperChunk::class)->orderBy('chunk_index');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** True when a readable PDF is attached. */
    public function hasPdf(): bool
    {
        return filled($this->file_path);
    }

    /** True once the text has been extracted and chunked for retrieval. */
    public function isIndexed(): bool
    {
        return $this->indexed_at !== null && $this->index_status === 'indexed';
    }

    /**
     * A scanned/image-only PDF yields no extractable text, which the UI must
     * say plainly rather than silently returning nothing from search and AI.
     */
    public function hasNoExtractableText(): bool
    {
        return $this->index_status === 'no_text';
    }

    // ---------------------------------------------------------------------
    // Query scopes — used by the library index and the search page.
    // ---------------------------------------------------------------------

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Papers the user owns, plus papers inside collections shared with them.
     *
     * This is the access boundary for retrieval: anything reachable here may
     * legitimately be fed to search and the AI layer for this user.
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas(
                    'collections.members',
                    fn (Builder $m) => $m->where('collection_members.user_id', $userId)
                );
        });
    }

    /** Keyword search across the fields a researcher actually recalls. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // Support\Search chooses LIKE or ILIKE for the driver, so this matches
        // case-insensitively on Postgres as well as SQLite, and escapes the
        // wildcards so a search for "50%" does not match every row. Column
        // names are hard-coded there; only the value is bound.
        return Search::anyColumn($query, ['title', 'authors', 'abstract', 'venue', 'doi'], $term);
    }

    public function scopeWithTag(Builder $query, mixed $tagId): Builder
    {
        if (blank($tagId)) {
            return $query;
        }

        return $query->whereHas('tags', fn (Builder $q) => $q->where('tags.id', $tagId));
    }

    public function scopeWithStatus(Builder $query, mixed $status): Builder
    {
        if (blank($status) || ! in_array($status, ReadingStatus::values(), true)) {
            return $query;
        }

        return $query->where('reading_status', $status);
    }

    public function scopeWithYear(Builder $query, mixed $year): Builder
    {
        if (blank($year)) {
            return $query;
        }

        return $query->where('year', $year);
    }

    /**
     * Filter by author (requirement 12 lists author among the filters).
     *
     * `authors` is a comma-separated string rather than a relation, so this is
     * a substring match: selecting "Vaswani" finds papers where they are any
     * of the listed authors, not only the first.
     */
    public function scopeWithAuthor(Builder $query, mixed $author): Builder
    {
        if (blank($author)) {
            return $query;
        }

        return $query->whereRaw(Search::clause('authors'), [Search::pattern((string) $author)]);
    }

    public function scopeWithVenue(Builder $query, mixed $venue): Builder
    {
        if (blank($venue)) {
            return $query;
        }

        return $query->where('venue', $venue);
    }

    public function scopeInCollection(Builder $query, mixed $collectionId): Builder
    {
        if (blank($collectionId)) {
            return $query;
        }

        return $query->whereHas(
            'collections',
            fn (Builder $q) => $q->where('collections.id', $collectionId)
        );
    }

    /** Whitelisted sorting, so a query string cannot order by an arbitrary column. */
    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'title' => $query->orderBy('title'),
            'year' => $query->orderByRaw('year IS NULL, year DESC'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };
    }
}
