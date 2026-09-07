<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * What the mailer is actually doing, and a way to prove it.
 *
 * Mirrors AiService::isConfigured()/provider()/model() so the settings page can
 * present mail and AI the same way.
 *
 * The distinction that matters: mail can be "working" in the sense that Laravel
 * accepts the message while nothing ever reaches an inbox, because config/mail.php
 * defines `failover` as smtp-then-log and blank credentials quietly take the log
 * branch. isConfigured() answers the question the user actually cares about.
 */
class MailService
{
    public function isConfigured(): bool
    {
        $default = config('mail.default');

        // The log and array transports never deliver anything to anyone.
        if (in_array($default, ['log', 'array', null], true)) {
            return false;
        }

        // smtp, and the failover chain that begins with smtp, both stand or fall
        // on the SMTP credentials. Missing ones are this project's usual failure
        // and are indistinguishable from success unless checked here.
        if (in_array($default, ['smtp', 'failover'], true)) {
            return filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password'));
        }

        // ses / postmark / resend / sendmail carry their credentials elsewhere.
        return true;
    }

    /** A short human label for where mail goes, shown on the settings page. */
    public function transport(): string
    {
        $default = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');

        return match ($default) {
            '', 'log' => 'the application log',
            'array' => 'an in-memory array (tests)',
            'smtp' => $host,
            'failover' => $host.', falling back to the application log',
            default => $default,
        };
    }

    /**
     * Send a one-line proof-of-life to the user's own address.
     *
     * Unlike NotificationService::email(), this lets exceptions escape on
     * purpose. That method swallows failures because a dead SMTP server must
     * never break the request that triggered a notification; this one exists
     * solely to tell the user whether mail works, and a silent success would
     * be a lie.
     */
    public function sendTest(User $user): void
    {
        Mail::raw(
            "This is a test message from ScholarDesk.\n\n"
            .'If you are reading it in your inbox, email notifications are working.',
            fn ($mail) => $mail->to($user->email)->subject('ScholarDesk: test email')
        );
    }
}
