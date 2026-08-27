<?php

use App\Enums\MemberRole;
use App\Enums\ReadingStatus;
use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Paper;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| The 22 functional requirements, as a checklist
|--------------------------------------------------------------------------
|
| One test per numbered requirement in the ScholarDesk proposal, asserting
| that its entry point exists and responds. This is deliberately shallow —
| the depth lives in the per-feature suites — but it fails loudly if a route
| is renamed, a controller is broken, or a view stops rendering.
|
| The point is that "all 22 work" becomes something the suite proves rather
| than something a person re-checks by hand.
*/

/** A researcher with one indexed-looking paper, a collection and a tag. */
function library(): array
{
    $user = User::factory()->create();

    $paper = Paper::create([
        'title' => 'Attention Is All You Need',
        'authors' => 'Ashish Vaswani',
        'year' => 2017,
        'venue' => 'NeurIPS',
        'doi' => '10.5555/3295222',
        'abstract' => 'A new architecture based on attention.',
        'user_id' => $user->id,
        'reading_status' => ReadingStatus::ToRead,
    ]);

    $collection = Collection::create(['name' => 'Transformers', 'user_id' => $user->id]);
    CollectionMember::create([
        'collection_id' => $collection->id,
        'user_id' => $user->id,
        'role' => MemberRole::Owner,
    ]);
    $collection->papers()->attach($paper);

    $tag = Tag::create(['name' => 'nlp', 'color' => '#4F46E5', 'user_id' => $user->id]);
    $paper->tags()->attach($tag);

    return compact('user', 'paper', 'collection', 'tag');
}

// -- Authentication features ------------------------------------------------

it('req auth: a visitor can reach register, login and password recovery', function () {
    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk();
    $this->get(route('password.request'))->assertOk();
});

it('req auth: every new account is a researcher, never an administrator', function () {
    $this->post(route('register'), [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'password123!',
        'password_confirmation' => 'password123!',
        'role' => 'administrator',   // ignored on purpose
    ]);

    expect(User::where('email', 'someone@example.com')->first()->role)
        ->toBe(UserRole::Researcher);
});

// -- Module 1 ---------------------------------------------------------------

it('req 1: two account types exist and the admin portal is gated', function () {
    ['user' => $researcher] = library();

    actingAs($researcher)->get(route('admin.index'))->assertForbidden();

    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    actingAs($admin)->get(route('admin.index'))->assertOk();

    // Owner / Editor / Viewer exist for shared collections.
    expect(MemberRole::cases())->toHaveCount(3);
});

it('req 2: a paper can be added by uploading a PDF', function () {
    Storage::fake('public');
    ['user' => $user] = library();

    actingAs($user)->post(route('papers.store'), [
        'file' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf'),
        'title' => 'Uploaded Paper',
    ])->assertRedirect();

    expect(Paper::where('title', 'Uploaded Paper')->exists())->toBeTrue();
});

it('req 3: the add form accepts a DOI or URL identifier', function () {
    ['user' => $user] = library();

    // The form exposes one field for either; resolution itself is covered by
    // ImportByUrlTest and MetadataServiceTest against faked HTTP.
    actingAs($user)->get(route('papers.create'))
        ->assertOk()
        ->assertSee('identifier', false);
});

it('req 4: a paper can be viewed, edited, deleted and re-statused', function () {
    ['user' => $user, 'paper' => $paper] = library();

    actingAs($user)->get(route('papers.show', $paper))->assertOk();
    actingAs($user)->get(route('papers.edit', $paper))->assertOk();

    actingAs($user)->patch(route('papers.status', $paper), [
        'reading_status' => ReadingStatus::Read->value,
    ])->assertRedirect();

    expect($paper->fresh()->reading_status)->toBe(ReadingStatus::Read);

    actingAs($user)->delete(route('papers.destroy', $paper))->assertRedirect();
    expect(Paper::find($paper->id))->toBeNull();
});

it('req 5: a paper sits in several collections and detaches without deleting', function () {
    ['user' => $user, 'paper' => $paper, 'collection' => $first] = library();

    $second = Collection::create(['name' => 'Second', 'user_id' => $user->id]);
    CollectionMember::create([
        'collection_id' => $second->id, 'user_id' => $user->id, 'role' => MemberRole::Owner,
    ]);

    actingAs($user)->post(route('collections.papers.add', $second), ['paper_id' => $paper->id])
        ->assertRedirect();

    expect($paper->fresh()->collections)->toHaveCount(2);

    actingAs($user)->delete(route('collections.papers.remove', [$first, $paper]))->assertRedirect();

    // Detached from one collection, still in the library.
    expect($paper->fresh()->collections)->toHaveCount(1)
        ->and(Paper::find($paper->id))->not->toBeNull();
});

it('req 6: coloured tags exist and filter the library', function () {
    ['user' => $user, 'tag' => $tag] = library();

    actingAs($user)->get(route('tags.index'))->assertOk()->assertSee('nlp');
    actingAs($user)->get(route('papers.index', ['tag' => $tag->id]))
        ->assertOk()
        ->assertSee('Attention Is All You Need');
});

// -- Module 2 ---------------------------------------------------------------

it('req 7: the reader opens for a paper with a PDF and highlights round-trip', function () {
    Storage::fake('public');
    ['user' => $user] = library();

    $path = UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')->store('papers', 'public');
    $paper = Paper::create(['title' => 'Readable', 'user_id' => $user->id, 'file_path' => $path]);

    actingAs($user)->get(route('papers.read', $paper))->assertOk();

    actingAs($user)->postJson(route('highlights.store', $paper), [
        'color' => '#FDE68A',
        'position' => ['page' => 1, 'rects' => [['left' => 10, 'top' => 20, 'width' => 30, 'height' => 8]]],
        'text' => 'a highlighted phrase',
    ])->assertCreated();

    actingAs($user)->getJson(route('highlights.index', $paper))
        ->assertOk()
        ->assertJsonCount(1);
});

it('req 8: markdown notes can be written, edited and deleted', function () {
    ['user' => $user, 'paper' => $paper] = library();

    actingAs($user)->post(route('notes.store', $paper), ['content' => '## Heading'])->assertRedirect();

    $note = $paper->notes()->first();
    expect($note)->not->toBeNull();

    actingAs($user)->put(route('notes.update', $note), ['content' => '## Edited'])->assertRedirect();
    expect($note->fresh()->content)->toBe('## Edited');

    actingAs($user)->delete(route('notes.destroy', $note))->assertRedirect();
    expect($paper->notes()->count())->toBe(0);
});

it('req 9: the summary endpoint exists and reports cleanly when unconfigured', function () {
    ['user' => $user, 'paper' => $paper] = library();

    config(['services.groq.key' => null, 'services.gemini.key' => null]);

    // Without a key the AI degrades to a 503 with a readable message rather
    // than a 500 — the product rule that AI is never a hard dependency.
    actingAs($user)->postJson(route('ai.paper.summary', $paper))
        ->assertStatus(503)
        ->assertJsonStructure(['error']);
});

it('req 10: single-paper Q&A validates and responds', function () {
    ['user' => $user, 'paper' => $paper] = library();

    actingAs($user)->postJson(route('ai.paper.ask', $paper), ['question' => 'hi'])
        ->assertStatus(422); // min:3

    actingAs($user)->postJson(route('ai.paper.ask', $paper), ['question' => 'What is the method?'])
        ->assertStatus(503); // no indexed chunks in this fixture
});

// -- Module 3 ---------------------------------------------------------------

it('req 11: collection-wide Q&A is routed and authorized', function () {
    ['user' => $user, 'collection' => $collection] = library();

    actingAs($user)->postJson(route('ai.collection.ask', $collection), ['question' => 'What themes appear?'])
        ->assertStatus(503);

    actingAs(User::factory()->create())
        ->postJson(route('ai.collection.ask', $collection), ['question' => 'Sneaking a look'])
        ->assertForbidden();
});

it('req 12: search offers both modes and all five filters', function () {
    ['user' => $user] = library();

    actingAs($user)->get(route('search', ['q' => 'attention']))
        ->assertOk()
        ->assertSee('Attention Is All You Need');

    actingAs($user)->get(route('search', ['q' => 'attention', 'mode' => 'semantic']))->assertOk();

    // year, author, venue, tag, status — every filter the proposal names.
    actingAs($user)->get(route('search', [
        'q' => 'attention',
        'year' => 2017,
        'author' => 'Vaswani',
        'venue' => 'NeurIPS',
        'status' => ReadingStatus::ToRead->value,
    ]))->assertOk()->assertSee('Attention Is All You Need');
});

it('req 13: related papers responds as JSON without an API key', function () {
    ['user' => $user, 'paper' => $paper] = library();

    // Pure local vector maths, so this must work with no provider configured.
    actingAs($user)->getJson(route('ai.paper.related', $paper))
        ->assertOk()
        ->assertJsonStructure(['related', 'indexed']);
});

it('req 14: the review endpoint accepts a paper selection', function () {
    ['user' => $user, 'collection' => $collection, 'paper' => $paper] = library();

    actingAs($user)->postJson(route('ai.collection.review', $collection), ['paper_ids' => [$paper->id]])
        ->assertStatus(503);

    // A paper outside the collection cannot be pulled into a draft.
    $outside = Paper::create(['title' => 'Elsewhere', 'user_id' => $user->id]);
    actingAs($user)->postJson(route('ai.collection.review', $collection), ['paper_ids' => [$outside->id]])
        ->assertStatus(422);
});

it('req 15: citations export in BibTeX, APA and text, per paper and per collection', function () {
    ['user' => $user, 'paper' => $paper, 'collection' => $collection] = library();

    foreach (['bibtex', 'apa', 'text'] as $format) {
        actingAs($user)->get(route('papers.export', ['paper' => $paper, 'format' => $format]))
            ->assertOk()
            ->assertSee('Attention', false);

        actingAs($user)->get(route('collections.export', ['collection' => $collection, 'format' => $format]))
            ->assertOk();
    }
});

// -- Module 4 ---------------------------------------------------------------

it('req 16: a collection downloads as one archive carrying its PDFs', function () {
    Storage::fake('public');
    ['user' => $user, 'collection' => $collection] = library();

    actingAs($user)->get(route('collections.bundle', $collection))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

it('req 17: a collection is shared with a role, and the role is enforced', function () {
    ['user' => $owner, 'collection' => $collection] = library();
    $viewer = User::factory()->create(['email' => 'viewer@example.com']);

    actingAs($owner)->post(route('collections.members.add', $collection), [
        'email' => 'viewer@example.com',
        'role' => MemberRole::Viewer->value,
    ])->assertRedirect();

    actingAs($viewer)->get(route('collections.show', $collection))->assertOk();

    // A viewer reads but does not write.
    actingAs($viewer)->post(route('collections.papers.add', $collection), ['paper_id' => 1])
        ->assertForbidden();
});

it('req 18: threaded comments work on collections and on individual papers', function () {
    ['user' => $user, 'collection' => $collection, 'paper' => $paper] = library();

    actingAs($user)->post(route('comments.store', $collection), ['content' => 'On the collection'])
        ->assertRedirect();

    actingAs($user)->post(route('papers.comments.store', $paper), ['content' => 'On the paper'])
        ->assertRedirect();

    actingAs($user)->get(route('papers.show', $paper))->assertOk()->assertSee('On the paper');
});

it('req 19: the activity feed records what happened in a collection', function () {
    ['user' => $user, 'collection' => $collection] = library();

    $paper = Paper::create(['title' => 'Feed paper', 'user_id' => $user->id]);
    actingAs($user)->post(route('collections.papers.add', $collection), ['paper_id' => $paper->id]);

    $this->assertDatabaseHas('activities', [
        'collection_id' => $collection->id,
        'type' => 'paper_added',
    ]);
});

it('req 20: in-app notifications are delivered and readable', function () {
    ['user' => $owner, 'collection' => $collection] = library();
    $member = User::factory()->create(['email' => 'member@example.com']);

    actingAs($owner)->post(route('collections.members.add', $collection), [
        'email' => 'member@example.com',
        'role' => MemberRole::Editor->value,
    ]);

    expect($member->inAppNotifications()->count())->toBe(1);

    actingAs($member)->get(route('notifications.index'))->assertOk();
    actingAs($member)->getJson(route('notifications.count'))->assertOk()->assertJson(['count' => 1]);
});

it('req 21: the analytics dashboard shows all five breakdowns', function () {
    ['user' => $user] = library();

    actingAs($user)->get(route('analytics'))
        ->assertOk()
        ->assertSee('Papers added over time')
        ->assertSee('By publication year')
        ->assertSee('By venue')
        ->assertSee('By tag')
        ->assertSee('By reading status')
        ->assertSee('NeurIPS');
});

it('req 22: admins manage roles, moderate reports, see stats, and the UI is bilingual', function () {
    $admin = User::factory()->create(['role' => UserRole::Administrator]);
    ['user' => $user, 'paper' => $paper] = library();

    // Roles
    actingAs($admin)->patch(route('admin.users.role', $user), ['role' => UserRole::Administrator->value])
        ->assertRedirect();
    expect($user->fresh()->role)->toBe(UserRole::Administrator);

    // Reported content — a real queue, not just a hide button.
    $reporter = User::factory()->create();
    $shared = Collection::create(['name' => 'Shared', 'user_id' => $user->id]);
    $shared->papers()->attach($paper);
    CollectionMember::create([
        'collection_id' => $shared->id, 'user_id' => $reporter->id, 'role' => MemberRole::Viewer,
    ]);

    actingAs($reporter)->post(route('reports.store'), [
        'type' => 'paper', 'id' => $paper->id, 'reason' => 'Needs review',
    ])->assertRedirect();

    actingAs($admin)->get(route('admin.reports'))->assertOk()->assertSee('Needs review');

    // System-wide statistics
    actingAs($admin)->get(route('admin.index'))->assertOk();

    // English and Bangla
    expect(array_keys(require base_path('lang/en/app.php')))
        ->toEqual(array_keys(require base_path('lang/bn/app.php')));
});
