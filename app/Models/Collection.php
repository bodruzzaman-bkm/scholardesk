<?php

namespace App\Models;

use App\Enums\MemberRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    // Allowing these columns to be saved in the database
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    // Relationship: A collection belongs to a specific user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Papers grouped into this collection.
     *
     * Detaching a paper only removes the pivot row — the paper itself stays in
     * the library, which is the behaviour ScholarDesk specifies.
     */
    public function papers(): BelongsToMany
    {
        return $this->belongsToMany(Paper::class)->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(CollectionMember::class);
    }

    /** Users with any membership, including the owner. */
    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'collection_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LiteratureReview::class)->latest();
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Collections this user owns *or* has been given access to.
     *
     * Used by the collections index so shared work appears alongside your own.
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas('members', fn (Builder $m) => $m->where('user_id', $userId));
        });
    }

    /** The effective role for a user, or null when they have no access. */
    public function roleFor(?User $user): ?MemberRole
    {
        if ($user === null) {
            return null;
        }

        // The creator is always an owner, even if the membership row is missing.
        if ($this->user_id === $user->id) {
            return MemberRole::Owner;
        }

        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $user->id)
            : $this->members()->where('user_id', $user->id)->first();

        return $member?->role;
    }

    public function userCanAtLeast(?User $user, MemberRole $minimum): bool
    {
        return $this->roleFor($user)?->atLeast($minimum) ?? false;
    }
}
