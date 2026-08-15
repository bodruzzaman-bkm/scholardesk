<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Models\Collection;
use App\Models\Highlight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // 1. Get Quick Stats
        $stats = [
            'total_papers' => Paper::where('user_id', $userId)->count(),
            'total_collections' => Collection::where('user_id', $userId)->count(),
            'total_notes' => Highlight::where('user_id', $userId)->count(),
        ];

        // 2. Get Reading Status Breakdown
        $readingStatus = [
            'to_read' => Paper::where('user_id', $userId)->where('reading_status', 'to read')->count(),
            'reading' => Paper::where('user_id', $userId)->where('reading_status', 'reading')->count(),
            'read' => Paper::where('user_id', $userId)->where('reading_status', 'read')->count(),
        ];

        // 3. Get the latest paper currently being read
        $continueReading = Paper::where('user_id', $userId)
                                ->where('reading_status', 'reading')
                                ->latest()
                                ->first();

        return view('dashboard', compact('stats', 'readingStatus', 'continueReading'));
    }
}