<?php

use App\Enums\MemberRole;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Comment;
use App\Models\Paper;
use App\Models\Report;
use App\Models\User;

use function Pest\Laravel\actingAs;

/*
| Requirement 22: administrators "moderate reported content".
|
| Hiding a comment already worked, but nothing let a user flag one, so there
| was no queue to moderate. These cover the reporting half and the admin queue.
*/

/** A paper owned by someone else that $viewer can legitimately see. */
function sharedPaper(User $owner, User $viewer): Paper
{
    $paper = Paper::create(['title' => 'Shared Paper', 'user_id' => $owner->id]);
    $collection = Collection::create(['name' => 'Shared', 'user_id' => $owner->id]);
    $collection->papers()->attach($paper);

    CollectionMember::create([
        'collection_id' => $collection->id,
        'user_id' => $viewer->id,
        'role' => MemberRole::Viewer,
    ]);

    return $paper;
}

it('lets a user report a comment they can see', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($owner)->post(route('papers.comments.store', $paper), ['content' => 'Rude comment']);
    $comment = Comment::first();

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'comment',
        'id' => $comment->id,
        'reason' => 'Abusive language',
    ])->assertRedirect();

    $report = Report::first();

    expect($report->reportable_id)->toBe($comment->id)
        ->and($report->reportable_type)->toBe(Comment::class)
        ->and($report->status)->toBe(ReportStatus::Open)
        ->and($report->reason)->toBe('Abusive language');
});

it('lets a user report a paper they can see', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper',
        'id' => $paper->id,
        'reason' => 'Copyright violation',
    ])->assertRedirect();

    expect(Report::first()->reportable_type)->toBe(Paper::class);
});

it('refuses a report on content the user cannot see', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $paper = Paper::create(['title' => 'Private', 'user_id' => $owner->id]);

    // Reporting must not become a way to confirm another user's papers exist.
    actingAs($stranger)->post(route('reports.store'), [
        'type' => 'paper',
        'id' => $paper->id,
        'reason' => 'Fishing',
    ])->assertForbidden();

    expect(Report::count())->toBe(0);
});

it('rejects an unknown reportable type', function () {
    $user = User::factory()->create();

    // A free-form class name would let a caller point a report at any model.
    actingAs($user)->post(route('reports.store'), [
        'type' => 'App\Models\User',
        'id' => $user->id,
        'reason' => 'nope',
    ])->assertSessionHasErrors('type');

    expect(Report::count())->toBe(0);
});

it('requires a reason', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper',
        'id' => $paper->id,
    ])->assertSessionHasErrors('reason');
});

it('does not let one user flood the queue for the same item', function () {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    foreach (range(1, 3) as $i) {
        actingAs($reporter)->post(route('reports.store'), [
            'type' => 'paper',
            'id' => $paper->id,
            'reason' => "Attempt {$i}",
        ])->assertRedirect();
    }

    // Re-reporting updates the existing row rather than stacking duplicates.
    expect(Report::count())->toBe(1)
        ->and(Report::first()->reason)->toBe('Attempt 3');
});

it('reports gracefully when the content is already gone', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('reports.store'), [
        'type' => 'comment',
        'id' => 999999,
        'reason' => 'Ghost',
    ])->assertRedirect()->assertSessionHas('error');

    expect(Report::count())->toBe(0);
});

it('shows open reports in the admin queue', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper',
        'id' => $paper->id,
        'reason' => 'Needs a look',
    ]);

    actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Needs a look')
        ->assertSee('Shared Paper');
});

it('keeps researchers out of the report queue', function () {
    actingAs(User::factory()->create())
        ->get(route('admin.reports'))
        ->assertForbidden();
});

it('lets an admin resolve a report', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper', 'id' => $paper->id, 'reason' => 'Spam',
    ]);
    $report = Report::first();

    actingAs($admin)
        ->patch(route('admin.reports.resolve', $report), ['status' => 'resolved'])
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::Resolved)
        ->and($report->resolved_by)->toBe($admin->id)
        ->and($report->resolved_at)->not->toBeNull();
});

it('lets an admin dismiss a report', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper', 'id' => $paper->id, 'reason' => 'Mistake',
    ]);
    $report = Report::first();

    actingAs($admin)
        ->patch(route('admin.reports.resolve', $report), ['status' => 'dismissed'])
        ->assertRedirect();

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed);
});

it('refuses an arbitrary status', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper', 'id' => $paper->id, 'reason' => 'x',
    ]);
    $report = Report::first();

    // 'open' is not an allowed transition target either — closing is one-way
    // through this endpoint.
    actingAs($admin)
        ->patch(route('admin.reports.resolve', $report), ['status' => 'open'])
        ->assertSessionHasErrors('status');

    expect($report->fresh()->status)->toBe(ReportStatus::Open);
});

it('still renders a report whose target was deleted', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $paper = sharedPaper($owner, $reporter);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper', 'id' => $paper->id, 'reason' => 'Will vanish',
    ]);

    $paper->delete();

    actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('Will vanish')
        ->assertSee('has since been deleted');
});
