<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Paper;
use App\Models\PaperChunk;
use App\Models\Report;
use App\Models\User;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Administrator portal: system statistics, user/role management and comment
 * moderation. Gated by the `admin` middleware alias on the route group.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'stats' => [
                'users' => User::count(),
                'admins' => User::where('role', UserRole::Administrator->value)->count(),
                'papers' => Paper::count(),
                'collections' => Collection::count(),
                'notes' => Note::count(),
                'highlights' => Highlight::count(),
                'comments' => Comment::count(),
                'indexed_papers' => Paper::where('index_status', 'indexed')->count(),
                'chunks' => PaperChunk::count(),
                'open_reports' => Report::query()->open()->count(),
                'storage_mb' => round($this->storageBytes() / 1_048_576, 1),
            ],
            'recentUsers' => User::latest()->limit(5)->get(),
            'topUsers' => User::query()
                ->withCount('papers')
                ->orderByDesc('papers_count')
                ->limit(5)
                ->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $users = User::query()
            // Search, not `like`: Postgres LIKE is case-sensitive, so looking
            // up "Vaskar" would miss "vaskar@..." once deployed. It also
            // escapes the wildcards the raw '%'.$term.'%' left open.
            ->when($request->query('q'), fn ($q, $term) => Search::anyColumn($q, ['name', 'email'], $term))
            ->withCount(['papers', 'collections'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'roles' => collect(UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        // An administrator must not be able to strip their own access and
        // lock the last admin out of the portal.
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->update(['role' => UserRole::from($validated['role'])]);

        return back()->with('success', "Role updated for {$user->name}.");
    }

    /**
     * Suspend or reinstate an account (requirement 22).
     *
     * Suspension rather than deletion: deleting a user cascades through their
     * papers, collections, notes, highlights and comments, which destroys a
     * library to silence an account and cannot be undone. A suspended user
     * keeps everything and simply cannot sign in.
     *
     * The suspension columns are deliberately absent from the model's
     * fillable list — they are a privileged decision, not user-editable data
     * — so they are assigned explicitly here rather than mass-assigned.
     */
    public function toggleSuspension(Request $request, User $user): RedirectResponse
    {
        // The same guard as updateRole: an administrator locking themselves
        // out is unrecoverable without database access.
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($user->isSuspended()) {
            $user->suspended_at = null;
            $user->suspended_by = null;
            $user->suspension_reason = null;
            $user->save();

            return back()->with('success', "{$user->name} can sign in again.");
        }

        $user->suspended_at = now();
        $user->suspended_by = $request->user()->id;
        $user->suspension_reason = $validated['reason'] ?? null;
        $user->save();

        return back()->with('success', "{$user->name} is suspended and has been signed out.");
    }

    /** Moderation queue: every comment, newest first. */
    public function comments(): View
    {
        $comments = Comment::query()
            ->with(['user:id,name', 'collection:id,name', 'paper:id,title'])
            ->latest()
            ->paginate(25);

        return view('admin.comments', compact('comments'));
    }

    /**
     * Hide or unhide a comment. Hiding is reversible and keeps the thread
     * shape, which is why moderation does not simply delete.
     */
    public function toggleCommentVisibility(Comment $comment): RedirectResponse
    {
        $comment->update(['is_hidden' => ! $comment->is_hidden]);

        return back()->with('success', $comment->is_hidden ? 'Comment hidden.' : 'Comment restored.');
    }

    /**
     * The report queue (requirement 22).
     *
     * Defaults to open reports, because that is the work. `?status=` switches
     * to the resolved or dismissed history.
     */
    public function reports(Request $request): View
    {
        $status = $request->query('status', ReportStatus::Open->value);

        $reports = Report::query()
            ->withStatus($status)
            ->with(['user:id,name', 'resolver:id,name', 'reportable'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.reports', [
            'reports' => $reports,
            'status' => $status,
            'openCount' => Report::query()->open()->count(),
        ]);
    }

    /**
     * Close a report as resolved or dismissed.
     *
     * Resolving does not itself hide the reported content — that stays a
     * separate, deliberate action on the comment. An administrator can decide
     * a report is valid and still leave the content up.
     */
    public function resolveReport(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ReportStatus::Resolved->value, ReportStatus::Dismissed->value])],
        ]);

        $status = ReportStatus::from($validated['status']);

        $report->update([
            'status' => $status,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('success', "Report marked {$status->label()}.");
    }

    private function storageBytes(): int
    {
        return (int) DB::table('papers')->whereNotNull('file_path')->count() > 0
            ? collect(\Illuminate\Support\Facades\Storage::disk('public')->allFiles('papers'))
                ->sum(fn ($f) => \Illuminate\Support\Facades\Storage::disk('public')->size($f))
            : 0;
    }
}
