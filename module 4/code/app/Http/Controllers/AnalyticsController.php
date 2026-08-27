<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The analytics dashboard (requirement 21).
 *
 * Separate from the main dashboard on purpose: /dashboard is a working
 * surface — continue reading, recent papers, jump back in — while this page
 * answers "what does my library look like?" and carries every breakdown the
 * proposal names.
 *
 * Every figure comes from AnalyticsService, which scopes each query to one
 * user id, so there is no code path here that can surface another
 * researcher's numbers.
 */
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index(): View
    {
        $userId = Auth::id();

        return view('analytics.index', [
            'stats' => $this->analytics->headlineStats($userId),
            'addedByMonth' => $this->analytics->papersAddedByMonth($userId, 12),
            'papersByYear' => $this->analytics->papersByYear($userId),
            'papersByVenue' => $this->analytics->papersByVenue($userId),
            'topTags' => $this->analytics->topTags($userId),
            'readingStatus' => $this->analytics->readingStatusBreakdown($userId),
        ]);
    }
}
