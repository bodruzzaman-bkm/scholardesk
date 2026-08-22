<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app notification.
 *
 * Deliberately NOT called "Notification": Laravel already ships
 * Illuminate\Notifications\Notification and its own `notifications` table, and
 * colliding on either name causes confusing resolution bugs. This model owns
 * the `notifications_inapp` table.
 */
class InAppNotification extends Model
{
    protected $table = 'notifications_inapp';

    protected $fillable = ['user_id', 'type', 'message', 'link', 'is_read'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'is_read' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
