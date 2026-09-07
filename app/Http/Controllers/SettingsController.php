<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Enums\NotificationType;
use App\Services\AiService;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request, AiService $ai, MailService $mail): View
    {
        return view('settings.edit', [
            'locales' => Locale::options(),
            'current' => $request->user()->locale?->value ?? config('app.locale'),
            'aiConfigured' => $ai->isConfigured(),
            'aiProvider' => $ai->provider(),
            'aiModel' => $ai->model(),
            'mailConfigured' => $mail->isConfigured(),
            'mailTransport' => $mail->transport(),
            'notificationTypes' => NotificationType::cases(),
            'emailPrefs' => $request->user()->emailPreferenceMap(),
        ]);
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', new Enum(Locale::class)],
        ]);

        $request->user()->update(['locale' => Locale::from($validated['locale'])]);
        // Mirrored into the session so the change applies to this response too.
        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('Language updated.'));
    }

    /**
     * Save the per-type email preferences.
     *
     * Unchecked boxes are simply absent from the request, so the map is rebuilt
     * from every case rather than merged into the old one: an unticked type has
     * to persist as an explicit false, or User::wantsEmailFor() would read the
     * missing key as its "on" default and keep mailing.
     */
    public function updateEmailPrefs(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'types' => ['array'],
            'types.*' => [Rule::in(NotificationType::values())],
        ]);

        $enabled = $validated['types'] ?? [];

        $prefs = collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => [
                $type->value => in_array($type->value, $enabled, true),
            ])
            ->all();

        $request->user()->update(['email_prefs' => $prefs]);

        return back()->with('success', __('app.email_prefs_saved'));
    }

    /**
     * Prove whether mail actually leaves the building.
     *
     * NotificationService swallows mail failures on purpose; here the failure is
     * the whole point, so it is caught only in order to be shown.
     */
    public function sendTestEmail(Request $request, MailService $mail): RedirectResponse
    {
        try {
            $mail->sendTest($request->user());
        } catch (\Throwable $e) {
            return back()->with('error', __('app.test_email_failed').' '.$e->getMessage());
        }

        return back()->with('success', __('app.test_email_sent', ['email' => $request->user()->email]));
    }
}
