<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Services\AiService;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index(AiService $ai): View
    {
        $userId = Auth::id();

        return view('dashboard', [
            'aiConfigured' => $ai->isConfigured(),
            // Drives the "nothing indexed yet" hint under the assistant.
            'indexedPapers' => Paper::query()->ownedBy($userId)->where('index_status', 'indexed')->count(),
            'stats' => $this->analytics->headlineStats($userId),
            'readingStatus' => $this->analytics->readingStatusBreakdown($userId),
            'continueReading' => $this->analytics->continueReading($userId),
            'recentPapers' => $this->analytics->recentPapers($userId),
            'topTags' => $this->analytics->topTags($userId),
            'papersByYear' => $this->analytics->papersByYear($userId),
            'addedByMonth' => $this->analytics->papersAddedByMonth($userId),
        ]);
    }
}
