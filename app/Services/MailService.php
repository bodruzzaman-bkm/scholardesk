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

        // Every remaining transport is "configured" exactly when the credential
        // it actually authenticates with is present. Answering true for a
        // transport whose key is missing would paint the panel green over a
        // mailer that cannot send — the precise dishonesty this class exists to
        // remove — so each one is asked about its own secret rather than taken
        // at its word.
        return match ($default) {
            // smtp, and the failover chain that begins with smtp.
            'smtp', 'failover' => filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password')),

            'resend' => filled(config('services.resend.key')),
            'postmark' => filled(config('services.postmark.key')),
            'ses' => filled(config('services.ses.key')) && filled(config('services.ses.secret')),

            // sendmail shells out to a local binary and has nothing to check.
            'sendmail' => true,

            default => true,
        };
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
            'resend' => 'the Resend API',
            'postmark' => 'the Postmark API',
            'ses' => 'Amazon SES',
            'sendmail' => 'the local sendmail binary',
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
