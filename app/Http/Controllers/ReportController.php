<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Paper;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Users flag content for an administrator to look at (requirement 22).
 *
 * Reporting is deliberately NOT gated by a policy on the target. Someone who
 * can see a comment can report it, and that is the whole population that could
 * be harmed by it. Requiring ownership would mean only the author of a comment
 * could report their own comment, which is not a moderation system.
 *
 * Access is still checked, though: you cannot report something you cannot see,
 * because that would confirm the existence of other users' private papers.
 */
class ReportController extends Controller
{
    /**
     * The content types a user may report.
     *
     * An allowlist rather than accepting a class name from the request:
     * a free-form `reportable_type` would let a caller point a report at any
     * model in the application.
     *
     * @var array<string, class-string>
     */
    private const REPORTABLE = [
        'comment' => Comment::class,
        'paper' => Paper::class,
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::REPORTABLE))],
            'id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $model = self::REPORTABLE[$validated['type']];
        $target = $model::find($validated['id']);

        if ($target === null) {
            return back()->with('error', 'That content no longer exists.');
        }

        $this->authorizeVisibility($request, $target);

        // updateOrCreate, not create: the unique index means a second report
        // from the same person would otherwise be a 500 rather than a no-op.
        // Re-reporting refreshes the reason and reopens a dismissed report,
        // which is the useful behaviour when something changes.
        Report::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'reportable_type' => $model,
                'reportable_id' => $target->id,
            ],
            [
                'reason' => $validated['reason'],
                'status' => \App\Enums\ReportStatus::Open,
                'resolved_by' => null,
                'resolved_at' => null,
            ],
        );

        return back()->with('success', 'Reported. An administrator will review it.');
    }

    /**
     * You may only report what you can already see.
     *
     * A comment is visible if its collection or its paper is; a paper follows
     * PaperPolicy::view.
     */
    private function authorizeVisibility(Request $request, Paper|Comment $target): void
    {
        if ($target instanceof Paper) {
            $this->authorize('view', $target);

            return;
        }

        if ($target->collection !== null) {
            $this->authorize('view', $target->collection);

            return;
        }

        if ($target->paper !== null) {
            $this->authorize('view', $target->paper);

            return;
        }

        // A comment anchored to nothing is unreachable in the UI; refuse
        // rather than guess.
        abort(403);
    }
}
