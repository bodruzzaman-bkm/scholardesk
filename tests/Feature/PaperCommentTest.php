<?php

use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Comment;
use App\Models\Paper;
use App\Models\User;
use App\Enums\MemberRole;
use App\Enums\UserRole;

use function Pest\Laravel\actingAs;

/*
| Requirement 18: "threaded comments on shared collections AND individual
| papers". The collection half already worked; these cover the paper half,
| including the case the original schema could not represent at all — a paper
| that belongs to no collection.
*/

it('lets an owner comment on their own paper that is in no collection', function () {
    $user = User::factory()->create();
    $paper = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);

    expect($paper->collections()->count())->toBe(0);

    actingAs($user)
        ->post(route('papers.comments.store', $paper), ['content' => 'First thoughts.'])
        ->assertRedirect();

    $comment = Comment::first();

    expect($comment->paper_id)->toBe($paper->id)
        ->and($comment->collection_id)->toBeNull()
        ->and($comment->content)->toBe('First thoughts.');
});

it('shows paper comments on the paper page', function () {
    $user = User::factory()->create();
    $paper = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);

    actingAs($user)->post(route('papers.comments.store', $paper), ['content' => 'Visible comment.']);

    actingAs($user)
        ->get(route('papers.show', $paper))
        ->assertOk()
        ->assertSee('Visible comment.')
        ->assertSee('Discussion');
});

it('threads replies under their parent', function () {
    $user = User::factory()->create();
    $paper = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);

    actingAs($user)->post(route('papers.comments.store', $paper), ['content' => 'Parent']);
    $parent = Comment::first();

    actingAs($user)->post(route('papers.comments.store', $paper), [
        'content' => 'Reply',
        'parent_id' => $parent->id,
    ])->assertRedirect();

    $reply = Comment::where('content', 'Reply')->first();

    expect($reply->parent_id)->toBe($parent->id)
        ->and($parent->replies()->count())->toBe(1);
});

it('refuses a reply whose parent belongs to a different paper', function () {
    $user = User::factory()->create();
    $paperA = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);
    $paperB = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);

    actingAs($user)->post(route('papers.comments.store', $paperA), ['content' => 'On A']);
    $onA = Comment::first();

    // Grafting a reply onto another paper's thread must not be possible.
    actingAs($user)->post(route('papers.comments.store', $paperB), [
        'content' => 'Smuggled',
        'parent_id' => $onA->id,
    ])->assertSessionHasErrors('parent_id');

    expect(Comment::where('content', 'Smuggled')->exists())->toBeFalse();
});

it('lets a collaborator comment on a shared paper', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();

    $paper = Paper::create(['title' => 'A paper', 'user_id' => $owner->id]);
    $collection = Collection::create(['name' => 'A collection', 'user_id' => $owner->id]);
    $collection->papers()->attach($paper);

    CollectionMember::create([
        'collection_id' => $collection->id,
        'user_id' => $collaborator->id,
        'role' => MemberRole::Viewer,
    ]);

    actingAs($collaborator)
        ->post(route('papers.comments.store', $paper), ['content' => 'From a collaborator.'])
        ->assertRedirect();

    expect(Comment::where('content', 'From a collaborator.')->exists())->toBeTrue();
});

it('blocks a stranger from commenting on a paper they cannot see', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $paper = Paper::create(['title' => 'A paper', 'user_id' => $owner->id]);

    actingAs($stranger)
        ->post(route('papers.comments.store', $paper), ['content' => 'Should not land.'])
        ->assertForbidden();

    expect(Comment::count())->toBe(0);
});

it('notifies the paper owner when someone else comments', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();

    $paper = Paper::create(['user_id' => $owner->id, 'title' => 'Attention Is All You Need']);
    $collection = Collection::create(['name' => 'A collection', 'user_id' => $owner->id]);
    $collection->papers()->attach($paper);

    CollectionMember::create([
        'collection_id' => $collection->id,
        'user_id' => $collaborator->id,
        'role' => MemberRole::Editor,
    ]);

    actingAs($collaborator)->post(route('papers.comments.store', $paper), ['content' => 'Nice paper.']);

    expect($owner->inAppNotifications()->count())->toBe(1)
        ->and($owner->inAppNotifications()->first()->message)->toContain('Attention Is All You Need');

    // The commenter is never notified about their own comment.
    expect($collaborator->inAppNotifications()->count())->toBe(0);
});

it('renders a paper-only comment in the admin moderation queue', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    $user = User::factory()->create();
    $paper = Paper::create(['user_id' => $user->id, 'title' => 'Orphan Paper']);

    actingAs($user)->post(route('papers.comments.store', $paper), ['content' => 'No collection here.']);

    // This 500'd before the view guarded a null collection.
    actingAs($admin)
        ->get(route('admin.comments'))
        ->assertOk()
        ->assertSee('Orphan Paper')
        ->assertSee('No collection here.');
});

it('records a paper comment in the activity feed of every collection holding it', function () {
    $owner = User::factory()->create();
    $paper = Paper::create(['title' => 'Shared Paper', 'user_id' => $owner->id]);

    // The same paper filed in two collections.
    $a = Collection::create(['name' => 'Alpha', 'user_id' => $owner->id]);
    $b = Collection::create(['name' => 'Beta', 'user_id' => $owner->id]);
    $a->papers()->attach($paper);
    $b->papers()->attach($paper);

    actingAs($owner)->post(route('papers.comments.store', $paper), ['content' => 'Worth reading.']);

    // Requirement 19 says the feed records "comments posted" — to a
    // collaborator watching Alpha, a comment on a paper inside Alpha is
    // exactly the event the feed exists to surface.
    foreach ([$a, $b] as $collection) {
        expect(
            App\Models\Activity::where('collection_id', $collection->id)
                ->where('type', App\Enums\ActivityType::CommentAdded->value)
                ->exists()
        )->toBeTrue("the comment should appear in {$collection->name}'s feed");
    }
});

it('does not invent a feed entry for a paper in no collection', function () {
    $user = User::factory()->create();
    $paper = Paper::create(['title' => 'Solo', 'user_id' => $user->id]);

    actingAs($user)->post(route('papers.comments.store', $paper), ['content' => 'Private thought.']);

    // No collection means no feed to write to; the comment still lands.
    expect(App\Models\Activity::count())->toBe(0)
        ->and(Comment::count())->toBe(1);
});

/*
| The reply form is only rendered on top-level comments, and the comment
| component documents the rule as "one level deep: threads ... cannot nest
| forever". That was enforced in the UI alone: the server checked only that a
| parent lived on the same paper, so a hand-made POST could reply to a reply.
|
| It matters twice over. The Blade component recurses, and only one level of
| replies is eager-loaded, so each extra level costs another query; and the
| indentation compounds until the thread is unreadable.
*/
it('refuses a reply to a reply on a paper, so threads stay one level deep', function () {
    $user = User::factory()->create();
    $paper = Paper::create(['title' => 'A paper', 'user_id' => $user->id]);

    actingAs($user)->post(route('papers.comments.store', $paper), ['content' => 'Root']);
    $root = Comment::firstOrFail();

    actingAs($user)->post(route('papers.comments.store', $paper), [
        'content' => 'A reply',
        'parent_id' => $root->id,
    ])->assertRedirect();

    $reply = Comment::where('parent_id', $root->id)->firstOrFail();

    // Replying to the reply is the case the UI never offers.
    actingAs($user)->post(route('papers.comments.store', $paper), [
        'content' => 'Reply to the reply',
        'parent_id' => $reply->id,
    ])->assertSessionHasErrors('parent_id');

    expect(Comment::where('parent_id', $reply->id)->count())->toBe(0)
        ->and(Comment::count())->toBe(2);
});

it('refuses a reply to a reply on a collection thread too', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'Shared', 'user_id' => $user->id]);

    actingAs($user)->post(route('comments.store', $collection), ['content' => 'Root']);
    $root = Comment::firstOrFail();

    actingAs($user)->post(route('comments.store', $collection), [
        'content' => 'A reply',
        'parent_id' => $root->id,
    ])->assertRedirect();

    $reply = Comment::where('parent_id', $root->id)->firstOrFail();

    actingAs($user)->post(route('comments.store', $collection), [
        'content' => 'Too deep',
        'parent_id' => $reply->id,
    ])->assertSessionHasErrors('parent_id');

    expect(Comment::count())->toBe(2);
});
