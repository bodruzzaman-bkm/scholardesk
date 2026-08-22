<?php

namespace App\Services;

use App\Enums\ReadingStatus;
use App\Models\Collection;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Paper;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Aggregations for the dashboard and analytics views.
 *
 * Every method is scoped to a single user id — there is no code path here that
 * can return another researcher's numbers.
 */
class AnalyticsService
{
    /** @return array<string, int> */
    public function headlineStats(int $userId): array
    {
        return [
            'papers' => Paper::query()->ownedBy($userId)->count(),
            'collections' => Collection::query()->ownedBy($userId)->count(),
            'tags' => Tag::query()->ownedBy($userId)->count(),
            // The dashboard card is labelled "Highlights & notes", so it must
            // count both. The previous implementation counted highlights only.
            'highlights' => Highlight::query()->where('user_id', $userId)->count(),
            'notes' => Note::query()->where('user_id', $userId)->count(),
        ];
    }

    /** @return array<string, int> keyed by ReadingStatus value */
    public function readingStatusBreakdown(int $userId): array
    {
        $counts = Paper::query()
            ->ownedBy($userId)
            ->groupBy('reading_status')
            ->select('reading_status', DB::raw('count(*) as aggregate'))
            ->pluck('aggregate', 'reading_status')
            ->all();

        $result = [];
        foreach (ReadingStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * Papers grouped by publication year, newest first.
     *
     * @return array<int|string, int>
     */
    public function papersByYear(int $userId, int $limit = 10): array
    {
        return Paper::query()
            ->ownedBy($userId)
            ->whereNotNull('year')
            ->groupBy('year')
            ->select('year', DB::raw('count(*) as aggregate'))
            ->orderByDesc('year')
            ->limit($limit)
            ->pluck('aggregate', 'year')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Most-used tags with their paper counts and colours.
     *
     * @return \Illuminate\Support\Collection<int, Tag>
     */
    public function topTags(int $userId, int $limit = 8)
    {
        return Tag::query()
            ->ownedBy($userId)
            ->withCount('papers')
            // whereHas rather than having(): withCount builds a correlated
            // subquery, and SQLite rejects HAVING on a query with no GROUP BY.
            ->whereHas('papers')
            ->orderByDesc('papers_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Papers added per month over the last N months, oldest first.
     * Returned as an ordered map so the chart has no gaps.
     *
     * @return array<string, int>
     */
    public function papersAddedByMonth(int $userId, int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = Paper::query()
            ->ownedBy($userId)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($paper) => $paper->created_at->format('Y-m'))
            ->map(fn ($group) => $group->count());

        // Pre-fill every month in range so quiet months render as zero.
        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $series[$key] = (int) ($rows[$key] ?? 0);
        }

        return $series;
    }

    /** The paper the user is most likely to want to resume. */
    public function continueReading(int $userId): ?Paper
    {
        return Paper::query()
            ->ownedBy($userId)
            ->where('reading_status', ReadingStatus::Reading->value)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Most recently added papers, for the dashboard's "recently added" list.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Paper>
     */
    public function recentPapers(int $userId, int $limit = 5)
    {
        return Paper::query()
            ->ownedBy($userId)
            ->with('tags')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
