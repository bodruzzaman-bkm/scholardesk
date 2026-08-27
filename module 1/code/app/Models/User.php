<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Locale;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'locale' => Locale::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    // Relationship: A user can have many uploaded papers (One-to-Many)
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }

    // Relationship: A user can create many collections (One-to-Many)
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    // Relationship: A user can create many tags (One-to-Many)
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    // Relationship: A user can write many notes across papers
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    // Relationship: A user can create many highlights across papers
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    /** Memberships of collections owned by other people (and their own). */
    public function collectionMemberships(): HasMany
    {
        return $this->hasMany(CollectionMember::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class)->latest();
    }

    public function unreadNotificationCount(): int
    {
        return $this->inAppNotifications()->unread()->count();
    }
}

