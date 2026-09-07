<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * In-app notifications, with an optional email copy.
 *
 * The in-app row is the source of truth; email is best-effort and opt-out per
 * type (User::wantsEmailFor). A mail failure is swallowed and logged, because a
 * down SMTP server must never break the request that triggered the notification
 * (ScholarDesk's rule, kept). MailService::sendTest() is the deliberate
 * exception, where the error is the point.
 */
class NotificationService
{
    public function notify(User $user, NotificationType $type, string $message, ?string $link = null): void
    {
        InAppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'message' => $message,
            'link' => $link,
        ]);

        $this->email($user, $type, $message, $link);
    }

    /**
     * Notify several users at once, skipping the actor.
     *
     * @param  iterable<User>  $users
     */
    public function notifyMany(iterable $users, NotificationType $type, string $message, ?string $link = null, ?User $except = null): void
    {
        foreach ($users as $user) {
            if ($except !== null && $user->id === $except->id) {
                continue;
            }

            $this->notify($user, $type, $message, $link);
        }
    }

    public function markRead(InAppNotification $notification): void
    {
        $notification->update(['is_read' => true]);
    }

    public function markAllRead(User $user): void
    {
        $user->inAppNotifications()->unread()->update(['is_read' => true]);
    }

    private function email(User $user, NotificationType $type, string $message, ?string $link): void
    {
        // The in-app row is already written; the user can decline the copy.
        if (! $user->wantsEmailFor($type)) {
            return;
        }

        // Nothing to send to in local/array/log-less setups without a mailer.
        if (config('mail.default') === null) {
            return;
        }

        try {
            $url = $link ? rtrim((string) config('app.url'), '/').$link : null;

            Mail::raw(
                $message.($url ? "\n\n".$url : ''),
                function ($mail) use ($user, $message) {
                    $mail->to($user->email)
                        ->subject('ScholarDesk: '.mb_strimwidth($message, 0, 60, '…'));
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Email notification failed (non-fatal)', ['error' => $e->getMessage()]);
        }
    }
}
