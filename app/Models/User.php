<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Locale;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'locale', 'email_prefs'])]
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
            'suspended_at' => 'datetime',
            'email_prefs' => 'array',
        ];
    }

    /**
     * Should this user get an email copy of a given notification type?
     *
     * Null prefs means "everything on" — the behaviour before the setting
     * existed, so accounts predating the column are unaffected until they
     * touch the switches themselves.
     */
    public function wantsEmailFor(NotificationType $type): bool
    {
        return $this->email_prefs === null
            || (bool) ($this->email_prefs[$type->value] ?? true);
    }

    /**
     * Every type mapped to its on/off state, so the settings form never has to
     * reason about the null default.
     *
     * @return array<string, bool>
     */
    public function emailPreferenceMap(): array
    {
        return collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => [$type->value => $this->wantsEmailFor($type)])
            ->all();
    }

    /**
     * A suspended account keeps everything it owns but cannot sign in.
     *
     * Checked at the login gate and again on every authenticated request, so
     * suspending someone who is already signed in takes effect immediately
     * rather than at their next login.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
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

