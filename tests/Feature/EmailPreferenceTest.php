<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The settings card that lets a user opt out of the email copy of their
 * notifications, and the test-send button that proves mail works.
 *
 * Mail is asserted with Mail::shouldReceive('raw'), matching NotificationTest —
 * Mail::fake() does not record raw sends, so assertSent* would pass vacuously.
 */
class EmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_page_shows_the_email_card(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('app.email_notifications'))
            ->assertSee(__('app.send_test_email'))
            ->assertSee(__('app.notif_comment'));
    }

    public function test_every_type_is_emailed_before_the_user_touches_the_setting(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->email_prefs);

        foreach (NotificationType::cases() as $type) {
            $this->assertTrue($user->wantsEmailFor($type));
        }
    }

    /**
     * An unticked box must be stored as an explicit false. If it were merely
     * absent, wantsEmailFor() would fall back to its "on" default and the user
     * would keep getting the mail they just turned off.
     */
    public function test_saving_preferences_stores_a_complete_map(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.email'), ['types' => ['comment', 'share']])
            ->assertRedirect();

        $prefs = $user->fresh()->email_prefs;

        $this->assertSame([
            'comment' => true,
            'share' => true,
            'mention' => false,
            'ai_done' => false,
            'system' => false,
        ], $prefs);
    }

    public function test_clearing_every_box_disables_all_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('settings.email'))->assertRedirect();

        foreach (NotificationType::cases() as $type) {
            $this->assertFalse($user->fresh()->wantsEmailFor($type));
        }
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.email'), ['types' => ['not_a_type']])
            ->assertSessionHasErrors('types.0');

        $this->assertNull($user->fresh()->email_prefs);
    }

    /**
     * The core regression: opting out silences the email, never the in-app row.
     */
    public function test_a_disabled_type_still_records_the_notification_but_sends_no_mail(): void
    {
        Mail::shouldReceive('raw')->never();

        $user = User::factory()->create(['email_prefs' => ['comment' => false]]);

        app(NotificationService::class)->notify($user, NotificationType::Comment, 'Someone commented');

        $this->assertDatabaseHas('notifications_inapp', [
            'user_id' => $user->id,
            'message' => 'Someone commented',
        ]);
        $this->assertSame(1, $user->unreadNotificationCount());
    }

    public function test_an_enabled_type_is_still_emailed(): void
    {
        Mail::shouldReceive('raw')->once();

        $user = User::factory()->create(['email_prefs' => ['comment' => false, 'share' => true]]);

        app(NotificationService::class)->notify($user, NotificationType::Share, 'Shared with you');
    }

    /** A type missing from a saved map keeps the "on" default. */
    public function test_a_type_absent_from_the_map_is_still_emailed(): void
    {
        Mail::shouldReceive('raw')->once();

        $user = User::factory()->create(['email_prefs' => ['comment' => false]]);

        app(NotificationService::class)->notify($user, NotificationType::System, 'Announcement');
    }

    public function test_the_test_button_reports_success(): void
    {
        Mail::shouldReceive('raw')->once();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.email.test'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    /**
     * Notification mail hides its failures on purpose; this button must not.
     */
    public function test_the_test_button_surfaces_a_failure(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('SMTP down'));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.email.test'))->assertRedirect();

        $response->assertSessionHas('error', fn (string $error) => str_contains($error, 'SMTP down'));
    }

    public function test_the_settings_routes_require_authentication(): void
    {
        $this->patch(route('settings.email'))->assertRedirect(route('login'));
        $this->post(route('settings.email.test'))->assertRedirect(route('login'));
    }

    /**
     * A transport with no credential must never report itself configured.
     *
     * The whole point of the status panel is that it cannot be green over a
     * mailer that will not send, so an API transport missing its key has to
     * read exactly like a blank SMTP password.
     */
    public function test_a_transport_is_only_configured_when_its_credential_is_present(): void
    {
        $mail = app(\App\Services\MailService::class);

        // Transports that never deliver, whatever else is set.
        foreach (['log', 'array'] as $driver) {
            config(['mail.default' => $driver]);
            $this->assertFalse($mail->isConfigured(), "{$driver} must not report configured");
        }

        // API transports: keyed on their own secret, not assumed good.
        config(['mail.default' => 'resend', 'services.resend.key' => null]);
        $this->assertFalse($mail->isConfigured(), 'resend without a key must not report configured');

        config(['services.resend.key' => 're_test_key']);
        $this->assertTrue($mail->isConfigured(), 'resend with a key must report configured');
        $this->assertSame('the Resend API', $mail->transport());

        config(['mail.default' => 'postmark', 'services.postmark.key' => null]);
        $this->assertFalse($mail->isConfigured(), 'postmark without a key must not report configured');

        // SMTP: both halves of the credential are required.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => 'someone@example.com',
            'mail.mailers.smtp.password' => null,
        ]);
        $this->assertFalse($mail->isConfigured(), 'a blank SMTP password must not report configured');

        config(['mail.mailers.smtp.password' => 'secret']);
        $this->assertTrue($mail->isConfigured());
    }
}
