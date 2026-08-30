<?php

use App\Enums\NotificationType;
use App\Jobs\IndexPaper;
use App\Models\Collection;
use App\Models\CollectionMember;
use App\Models\Paper;
use App\Models\User;
use App\Enums\MemberRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/*
| Requirement 20 names three triggers:
|
|   "when they are added to a collection, when someone comments, or when an
|    AI task completes"
|
| The first two were covered. The third was not — NotificationType::AiDone
| existed as an enum case and nothing ever dispatched it, so the requirement
| was two-thirds built and the gap was invisible: an unused enum case throws
| no error and fails no test.
|
| These pin all three, so a case can never again be declared and forgotten.
*/

it('notifies the owner when indexing finishes', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $path = UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')->store('papers', 'public');
    $paper = Paper::create([
        'title' => 'Attention Is All You Need',
        'user_id' => $user->id,
        'file_path' => $path,
    ]);

    (new IndexPaper($paper->id))->handle(
        app(App\Services\IndexingService::class),
        app(App\Services\NotificationService::class),
    );

    $note = $user->inAppNotifications()->first();

    expect($note)->not->toBeNull()
        ->and($note->type)->toBe(NotificationType::AiDone)
        ->and($note->message)->toContain('Attention Is All You Need');
});

it('says so when indexing could not read the paper', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // A file that is not a readable PDF: extraction produces nothing.
    $path = UploadedFile::fake()->create('broken.pdf', 2, 'application/pdf')->store('papers', 'public');
    $paper = Paper::create(['title' => 'Unreadable', 'user_id' => $user->id, 'file_path' => $path]);

    (new IndexPaper($paper->id))->handle(
        app(App\Services\IndexingService::class),
        app(App\Services\NotificationService::class),
    );

    // Silence after an upload reads as success. A failure has to be said out
    // loud, or the user only finds out when the assistant claims to know
    // nothing about a paper they just added.
    expect($user->inAppNotifications()->first()->message)
        ->toContain('could not be indexed');
});

it('does not fall over when the paper was deleted before the job ran', function () {
    $user = User::factory()->create();
    $paper = Paper::create(['title' => 'Doomed', 'user_id' => $user->id]);
    $id = $paper->id;
    $paper->delete();

    (new IndexPaper($id))->handle(
        app(App\Services\IndexingService::class),
        app(App\Services\NotificationService::class),
    );

    expect($user->inAppNotifications()->count())->toBe(0);
});

it('notifies when a literature review finishes', function () {
    $user = User::factory()->create();

    $collection = Collection::create(['name' => 'Transformers', 'user_id' => $user->id]);
    CollectionMember::create([
        'collection_id' => $collection->id, 'user_id' => $user->id, 'role' => MemberRole::Owner,
    ]);
    $paper = Paper::create([
        'title' => 'A Paper', 'user_id' => $user->id,
        'abstract' => 'Self-attention replaces recurrence entirely.',
    ]);
    $collection->papers()->attach($paper);

    config(['services.groq.key' => 'test-key']);
    Http::fake([
        '*' => Http::response(['choices' => [['message' => ['content' => '## Introduction

A drafted review.']]]], 200),
    ]);

    app(App\Services\RagService::class)->draftReview($collection, $user);

    $note = $user->inAppNotifications()
        ->where('type', NotificationType::AiDone->value)
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->message)->toContain('Transformers')
        ->and($note->message)->toContain('ready');
});

it('covers all three triggers requirement 20 names', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'member@example.com']);

    $collection = Collection::create(['name' => 'Shared', 'user_id' => $owner->id]);
    CollectionMember::create([
        'collection_id' => $collection->id, 'user_id' => $owner->id, 'role' => MemberRole::Owner,
    ]);

    // 1. added to a collection
    actingAs($owner)->post(route('collections.members.add', $collection), [
        'email' => 'member@example.com',
        'role' => MemberRole::Editor->value,
    ])->assertRedirect();

    expect($member->inAppNotifications()->where('type', NotificationType::Share->value)->count())->toBe(1);

    // 2. someone comments
    actingAs($member)->post(route('comments.store', $collection), ['content' => 'Hello'])->assertRedirect();

    expect($owner->inAppNotifications()->where('type', NotificationType::Comment->value)->count())->toBe(1);

    // 3. an AI task completes — proved by the indexing and review tests above.
    // Asserted here as the enum case being reachable at all.
    expect(NotificationType::AiDone->value)->toBe('ai_done');
});
