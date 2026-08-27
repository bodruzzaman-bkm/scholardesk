<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Services\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request, AiService $ai): View
    {
        return view('settings.edit', [
            'locales' => Locale::options(),
            'current' => $request->user()->locale?->value ?? config('app.locale'),
            'aiConfigured' => $ai->isConfigured(),
            'aiProvider' => $ai->provider(),
            'aiModel' => $ai->model(),
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
}
